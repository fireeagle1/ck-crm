import SwiftUI

/// Full-featured ticket creation form matching the web app.
/// Supports customer selection, asset/service linking, email notification toggle, etc.
struct TicketCreateView: View {
    @Environment(\.dismiss) private var dismiss

    // MARK: - Form Fields
    @State private var companyId: String = ""
    @State private var subject: String = ""
    @State private var description: String = ""
    @State private var ticketType: String = "Incident"
    @State private var priority: String = "Normal"
    @State private var requestCategory: String = ""
    @State private var selectedAssetId: Int?
    @State private var selectedServiceId: Int?
    @State private var selectedUserId: Int?
    @State private var notifyCustomer: Bool = true

    // MARK: - Context Data (loaded when customer is selected)
    @State private var contextAssets: [ContextAsset] = []
    @State private var contextServices: [ContextService] = []
    @State private var contextUsers: [ContextUser] = []
    @State private var isLoadingContext = false

    // MARK: - State
    @State private var isSaving = false
    @State private var generalError: String?
    @State private var fieldErrors: [String: [String]] = [:]

    private let apiClient: APIClient
    private let onCreated: (() async -> Void)?
    private let prefilledCustomerId: Int?

    init(apiClient: APIClient, prefilledCustomerId: Int? = nil, onCreated: (() async -> Void)? = nil) {
        self.apiClient = apiClient
        self.prefilledCustomerId = prefilledCustomerId
        self.onCreated = onCreated
        if let id = prefilledCustomerId {
            _companyId = State(initialValue: String(id))
        }
    }

    private let ticketTypes = ["Incident", "Service Request"]
    private let priorities = ["Low", "Normal", "High", "Critical"]

    var body: some View {
        NavigationStack {
            Form {
                if let generalError {
                    Section {
                        Label(generalError, systemImage: "exclamationmark.triangle")
                            .foregroundStyle(.red)
                            .font(.subheadline)
                    }
                }

                // Customer
                Section("Customer") {
                    formField(label: "Company ID", text: $companyId, fieldKey: "company_id", isRequired: true, keyboardType: .numberPad)
                        .onChange(of: companyId) { _, newValue in
                            if let id = Int(newValue), id > 0 {
                                loadCustomerContext(customerId: id)
                            } else {
                                contextAssets = []
                                contextServices = []
                                contextUsers = []
                            }
                        }

                    if isLoadingContext {
                        HStack {
                            ProgressView().controlSize(.small)
                            Text("Loading customer data...")
                                .font(.caption)
                                .foregroundStyle(.secondary)
                        }
                    }
                }

                // Ticket Details
                Section("Ticket Details") {
                    formField(label: "Subject", text: $subject, fieldKey: "subject", isRequired: true)

                    VStack(alignment: .leading, spacing: 4) {
                        Text("Description *")
                            .font(.caption)
                            .foregroundStyle(.secondary)
                        TextEditor(text: $description)
                            .frame(minHeight: 100)
                            .onChange(of: description) { _, _ in fieldErrors["description"] = nil }
                        if let errors = fieldErrors["description"], !errors.isEmpty {
                            ForEach(errors, id: \.self) { Text($0).font(.caption).foregroundStyle(.red) }
                        }
                    }
                }

                // Classification
                Section("Classification") {
                    Picker("Type *", selection: $ticketType) {
                        ForEach(ticketTypes, id: \.self) { Text($0) }
                    }

                    Picker("Priority *", selection: $priority) {
                        ForEach(priorities, id: \.self) { Text($0) }
                    }

                    formField(label: "Category", text: $requestCategory, fieldKey: "request_category")
                }

                // Linked Items (only show if context loaded)
                if !contextAssets.isEmpty || !contextServices.isEmpty || !contextUsers.isEmpty {
                    Section("Linked Items") {
                        if !contextAssets.isEmpty {
                            Picker("Asset", selection: $selectedAssetId) {
                                Text("None").tag(nil as Int?)
                                ForEach(contextAssets) { asset in
                                    Text(asset.deviceName).tag(asset.deviceId as Int?)
                                }
                            }
                        }

                        if !contextServices.isEmpty {
                            Picker("Service", selection: $selectedServiceId) {
                                Text("None").tag(nil as Int?)
                                ForEach(contextServices) { service in
                                    Text(service.serviceShort).tag(service.serviceId as Int?)
                                }
                            }
                        }

                        if !contextUsers.isEmpty {
                            Picker("Assign To", selection: $selectedUserId) {
                                Text("Unassigned").tag(nil as Int?)
                                ForEach(contextUsers) { user in
                                    Text(user.name).tag(user.id as Int?)
                                }
                            }
                        }
                    }
                }

                // Notification
                Section("Notification") {
                    Toggle("Email customer", isOn: $notifyCustomer)
                }
            }
            .navigationTitle("New Ticket")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { dismiss() }.disabled(isSaving)
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Create") { Task { await createTicket() } }
                        .disabled(isSaving || !isFormValid)
                        .fontWeight(.semibold)
                }
            }
            .overlay {
                if isSaving {
                    Color.black.opacity(0.1).ignoresSafeArea()
                        .overlay {
                            ProgressView("Creating...")
                                .padding()
                                .background(.regularMaterial, in: RoundedRectangle(cornerRadius: 12))
                        }
                }
            }
            .interactiveDismissDisabled(isSaving)
            .onAppear {
                if let id = prefilledCustomerId {
                    loadCustomerContext(customerId: id)
                }
            }
        }
    }

    private var isFormValid: Bool {
        !companyId.trimmingCharacters(in: .whitespaces).isEmpty
            && !subject.trimmingCharacters(in: .whitespaces).isEmpty
            && !description.trimmingCharacters(in: .whitespaces).isEmpty
    }

    private func formField(label: String, text: Binding<String>, fieldKey: String, isRequired: Bool = false, keyboardType: UIKeyboardType = .default) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            TextField(isRequired ? "\(label) *" : label, text: text)
                .keyboardType(keyboardType)
                .autocorrectionDisabled(fieldKey == "company_id")
                .onChange(of: text.wrappedValue) { _, _ in fieldErrors[fieldKey] = nil }
            if let errors = fieldErrors[fieldKey], !errors.isEmpty {
                ForEach(errors, id: \.self) { Text($0).font(.caption).foregroundStyle(.red) }
            }
        }
    }

    // MARK: - Load Customer Context

    private func loadCustomerContext(customerId: Int) {
        isLoadingContext = true
        contextAssets = []
        contextServices = []
        contextUsers = []
        selectedAssetId = nil
        selectedServiceId = nil
        selectedUserId = nil

        Task { @MainActor in
            let endpoint = Endpoint(path: "/admin/ticket-context", queryItems: ["customer_id": String(customerId)])
            do {
                let response: TicketContextResponse = try await apiClient.request(endpoint)
                contextAssets = response.assets
                contextServices = response.services
                contextUsers = response.users
            } catch {
                // Non-fatal — just won't show pickers
            }
            isLoadingContext = false
        }
    }

    // MARK: - Create

    @MainActor
    private func createTicket() async {
        isSaving = true
        generalError = nil
        fieldErrors = [:]

        let payload = TicketCreatePayload(
            companyId: Int(companyId) ?? 0,
            subject: subject.trimmingCharacters(in: .whitespaces),
            description: description.trimmingCharacters(in: .whitespaces),
            ticketType: ticketType,
            priority: priority,
            requestCategory: requestCategory.isEmpty ? nil : requestCategory,
            assetId: selectedAssetId,
            serviceId: selectedServiceId,
            userId: selectedUserId,
            notifyCustomer: notifyCustomer
        )

        let endpoint = Endpoint(method: .post, path: "/admin/tickets", body: payload)

        do {
            let _: TicketCreateResponse = try await apiClient.request(endpoint)
            await onCreated?()
            dismiss()
        } catch let error as APIError {
            switch error {
            case .validationFailed(let errors):
                fieldErrors = errors
                if fieldErrors.isEmpty { generalError = "Validation failed." }
            default:
                generalError = error.errorDescription
            }
        } catch {
            generalError = "An unexpected error occurred."
        }

        isSaving = false
    }
}

// MARK: - Payload

struct TicketCreatePayload: Encodable {
    let companyId: Int
    let subject: String
    let description: String
    let ticketType: String
    let priority: String
    let requestCategory: String?
    let assetId: Int?
    let serviceId: Int?
    let userId: Int?
    let notifyCustomer: Bool

    enum CodingKeys: String, CodingKey {
        case companyId = "company_id"
        case subject
        case description
        case ticketType = "ticket_type"
        case priority
        case requestCategory = "request_category"
        case assetId = "asset_id"
        case serviceId = "service_id"
        case userId = "user_id"
        case notifyCustomer = "notify_customer"
    }
}

struct TicketCreateResponse: Decodable {
    let data: TicketCreatedItem
}

struct TicketCreatedItem: Decodable {
    let ticketId: Int?
    let subject: String?
    let status: String?
}

#Preview {
    TicketCreateView(apiClient: APIClient(authManager: AuthManager()))
}
