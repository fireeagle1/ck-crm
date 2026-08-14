import SwiftUI

/// Form for creating a new support ticket.
///
/// Provides fields for customer ID, subject, and description.
/// Submits via POST /api/admin/tickets and dismisses on success.
/// Displays field-level validation errors returned from 422 responses.
struct TicketCreateView: View {
    @Environment(\.dismiss) private var dismiss

    // MARK: - Form Fields

    @State private var companyId: String = ""
    @State private var subject: String = ""
    @State private var description: String = ""

    // MARK: - State

    @State private var isSaving = false
    @State private var generalError: String?
    @State private var fieldErrors: [String: [String]] = [:]

    // MARK: - Properties

    private let apiClient: APIClient
    private let onCreated: (() async -> Void)?

    /// Creates a ticket creation form.
    /// - Parameters:
    ///   - apiClient: The API client for network requests.
    ///   - onCreated: Optional callback invoked after a successful creation.
    init(
        apiClient: APIClient,
        onCreated: (() async -> Void)? = nil
    ) {
        self.apiClient = apiClient
        self.onCreated = onCreated
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
                    formField(
                        label: "Company ID",
                        text: $companyId,
                        fieldKey: "company_id",
                        isRequired: true,
                        keyboardType: .numberPad
                    )
                }

                // Ticket Details
                Section("Ticket Details") {
                    formField(
                        label: "Subject",
                        text: $subject,
                        fieldKey: "subject",
                        isRequired: true
                    )

                    VStack(alignment: .leading, spacing: 4) {
                        Text("Description *")
                            .font(.caption)
                            .foregroundStyle(.secondary)

                        TextEditor(text: $description)
                            .frame(minHeight: 120)
                            .overlay(
                                RoundedRectangle(cornerRadius: 8)
                                    .stroke(Color(.separator), lineWidth: 0.5)
                            )
                            .onChange(of: description) { _, _ in
                                fieldErrors["description"] = nil
                            }

                        if let errors = fieldErrors["description"], !errors.isEmpty {
                            ForEach(errors, id: \.self) { error in
                                Text(error)
                                    .font(.caption)
                                    .foregroundStyle(.red)
                            }
                        }
                    }
                }
            }
            .navigationTitle("New Ticket")
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
                            await createTicket()
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
                            ProgressView("Creating...")
                                .padding()
                                .background(.regularMaterial, in: RoundedRectangle(cornerRadius: 12))
                        }
                }
            }
            .interactiveDismissDisabled(isSaving)
        }
    }

    // MARK: - Validation

    /// Whether the form has all required fields filled.
    private var isFormValid: Bool {
        !companyId.trimmingCharacters(in: .whitespaces).isEmpty
            && !subject.trimmingCharacters(in: .whitespaces).isEmpty
            && !description.trimmingCharacters(in: .whitespaces).isEmpty
    }

    // MARK: - Form Field

    /// A form text field with an optional validation error message below.
    private func formField(
        label: String,
        text: Binding<String>,
        fieldKey: String,
        isRequired: Bool = false,
        keyboardType: UIKeyboardType = .default
    ) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            TextField(isRequired ? "\(label) *" : label, text: text)
                .keyboardType(keyboardType)
                .autocorrectionDisabled(fieldKey == "company_id")
                .onChange(of: text.wrappedValue) { _, _ in
                    fieldErrors[fieldKey] = nil
                }

            if let errors = fieldErrors[fieldKey], !errors.isEmpty {
                ForEach(errors, id: \.self) { error in
                    Text(error)
                        .font(.caption)
                        .foregroundStyle(.red)
                }
            }
        }
    }

    // MARK: - Create Operation

    @MainActor
    private func createTicket() async {
        isSaving = true
        generalError = nil
        fieldErrors = [:]

        let payload = TicketPayload(
            companyId: Int(companyId) ?? 0,
            subject: subject.trimmingCharacters(in: .whitespaces),
            description: description.trimmingCharacters(in: .whitespaces)
        )

        let endpoint = Endpoint(
            method: .post,
            path: "/admin/tickets",
            body: payload
        )

        do {
            let _: TicketCreateResponse = try await apiClient.request(endpoint)
            await onCreated?()
            dismiss()
        } catch let error as APIError {
            handleAPIError(error)
        } catch {
            generalError = "An unexpected error occurred."
        }

        isSaving = false
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
}

// MARK: - Ticket Payload

/// Encodable payload for ticket creation requests.
struct TicketPayload: Encodable {
    let companyId: Int
    let subject: String
    let description: String

    enum CodingKeys: String, CodingKey {
        case companyId = "company_id"
        case subject
        case description
    }
}

// MARK: - Ticket Create Response

/// Wrapper for the create ticket API response.
struct TicketCreateResponse: Decodable {
    let data: TicketCreatedItem
}

/// The ticket data returned after creation.
struct TicketCreatedItem: Decodable {
    let ticketId: Int?
    let subject: String?
    let status: String?
    let companyId: Int?
}

#Preview {
    TicketCreateView(
        apiClient: APIClient(authManager: AuthManager())
    )
}
