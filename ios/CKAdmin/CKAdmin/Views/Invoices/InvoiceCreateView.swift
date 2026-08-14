import SwiftUI

/// Form for creating a one-off invoice.
///
/// Provides fields for customer ID, amount, line items description, and due date.
/// Submits via POST /api/admin/invoices and dismisses on success.
/// Displays field-level validation errors from 422 responses.
struct InvoiceCreateView: View {
    @Environment(\.dismiss) private var dismiss

    // MARK: - Form Fields

    @State private var companyId: String = ""
    @State private var invoiceAmount: String = ""
    @State private var invoiceItems: String = ""
    @State private var dueDate: Date = Calendar.current.date(byAdding: .day, value: 30, to: Date()) ?? Date()

    // MARK: - State

    @State private var isSaving = false
    @State private var generalError: String?
    @State private var fieldErrors: [String: [String]] = [:]

    // MARK: - Properties

    private let apiClient: APIClient
    private let onSave: (() async -> Void)?

    /// Creates an invoice creation form.
    /// - Parameters:
    ///   - apiClient: The API client for network requests.
    ///   - onSave: Optional callback invoked after a successful save.
    init(
        apiClient: APIClient,
        onSave: (() async -> Void)? = nil
    ) {
        self.apiClient = apiClient
        self.onSave = onSave
    }

    var body: some View {
        NavigationStack {
            Form {
                // General error banner
                if let generalError {
                    Section {
                        Label(generalError, systemImage: "exclamationmark.triangle")
                            .foregroundStyle(.red)
                            .font(.subheadline)
                    }
                }

                // Customer
                Section("Customer") {
                    VStack(alignment: .leading, spacing: 4) {
                        TextField("Company ID *", text: $companyId)
                            .keyboardType(.numberPad)
                            .autocorrectionDisabled()
                            .onChange(of: companyId) { _, _ in
                                fieldErrors["company_id"] = nil
                            }

                        if let errors = fieldErrors["company_id"], !errors.isEmpty {
                            ForEach(errors, id: \.self) { error in
                                Text(error)
                                    .font(.caption)
                                    .foregroundStyle(.red)
                            }
                        }
                    }
                }

                // Amount
                Section("Amount") {
                    VStack(alignment: .leading, spacing: 4) {
                        TextField("Invoice Amount (£) *", text: $invoiceAmount)
                            .keyboardType(.decimalPad)
                            .autocorrectionDisabled()
                            .onChange(of: invoiceAmount) { _, _ in
                                fieldErrors["invoice_amount"] = nil
                            }

                        if let errors = fieldErrors["invoice_amount"], !errors.isEmpty {
                            ForEach(errors, id: \.self) { error in
                                Text(error)
                                    .font(.caption)
                                    .foregroundStyle(.red)
                            }
                        }
                    }
                }

                // Line Items
                Section("Line Items") {
                    VStack(alignment: .leading, spacing: 4) {
                        TextEditor(text: $invoiceItems)
                            .frame(minHeight: 100)
                            .overlay(alignment: .topLeading) {
                                if invoiceItems.isEmpty {
                                    Text("Describe the invoice items *")
                                        .foregroundStyle(.tertiary)
                                        .padding(.top, 8)
                                        .padding(.leading, 4)
                                        .allowsHitTesting(false)
                                }
                            }
                            .onChange(of: invoiceItems) { _, _ in
                                fieldErrors["invoice_items"] = nil
                            }

                        if let errors = fieldErrors["invoice_items"], !errors.isEmpty {
                            ForEach(errors, id: \.self) { error in
                                Text(error)
                                    .font(.caption)
                                    .foregroundStyle(.red)
                            }
                        }
                    }
                }

                // Due Date
                Section("Due Date") {
                    VStack(alignment: .leading, spacing: 4) {
                        DatePicker(
                            "Due Date *",
                            selection: $dueDate,
                            displayedComponents: .date
                        )
                        .onChange(of: dueDate) { _, _ in
                            fieldErrors["due_date"] = nil
                        }

                        if let errors = fieldErrors["due_date"], !errors.isEmpty {
                            ForEach(errors, id: \.self) { error in
                                Text(error)
                                    .font(.caption)
                                    .foregroundStyle(.red)
                            }
                        }
                    }
                }
            }
            .navigationTitle("New Invoice")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") {
                        dismiss()
                    }
                    .disabled(isSaving)
                }

                ToolbarItem(placement: .confirmationAction) {
                    Button("Create") {
                        Task {
                            await createInvoice()
                        }
                    }
                    .disabled(isSaving || !isFormValid)
                    .fontWeight(.semibold)
                }
            }
            .overlay {
                if isSaving {
                    Color.black.opacity(0.1)
                        .ignoresSafeArea()
                        .overlay {
                            ProgressView("Saving...")
                                .padding()
                                .background(.regularMaterial, in: RoundedRectangle(cornerRadius: 12))
                        }
                }
            }
            .interactiveDismissDisabled(isSaving)
        }
    }

    // MARK: - Validation

    /// Whether the form has enough data to attempt submission.
    private var isFormValid: Bool {
        !companyId.trimmingCharacters(in: .whitespaces).isEmpty
            && !invoiceAmount.trimmingCharacters(in: .whitespaces).isEmpty
            && !invoiceItems.trimmingCharacters(in: .whitespaces).isEmpty
            && parsedAmount != nil
            && (parsedAmount ?? 0) > 0
    }

    /// Parses the amount string to a Double, returning nil if invalid.
    private var parsedAmount: Double? {
        Double(invoiceAmount.trimmingCharacters(in: .whitespaces))
    }

    // MARK: - Create Operation

    @MainActor
    private func createInvoice() async {
        isSaving = true
        generalError = nil
        fieldErrors = [:]

        let payload = buildPayload()

        let endpoint = Endpoint(
            method: .post,
            path: "/admin/invoices",
            body: payload
        )

        do {
            let _: InvoiceCreateResponse = try await apiClient.request(endpoint)
            await onSave?()
            dismiss()
        } catch let error as APIError {
            handleAPIError(error)
        } catch {
            generalError = "An unexpected error occurred."
        }

        isSaving = false
    }

    /// Builds the JSON payload from current form state.
    private func buildPayload() -> InvoicePayload {
        InvoicePayload(
            companyId: Int(companyId) ?? 0,
            invoiceAmount: parsedAmount ?? 0,
            invoiceItems: invoiceItems.trimmingCharacters(in: .whitespaces),
            dueDate: Self.dateFormatter.string(from: dueDate)
        )
    }

    /// Handles API errors, extracting field-level validation errors for display.
    private func handleAPIError(_ error: APIError) {
        switch error {
        case .validationFailed(let errors):
            fieldErrors = errors
            if fieldErrors.isEmpty {
                generalError = "Validation failed. Please check your input."
            }
        default:
            generalError = error.errorDescription
        }
    }

    // MARK: - Date Formatting

    private static let dateFormatter: DateFormatter = {
        let formatter = DateFormatter()
        formatter.dateFormat = "yyyy-MM-dd"
        formatter.locale = Locale(identifier: "en_US_POSIX")
        return formatter
    }()
}

// MARK: - Invoice Payload

/// Encodable payload for invoice creation requests.
struct InvoicePayload: Encodable {
    let companyId: Int
    let invoiceAmount: Double
    let invoiceItems: String
    let dueDate: String

    enum CodingKeys: String, CodingKey {
        case companyId = "company_id"
        case invoiceAmount = "invoice_amount"
        case invoiceItems = "invoice_items"
        case dueDate = "due_date"
    }
}

// MARK: - Invoice Create Response

/// Response wrapper for the invoice create endpoint.
struct InvoiceCreateResponse: Decodable {
    let data: InvoiceListItem
}

#Preview {
    InvoiceCreateView(
        apiClient: APIClient(authManager: AuthManager())
    )
}
