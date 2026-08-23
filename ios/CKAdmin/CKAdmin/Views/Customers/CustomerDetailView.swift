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
        .navigationBarTitleDisplayMode(.large)
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
            // Relationship Counts
            Section {
                countRow(label: "Services", count: customer.servicesCount, icon: "server.rack")
                countRow(label: "Tickets", count: customer.ticketsCount, icon: "ticket")
                countRow(label: "Invoices", count: customer.invoicesCount, icon: "doc.text")
                countRow(label: "Domains", count: customer.domainsCount, icon: "globe")
            } header: {
                Text("Overview")
                    .font(CKTypography.caption)
                    .foregroundStyle(CKTheme.textSecondary)
            }

            // Quick Actions
            Section {
                Button {
                    showingCreateTicket = true
                } label: {
                    Label("Log Ticket / Service Request", systemImage: "plus.circle")
                        .font(CKTypography.body)
                        .foregroundStyle(CKTheme.accent)
                }
            } header: {
                Text("Actions")
                    .font(CKTypography.caption)
                    .foregroundStyle(CKTheme.textSecondary)
            }

            // Contact Information
            Section {
                detailRow(label: "Company", value: customer.companyName)
                detailRow(label: "Contact Name", value: customer.customerName)
                detailRow(label: "Phone", value: customer.phoneNumber)
            } header: {
                Text("Contact")
                    .font(CKTypography.caption)
                    .foregroundStyle(CKTheme.textSecondary)
            }

            // Address
            Section {
                detailRow(label: "Address Line 1", value: customer.addressLine1)
                detailRow(label: "Address Line 2", value: customer.addressLine2)
                detailRow(label: "City", value: customer.city)
                detailRow(label: "State", value: customer.state)
                detailRow(label: "Postal Code", value: customer.postalCode)
                detailRow(label: "Country", value: customer.country)
            } header: {
                Text("Address")
                    .font(CKTypography.caption)
                    .foregroundStyle(CKTheme.textSecondary)
            }
        }
        .scrollContentBackground(.hidden)
        .background(CKTheme.backgroundPrimary)
        .refreshable {
            await loadCustomer()
        }
    }

    // MARK: - Helper Views

    private func countRow(label: String, count: Int, icon: String) -> some View {
        CKRow {
            Label(label, systemImage: icon)
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textPrimary)
        } trailing: {
            Text("\(count)")
                .font(CKTypography.callout)
                .foregroundStyle(CKTheme.textSecondary)
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(label): \(count)")
    }

    private func detailRow(label: String, value: String?) -> some View {
        CKRow {
            Text(label)
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
        } trailing: {
            Text(value ?? "—")
                .font(CKTypography.body)
                .foregroundStyle(value != nil ? CKTheme.textPrimary : CKTheme.textTertiary)
                .multilineTextAlignment(.trailing)
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(label): \(value ?? "Not set")")
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
