import SwiftUI

/// Displays a full customer record with all fields and relationship counts.
///
/// Loads the customer detail from the API on appear, provides toolbar actions
/// for editing and deleting the customer.
struct CustomerDetailView: View {
    @Environment(\.dismiss) private var dismiss

    @State private var customer: CustomerDetail?
    @State private var isLoading = true
    @State private var errorMessage: String?
    @State private var showingDeleteConfirmation = false
    @State private var isDeleting = false
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
        .confirmationDialog(
            "Delete Customer",
            isPresented: $showingDeleteConfirmation,
            titleVisibility: .visible
        ) {
            Button("Delete", role: .destructive) {
                Task {
                    await deleteCustomer()
                }
            }
            Button("Cancel", role: .cancel) {}
        } message: {
            Text("Are you sure you want to delete this customer? This action cannot be undone.")
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
            Section("Overview") {
                countRow(label: "Services", count: customer.servicesCount, icon: "server.rack")
                countRow(label: "Tickets", count: customer.ticketsCount, icon: "ticket")
                countRow(label: "Invoices", count: customer.invoicesCount, icon: "doc.text")
                countRow(label: "Domains", count: customer.domainsCount, icon: "globe")
            }

            // Quick Actions
            Section("Actions") {
                Button {
                    showingCreateTicket = true
                } label: {
                    Label("Log Ticket / Service Request", systemImage: "plus.circle")
                }
            }

            // Contact Information
            Section("Contact") {
                detailRow(label: "Company", value: customer.companyName)
                detailRow(label: "Contact Name", value: customer.customerName)
                detailRow(label: "Phone", value: customer.phoneNumber)
            }

            // Address
            Section("Address") {
                detailRow(label: "Address Line 1", value: customer.addressLine1)
                detailRow(label: "Address Line 2", value: customer.addressLine2)
                detailRow(label: "City", value: customer.city)
                detailRow(label: "State", value: customer.state)
                detailRow(label: "Postal Code", value: customer.postalCode)
                detailRow(label: "Country", value: customer.country)
            }

            // Actions
            Section {
                Button(role: .destructive) {
                    showingDeleteConfirmation = true
                } label: {
                    HStack {
                        Spacer()
                        if isDeleting {
                            ProgressView()
                                .controlSize(.small)
                                .padding(.trailing, 8)
                        }
                        Label("Delete Customer", systemImage: "trash")
                        Spacer()
                    }
                }
                .disabled(isDeleting)
            }
        }
        .refreshable {
            await loadCustomer()
        }
    }

    // MARK: - Helper Views

    private func countRow(label: String, count: Int, icon: String) -> some View {
        HStack {
            Label(label, systemImage: icon)
                .foregroundStyle(.primary)
            Spacer()
            Text("\(count)")
                .foregroundStyle(.secondary)
                .fontWeight(.medium)
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(label): \(count)")
    }

    private func detailRow(label: String, value: String?) -> some View {
        HStack {
            Text(label)
                .foregroundStyle(.secondary)
            Spacer()
            Text(value ?? "—")
                .foregroundStyle(value != nil ? .primary : .tertiary)
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
                .font(.subheadline)
                .foregroundStyle(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .accessibilityLabel("Loading customer details")
    }

    // MARK: - Error State

    private func errorView(message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle")
                .font(.system(size: 48))
                .foregroundStyle(.orange)

            Text("Unable to Load Customer")
                .font(.headline)

            Text(message)
                .font(.subheadline)
                .foregroundStyle(.secondary)
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
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
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

    @MainActor
    private func deleteCustomer() async {
        isDeleting = true

        do {
            let endpoint = Endpoint(method: .delete, path: "/admin/customers/\(companyId)")
            try await apiClient.requestVoid(endpoint)
            dismiss()
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "Failed to delete customer."
        }

        isDeleting = false
    }
}

#Preview {
    NavigationStack {
        CustomerDetailView(companyId: 1, apiClient: APIClient(authManager: AuthManager()))
    }
}
