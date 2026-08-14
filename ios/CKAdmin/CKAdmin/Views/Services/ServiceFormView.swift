import SwiftUI

/// Reusable form for creating or editing a service.
///
/// Displays fields for service name, type, domain, status, billing, and customer.
/// In edit mode, fields are pre-populated from the existing service.
/// Displays field-level validation errors returned from 422 responses.
struct ServiceFormView: View {
    @Environment(\.dismiss) private var dismiss

    /// The form mode — either creating a new service or editing an existing one.
    enum Mode {
        case create
        case edit(ServiceDetail)

        var title: String {
            switch self {
            case .create: return "New Service"
            case .edit: return "Edit Service"
            }
        }

        var submitLabel: String {
            switch self {
            case .create: return "Create"
            case .edit: return "Save"
            }
        }
    }

    // MARK: - Form Fields

    @State private var serviceShort: String = ""
    @State private var serviceType: String = ""
    @State private var domainName: String = ""
    @State private var status: String = "Active"
    @State private var serviceMonthlyCharge: String = ""
    @State private var servicePaymentFrequency: String = "Monthly"
    @State private var companyId: String = ""
    @State private var startDate: String = ""
    @State private var endDate: String = ""

    // MARK: - State

    @State private var isSaving = false
    @State private var generalError: String?
    @State private var fieldErrors: [String: [String]] = [:]

    // MARK: - Properties

    private let mode: Mode
    private let apiClient: APIClient
    private let onSave: ((ServiceDetail) async -> Void)?

    /// Available status options for the picker.
    private let statusOptions = ["Active", "Suspended", "Cancelled"]

    /// Available payment frequency options for the picker.
    private let frequencyOptions = ["Weekly", "Monthly", "Quarterly", "Biannually", "Annually", "Biennially"]

    /// Creates a service form.
    /// - Parameters:
    ///   - mode: Whether this is a create or edit form.
    ///   - apiClient: The API client for network requests.
    ///   - onSave: Optional callback invoked after a successful save with the returned service.
    init(
        mode: Mode,
        apiClient: APIClient,
        onSave: ((ServiceDetail) async -> Void)? = nil
    ) {
        self.mode = mode
        self.apiClient = apiClient
        self.onSave = onSave

        // Pre-populate fields in edit mode
        if case .edit(let service) = mode {
            _serviceShort = State(initialValue: service.serviceShort)
            _serviceType = State(initialValue: service.serviceType ?? "")
            _domainName = State(initialValue: service.domainName ?? "")
            _status = State(initialValue: service.status)
            _serviceMonthlyCharge = State(initialValue: service.serviceMonthlyCharge.map { String(format: "%.2f", $0) } ?? "")
            _servicePaymentFrequency = State(initialValue: service.servicePaymentFrequency ?? "Monthly")
            _companyId = State(initialValue: "\(service.companyId)")
            _startDate = State(initialValue: service.startDate ?? "")
            _endDate = State(initialValue: service.endDate ?? "")
        }
    }

    var body: some View {
        Form {
            // General error banner
            if let generalError {
                Section {
                    Label(generalError, systemImage: "exclamationmark.triangle")
                        .foregroundStyle(.red)
                        .font(.subheadline)
                }
            }

            // Service Information
            Section("Service") {
                formField(
                    label: "Service Name",
                    text: $serviceShort,
                    fieldKey: "service_short",
                    isRequired: true
                )

                formField(
                    label: "Service Type",
                    text: $serviceType,
                    fieldKey: "service_type"
                )

                formField(
                    label: "Domain Name",
                    text: $domainName,
                    fieldKey: "domain_name",
                    keyboardType: .URL
                )
            }

            // Status
            Section("Status") {
                VStack(alignment: .leading, spacing: 4) {
                    Picker("Status *", selection: $status) {
                        ForEach(statusOptions, id: \.self) { option in
                            Text(option).tag(option)
                        }
                    }
                    .onChange(of: status) { _, _ in
                        fieldErrors["status"] = nil
                    }

                    if let errors = fieldErrors["status"], !errors.isEmpty {
                        ForEach(errors, id: \.self) { error in
                            Text(error)
                                .font(.caption)
                                .foregroundStyle(.red)
                        }
                    }
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

            // Billing
            Section("Billing") {
                formField(
                    label: "Monthly Charge",
                    text: $serviceMonthlyCharge,
                    fieldKey: "service_monthly_charge",
                    keyboardType: .decimalPad
                )

                VStack(alignment: .leading, spacing: 4) {
                    Picker("Payment Frequency", selection: $servicePaymentFrequency) {
                        ForEach(frequencyOptions, id: \.self) { option in
                            Text(option).tag(option)
                        }
                    }
                    .onChange(of: servicePaymentFrequency) { _, _ in
                        fieldErrors["service_payment_frequency"] = nil
                    }

                    if let errors = fieldErrors["service_payment_frequency"], !errors.isEmpty {
                        ForEach(errors, id: \.self) { error in
                            Text(error)
                                .font(.caption)
                                .foregroundStyle(.red)
                        }
                    }
                }
            }

            // Dates
            Section("Dates") {
                formField(
                    label: "Start Date (YYYY-MM-DD)",
                    text: $startDate,
                    fieldKey: "start_date"
                )

                formField(
                    label: "End Date (YYYY-MM-DD)",
                    text: $endDate,
                    fieldKey: "end_date"
                )
            }
        }
        .navigationTitle(mode.title)
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .cancellationAction) {
                Button("Cancel") {
                    dismiss()
                }
                .disabled(isSaving)
            }

            ToolbarItem(placement: .confirmationAction) {
                Button(mode.submitLabel) {
                    Task {
                        await saveService()
                    }
                }
                .disabled(isSaving || serviceShort.trimmingCharacters(in: .whitespaces).isEmpty || companyId.isEmpty)
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
                .autocorrectionDisabled()
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

    // MARK: - Save Operation

    @MainActor
    private func saveService() async {
        isSaving = true
        generalError = nil
        fieldErrors = [:]

        let payload = buildPayload()

        do {
            let response: ServiceMutateResponse

            switch mode {
            case .create:
                let endpoint = Endpoint(
                    method: .post,
                    path: "/admin/services",
                    body: payload
                )
                response = try await apiClient.request(endpoint)

            case .edit(let service):
                let endpoint = Endpoint(
                    method: .put,
                    path: "/admin/services/\(service.serviceId)",
                    body: payload
                )
                response = try await apiClient.request(endpoint)
            }

            await onSave?(response.data)
            dismiss()

        } catch let error as APIError {
            handleAPIError(error)
        } catch {
            generalError = "An unexpected error occurred."
        }

        isSaving = false
    }

    /// Builds the JSON payload from current form state.
    private func buildPayload() -> ServicePayload {
        ServicePayload(
            companyId: Int(companyId) ?? 0,
            serviceShort: serviceShort.trimmingCharacters(in: .whitespaces),
            serviceType: serviceType.isEmpty ? nil : serviceType.trimmingCharacters(in: .whitespaces),
            domainName: domainName.isEmpty ? nil : domainName.trimmingCharacters(in: .whitespaces),
            status: status,
            serviceMonthlyCharge: Double(serviceMonthlyCharge),
            servicePaymentFrequency: servicePaymentFrequency,
            startDate: startDate.isEmpty ? nil : startDate.trimmingCharacters(in: .whitespaces),
            endDate: endDate.isEmpty ? nil : endDate.trimmingCharacters(in: .whitespaces)
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
}

// MARK: - Service Payload

/// Encodable payload for service create/update requests.
struct ServicePayload: Encodable {
    let companyId: Int
    let serviceShort: String
    let serviceType: String?
    let domainName: String?
    let status: String
    let serviceMonthlyCharge: Double?
    let servicePaymentFrequency: String?
    let startDate: String?
    let endDate: String?

    enum CodingKeys: String, CodingKey {
        case companyId = "company_id"
        case serviceShort = "service_short"
        case serviceType = "service_type"
        case domainName = "domain_name"
        case status
        case serviceMonthlyCharge = "service_monthly_charge"
        case servicePaymentFrequency = "service_payment_frequency"
        case startDate = "start_date"
        case endDate = "end_date"
    }
}

#Preview("Create") {
    NavigationStack {
        ServiceFormView(
            mode: .create,
            apiClient: APIClient(authManager: AuthManager())
        )
    }
}
