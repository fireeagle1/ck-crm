import SwiftUI

/// Displays a full customer record with all fields and relationship counts.
///
/// Loads the customer detail from the API on appear, provides toolbar actions
/// for editing the customer.
struct CustomerDetailView: View {
    @State private var customer: CustomerDetail?
    @State private var isLoading = true
    @State private var errorMessage: String?
    @State private var showingEditForm = false
    @State private var showingCreateTicket = false

    private let companyId: Int
    private let apiClient: APIClient

    /// Creates a customer detail view.
    /// - Parameters:
    ///   - companyId: The ID of the customer to display.
    ///   - apiClient: The API client for network requests.
    init(companyId: Int, apiClient: APIClient) {
        self.companyId = companyId
        self.apiClient = apiClient
    }

    var body: some View {
        Group {
            if isLoading && customer == nil {
                loadingView
            } else if let errorMessage, customer == nil {
                errorView(message: errorMessage)
            } else if let customer {
                customerContent(customer)
            }
        }
        .navigationTitle(customer?.companyName ?? "Customer")
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            if customer != nil {
                ToolbarItem(placement: .topBarTrailing) {
                    Menu {
                        Button {
                            showingCreateTicket = true
                        } label: {
                            Label("New Ticket", systemImage: "ticket")
                        }
                        Button {
                            showingEditForm = true
                        } label: {
                            Label("Edit", systemImage: "pencil")
                        }
                    } label: {
                        Label("Actions", systemImage: "ellipsis.circle")
                    }
                }
            }
        }
        .sheet(isPresented: $showingEditForm) {
            if let customer {
                NavigationStack {
                    CustomerFormView(
                        mode: .edit(customer),
                        apiClient: apiClient
                    ) { _ in
                        // Reload customer data after edit
                        await loadCustomer()
                    }
                }
            }
        }
        .sheet(isPresented: $showingCreateTicket) {
            TicketCreateView(apiClient: apiClient, prefilledCustomerId: companyId) {
                await loadCustomer()
            }
        }
        .task {
            await loadCustomer()
        }
    }

    // MARK: - Customer Content

    private func customerContent(_ customer: CustomerDetail) -> some View {
        List {
            // Relationship Counts — tappable to drill into filtered lists
            Section {
                NavigationLink(destination: CustomerServicesView(companyId: companyId, customerName: customer.companyName, apiClient: apiClient)) {
                    countRow(label: "Services", count: customer.servicesCount, icon: "server.rack")
                }
                NavigationLink(destination: CustomerTicketsView(companyId: companyId, apiClient: apiClient)) {
                    countRow(label: "Tickets", count: customer.ticketsCount, icon: "ticket")
                }
                NavigationLink(destination: CustomerInvoicesView(companyId: companyId, apiClient: apiClient)) {
                    countRow(label: "Invoices", count: customer.invoicesCount, icon: "doc.text")
                }
                countRow(label: "Domains", count: customer.domainsCount, icon: "globe")
            } header: {
                Text("Overview")
                    .font(CKTypography.caption)
                    .foregroundStyle(CKTheme.textSecondary)
            }

            // Contact & Address combined
            Section {
                if let name = customer.customerName, !name.isEmpty {
                    detailRow(label: "Contact", value: name)
                }
                if let phone = customer.phoneNumber, !phone.isEmpty {
                    detailRow(label: "Phone", value: phone)
                }
                if let addr = formattedAddress(customer), !addr.isEmpty {
                    VStack(alignment: .leading, spacing: 2) {
                        Text("Address")
                            .font(CKTypography.caption)
                            .foregroundStyle(CKTheme.textSecondary)
                        Text(addr)
                            .font(CKTypography.body)
                            .foregroundStyle(CKTheme.textPrimary)
                    }
                    .padding(.vertical, 2)
                }
            } header: {
                Text("Details")
                    .font(CKTypography.caption)
                    .foregroundStyle(CKTheme.textSecondary)
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .background(CKTheme.backgroundPrimary)
        .refreshable {
            await loadCustomer()
        }
    }

    // MARK: - Address Formatting

    private func formattedAddress(_ customer: CustomerDetail) -> String? {
        let parts = [
            customer.addressLine1,
            customer.addressLine2,
            customer.city,
            customer.state,
            customer.postalCode,
            customer.country
        ].compactMap { $0?.trimmingCharacters(in: .whitespaces) }.filter { !$0.isEmpty }
        return parts.isEmpty ? nil : parts.joined(separator: ", ")
    }

    // MARK: - Helper Views

    private func countRow(label: String, count: Int, icon: String) -> some View {
        HStack {
            Label(label, systemImage: icon)
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textPrimary)
            Spacer()
            Text("\(count)")
                .font(CKTypography.callout)
                .foregroundStyle(CKTheme.textSecondary)
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(label): \(count)")
    }

    private func detailRow(label: String, value: String?) -> some View {
        HStack {
            Text(label)
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
            Spacer()
            Text(value ?? "—")
                .font(CKTypography.body)
                .foregroundStyle(value != nil ? CKTheme.textPrimary : CKTheme.textTertiary)
                .multilineTextAlignment(.trailing)
        }
    }

    // MARK: - Loading State

    private var loadingView: some View {
        VStack(spacing: 16) {
            ProgressView()
                .controlSize(.large)
            Text("Loading customer...")
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(CKTheme.backgroundPrimary)
        .accessibilityLabel("Loading customer details")
    }

    // MARK: - Error State

    private func errorView(message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle")
                .font(.system(size: 48))
                .foregroundStyle(CKTheme.warning)

            Text("Unable to Load Customer")
                .font(CKTypography.headline)
                .foregroundStyle(CKTheme.textPrimary)

            Text(message)
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal)

            Button {
                Task {
                    await loadCustomer()
                }
            } label: {
                Label("Retry", systemImage: "arrow.clockwise")
                    .fontWeight(.medium)
            }
            .buttonStyle(.borderedProminent)
            .tint(CKTheme.accent)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(CKTheme.backgroundPrimary)
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Error loading customer: \(message)")
    }

    // MARK: - Network Operations

    @MainActor
    private func loadCustomer() async {
        isLoading = true
        errorMessage = nil

        do {
            let endpoint = Endpoint(path: "/admin/customers/\(companyId)")
            let response: CustomerDetailResponse = try await apiClient.request(endpoint)
            customer = response.data
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "An unexpected error occurred."
        }

        isLoading = false
    }
}

#Preview {
    NavigationStack {
        CustomerDetailView(companyId: 1, apiClient: APIClient(authManager: AuthManager()))
    }
}
