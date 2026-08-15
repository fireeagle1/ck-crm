import SwiftUI

/// Full-featured ticket creation form matching the web app.
/// Supports customer selection via searchable picker, asset/service linking, email notification toggle, etc.
struct TicketCreateView: View {
    @Environment(\.dismiss) private var dismiss

    // MARK: - Form Fields
    @State private var selectedCustomerId: Int?
    @State private var subject: String = ""
    @State private var description: String = ""
    @State private var ticketType: String = "Incident"
    @State private var priority: String = "Normal"
    @State private var requestCategory: String = ""
    @State private var selectedAssetId: Int?
    @State private var selectedServiceId: Int?
    @State private var selectedUserId: Int?
    @State private var notifyCustomer: Bool = true

    // MARK: - Customer List
    @State private var customers: [CustomerListItem] = []
    @State private var isLoadingCustomers = false
    @State private var customerSearchText: String = ""

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
            _selectedCustomerId = State(initialValue: id)
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
                    NavigationLink {
                        CustomerPickerView(
                            customers: customers,
                            selectedId: $selectedCustomerId,
                            apiClient: apiClient
                        )
                    } label: {
                        HStack {
                            Text("Customer")
                                .foregroundStyle(.primary)
                            Spacer()
                            if let id = selectedCustomerId,
                               let customer = customers.first(where: { $0.companyId == id }) {
                                Text(customer.companyName)
                                    .foregroundStyle(.secondary)
                            } else if selectedCustomerId != nil {
                                Text("Loading...")
                                    .foregroundStyle(.secondary)
                            } else {
                                Text("Select *")
                                    .foregroundStyle(.tertiary)
                            }
                        }
                    }
                    if let errors = fieldErrors["company_id"], !errors.isEmpty {
                        ForEach(errors, id: \.self) { Text($0).font(.caption).foregroundStyle(.red) }
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

                    if ticketType == "Service Request" {
                        Picker("Category", selection: $requestCategory) {
                            Text("None").tag("")
                            ForEach(["Website Change", "Email Setup", "New Feature", "Hardware", "Software", "Network", "Other"], id: \.self) {
                                Text($0).tag($0)
                            }
                        }
                    }
                }

                // Linked Items (only show if context loaded)
                if !contextAssets.isEmpty || !contextServices.isEmpty || !contextUsers.isEmpty {
                    Section("Linked Items") {
                        if !contextAssets.isEmpty {
                            Picker("Asset", selection: $selectedAssetId) {
                                Text("None").tag(nil as Int?)
                                ForEach(contextAssets) { asset in
                                    Text("\(asset.deviceName)\(asset.deviceType.map { " (\($0))" } ?? "")").tag(asset.deviceId as Int?)
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
                                Text("Auto (primary contact)").tag(nil as Int?)
                                ForEach(contextUsers) { user in
                                    Text("\(user.name) (\(user.email))").tag(user.id as Int?)
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
            .onChange(of: selectedCustomerId) { _, newValue in
                if let id = newValue {
                    loadCustomerContext(customerId: id)
                } else {
                    contextAssets = []
                    contextServices = []
                    contextUsers = []
                    selectedAssetId = nil
                    selectedServiceId = nil
                    selectedUserId = nil
                }
            }
            .task {
                await loadCustomers()
                if let id = prefilledCustomerId {
                    loadCustomerContext(customerId: id)
                }
            }
        }
    }

    private var isFormValid: Bool {
        selectedCustomerId != nil
            && !subject.trimmingCharacters(in: .whitespaces).isEmpty
            && !description.trimmingCharacters(in: .whitespaces).isEmpty
    }

    private func formField(label: String, text: Binding<String>, fieldKey: String, isRequired: Bool = false) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            TextField(isRequired ? "\(label) *" : label, text: text)
                .onChange(of: text.wrappedValue) { _, _ in fieldErrors[fieldKey] = nil }
            if let errors = fieldErrors[fieldKey], !errors.isEmpty {
                ForEach(errors, id: \.self) { Text($0).font(.caption).foregroundStyle(.red) }
            }
        }
    }

    // MARK: - Load Customers

    @MainActor
    private func loadCustomers() async {
        isLoadingCustomers = true
        do {
            let endpoint = Endpoint(path: "/admin/customers", queryItems: ["per_page": "100"])
            let response: PaginatedResponse<CustomerListItem> = try await apiClient.request(endpoint)
            customers = response.data
        } catch {
            // Non-fatal
        }
        isLoadingCustomers = false
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
            companyId: selectedCustomerId ?? 0,
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

// MARK: - Customer Picker View

/// A searchable list for selecting a customer.
struct CustomerPickerView: View {
    let customers: [CustomerListItem]
    @Binding var selectedId: Int?
    let apiClient: APIClient

    @Environment(\.dismiss) private var dismiss
    @State private var searchText = ""
    @State private var searchResults: [CustomerListItem] = []
    @State private var isSearching = false

    private var displayedCustomers: [CustomerListItem] {
        if searchText.isEmpty {
            return customers
        }
        // Filter locally first
        let local = customers.filter {
            $0.companyName.localizedCaseInsensitiveContains(searchText) ||
            $0.customerName.localizedCaseInsensitiveContains(searchText)
        }
        // If we have remote results, merge them
        if !searchResults.isEmpty {
            let localIds = Set(local.map(\.companyId))
            let extra = searchResults.filter { !localIds.contains($0.companyId) }
            return local + extra
        }
        return local
    }

    var body: some View {
        List {
            ForEach(displayedCustomers) { customer in
                Button {
                    selectedId = customer.companyId
                    dismiss()
                } label: {
                    HStack {
                        VStack(alignment: .leading, spacing: 2) {
                            Text(customer.companyName)
                                .font(.body)
                                .foregroundStyle(.primary)
                            if !customer.customerName.isEmpty {
                                Text(customer.customerName)
                                    .font(.caption)
                                    .foregroundStyle(.secondary)
                            }
                        }
                        Spacer()
                        if selectedId == customer.companyId {
                            Image(systemName: "checkmark")
                                .foregroundStyle(.blue)
                                .fontWeight(.semibold)
                        }
                    }
                }
            }

            if displayedCustomers.isEmpty && !searchText.isEmpty && !isSearching {
                ContentUnavailableView.search(text: searchText)
            }
        }
        .navigationTitle("Select Customer")
        .navigationBarTitleDisplayMode(.inline)
        .searchable(text: $searchText, prompt: "Search customers")
        .onChange(of: searchText) { _, newValue in
            guard !newValue.isEmpty else {
                searchResults = []
                return
            }
            // Debounce remote search
            Task { @MainActor in
                try? await Task.sleep(for: .milliseconds(300))
                guard !Task.isCancelled, searchText == newValue else { return }
                await searchRemote(query: newValue)
            }
        }
    }

    @MainActor
    private func searchRemote(query: String) async {
        isSearching = true
        do {
            let endpoint = Endpoint(path: "/admin/customers", queryItems: ["search": query, "per_page": "20"])
            let response: PaginatedResponse<CustomerListItem> = try await apiClient.request(endpoint)
            searchResults = response.data
        } catch {
            // Non-fatal
        }
        isSearching = false
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
