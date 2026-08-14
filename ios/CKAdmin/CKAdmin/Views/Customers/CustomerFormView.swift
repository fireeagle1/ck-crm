import SwiftUI

/// Reusable form for creating or editing a customer.
///
/// Displays fields for company name, contact name, phone, and address.
/// In edit mode, fields are pre-populated from the existing customer.
/// Displays field-level validation errors returned from 422 responses.
struct CustomerFormView: View {
    @Environment(\.dismiss) private var dismiss

    /// The form mode — either creating a new customer or editing an existing one.
    enum Mode {
        case create
        case edit(CustomerDetail)

        var title: String {
            switch self {
            case .create: return "New Customer"
            case .edit: return "Edit Customer"
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

    @State private var companyName: String = ""
    @State private var customerName: String = ""
    @State private var phoneNumber: String = ""
    @State private var addressLine1: String = ""
    @State private var addressLine2: String = ""
    @State private var city: String = ""
    @State private var state: String = ""
    @State private var postalCode: String = ""
    @State private var country: String = ""

    // MARK: - State

    @State private var isSaving = false
    @State private var generalError: String?
    @State private var fieldErrors: [String: [String]] = [:]

    // MARK: - Properties

    private let mode: Mode
    private let apiClient: APIClient
    private let onSave: ((CustomerDetail) async -> Void)?

    /// Creates a customer form.
    /// - Parameters:
    ///   - mode: Whether this is a create or edit form.
    ///   - apiClient: The API client for network requests.
    ///   - onSave: Optional callback invoked after a successful save with the returned customer.
    init(
        mode: Mode,
        apiClient: APIClient,
        onSave: ((CustomerDetail) async -> Void)? = nil
    ) {
        self.mode = mode
        self.apiClient = apiClient
        self.onSave = onSave

        // Pre-populate fields in edit mode
        if case .edit(let customer) = mode {
            _companyName = State(initialValue: customer.companyName)
            _customerName = State(initialValue: customer.customerName ?? "")
            _phoneNumber = State(initialValue: customer.phoneNumber ?? "")
            _addressLine1 = State(initialValue: customer.addressLine1 ?? "")
            _addressLine2 = State(initialValue: customer.addressLine2 ?? "")
            _city = State(initialValue: customer.city ?? "")
            _state = State(initialValue: customer.state ?? "")
            _postalCode = State(initialValue: customer.postalCode ?? "")
            _country = State(initialValue: customer.country ?? "")
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

            // Company Information
            Section("Company") {
                formField(
                    label: "Company Name",
                    text: $companyName,
                    fieldKey: "company_name",
                    isRequired: true
                )
            }

            // Contact Information
            Section("Contact") {
                formField(
                    label: "Contact Name",
                    text: $customerName,
                    fieldKey: "customer_name"
                )

                formField(
                    label: "Phone Number",
                    text: $phoneNumber,
                    fieldKey: "phone_number",
                    keyboardType: .phonePad
                )
            }

            // Address
            Section("Address") {
                formField(
                    label: "Address Line 1",
                    text: $addressLine1,
                    fieldKey: "address_line1"
                )

                formField(
                    label: "Address Line 2",
                    text: $addressLine2,
                    fieldKey: "address_line2"
                )

                formField(
                    label: "City",
                    text: $city,
                    fieldKey: "city"
                )

                formField(
                    label: "State",
                    text: $state,
                    fieldKey: "state"
                )

                formField(
                    label: "Postal Code",
                    text: $postalCode,
                    fieldKey: "postal_code",
                    keyboardType: .numbersAndPunctuation
                )

                formField(
                    label: "Country",
                    text: $country,
                    fieldKey: "country"
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
                        await saveCustomer()
                    }
                }
                .disabled(isSaving || companyName.trimmingCharacters(in: .whitespaces).isEmpty)
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
                .textContentType(contentType(for: fieldKey))
                .autocorrectionDisabled(fieldKey == "phone_number")
                .onChange(of: text.wrappedValue) { _, _ in
                    // Clear field error on edit
                    fieldErrors[fieldKey] = nil
                }

            // Display validation error
            if let errors = fieldErrors[fieldKey], !errors.isEmpty {
                ForEach(errors, id: \.self) { error in
                    Text(error)
                        .font(.caption)
                        .foregroundStyle(.red)
                }
            }
        }
    }

    /// Maps field keys to appropriate text content types for autofill support.
    private func contentType(for fieldKey: String) -> UITextContentType? {
        switch fieldKey {
        case "phone_number": return .telephoneNumber
        case "address_line1": return .streetAddressLine1
        case "address_line2": return .streetAddressLine2
        case "city": return .addressCity
        case "state": return .addressState
        case "postal_code": return .postalCode
        case "country": return .countryName
        default: return nil
        }
    }

    // MARK: - Save Operation

    @MainActor
    private func saveCustomer() async {
        isSaving = true
        generalError = nil
        fieldErrors = [:]

        let payload = buildPayload()

        do {
            let response: CustomerMutateResponse

            switch mode {
            case .create:
                let endpoint = Endpoint(
                    method: .post,
                    path: "/admin/customers",
                    body: payload
                )
                response = try await apiClient.request(endpoint)

            case .edit(let customer):
                let endpoint = Endpoint(
                    method: .put,
                    path: "/admin/customers/\(customer.companyId)",
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
    private func buildPayload() -> CustomerPayload {
        CustomerPayload(
            companyName: companyName.trimmingCharacters(in: .whitespaces),
            customerName: customerName.isEmpty ? nil : customerName.trimmingCharacters(in: .whitespaces),
            phoneNumber: phoneNumber.isEmpty ? nil : phoneNumber.trimmingCharacters(in: .whitespaces),
            addressLine1: addressLine1.isEmpty ? nil : addressLine1.trimmingCharacters(in: .whitespaces),
            addressLine2: addressLine2.isEmpty ? nil : addressLine2.trimmingCharacters(in: .whitespaces),
            city: city.isEmpty ? nil : city.trimmingCharacters(in: .whitespaces),
            state: state.isEmpty ? nil : state.trimmingCharacters(in: .whitespaces),
            postalCode: postalCode.isEmpty ? nil : postalCode.trimmingCharacters(in: .whitespaces),
            country: country.isEmpty ? nil : country.trimmingCharacters(in: .whitespaces)
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

// MARK: - Customer Payload

/// Encodable payload for customer create/update requests.
struct CustomerPayload: Encodable {
    let companyName: String
    let customerName: String?
    let phoneNumber: String?
    let addressLine1: String?
    let addressLine2: String?
    let city: String?
    let state: String?
    let postalCode: String?
    let country: String?

    enum CodingKeys: String, CodingKey {
        case companyName = "company_name"
        case customerName = "customer_name"
        case phoneNumber = "phone_number"
        case addressLine1 = "address_line1"
        case addressLine2 = "address_line2"
        case city
        case state
        case postalCode = "postal_code"
        case country
    }
}

#Preview("Create") {
    NavigationStack {
        CustomerFormView(
            mode: .create,
            apiClient: APIClient(authManager: AuthManager())
        )
    }
}
