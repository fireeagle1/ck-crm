import SwiftUI

/// Customer list screen with search and infinite-scroll pagination.
///
/// Displays company name, customer name, and phone number for each record.
/// Supports live search via the `.searchable` modifier and loads additional
/// pages when the last item appears on screen.
/// Provides navigation to customer detail and a toolbar button to create new customers.
struct CustomerListView: View {
    @State private var viewModel: CustomerListViewModel
    @State private var showingCreateForm = false

    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
        _viewModel = State(initialValue: CustomerListViewModel(apiClient: apiClient))
    }

    var body: some View {
        @Bindable var viewModel = viewModel

        Group {
            if viewModel.isLoading && viewModel.customers.isEmpty {
                loadingView
            } else if let errorMessage = viewModel.errorMessage, viewModel.customers.isEmpty {
                errorView(message: errorMessage)
            } else {
                customerList
            }
        }
        .navigationTitle("Customers")
        .searchable(text: $viewModel.searchText, prompt: "Search customers")
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                Button {
                    showingCreateForm = true
                } label: {
                    Label("Add Customer", systemImage: "plus")
                }
            }
        }
        .sheet(isPresented: $showingCreateForm) {
            NavigationStack {
                CustomerFormView(
                    mode: .create,
                    apiClient: apiClient
                ) { _ in
                    // Reload list after creating a new customer
                    await viewModel.loadInitial()
                }
            }
        }
        .task {
            if viewModel.customers.isEmpty {
                await viewModel.loadInitial()
            }
        }
    }

    // MARK: - Customer List

    private var customerList: some View {
        List {
            ForEach(viewModel.customers) { customer in
                NavigationLink(value: customer) {
                    customerRow(customer)
                }
                .onAppear {
                    if customer.id == viewModel.customers.last?.id {
                        Task {
                            await viewModel.loadNextPage()
                        }
                    }
                }
            }

            if viewModel.isLoadingMore {
                loadingMoreRow
            }
        }
        .listStyle(.plain)
        .navigationDestination(for: CustomerListItem.self) { customer in
            CustomerDetailView(companyId: customer.companyId, apiClient: apiClient)
        }
        .refreshable {
            await viewModel.loadInitial()
        }
        .overlay {
            if viewModel.customers.isEmpty && !viewModel.isLoading {
                ContentUnavailableView.search(text: viewModel.searchText)
            }
        }
    }

    // MARK: - Customer Row

    private func customerRow(_ customer: CustomerListItem) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(customer.companyName)
                .font(.body)
                .fontWeight(.medium)
                .lineLimit(1)

            Text(customer.customerName)
                .font(.subheadline)
                .foregroundStyle(.secondary)
                .lineLimit(1)

            if let phone = customer.phoneNumber, !phone.isEmpty {
                Label(phone, systemImage: "phone")
                    .font(.caption)
                    .foregroundStyle(.secondary)
                    .lineLimit(1)
            }
        }
        .padding(.vertical, 2)
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(customer.companyName), \(customer.customerName)")
    }

    // MARK: - Loading More Row

    private var loadingMoreRow: some View {
        HStack {
            Spacer()
            ProgressView()
                .controlSize(.small)
            Text("Loading more...")
                .font(.caption)
                .foregroundStyle(.secondary)
            Spacer()
        }
        .listRowSeparator(.hidden)
        .accessibilityLabel("Loading more customers")
    }

    // MARK: - Loading State

    private var loadingView: some View {
        VStack(spacing: 16) {
            ProgressView()
                .controlSize(.large)
            Text("Loading customers...")
                .font(.subheadline)
                .foregroundStyle(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .accessibilityLabel("Loading customer list")
    }

    // MARK: - Error State

    private func errorView(message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle")
                .font(.system(size: 48))
                .foregroundStyle(.orange)

            Text("Unable to Load Customers")
                .font(.headline)

            Text(message)
                .font(.subheadline)
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal)

            Button {
                Task {
                    await viewModel.loadInitial()
                }
            } label: {
                Label("Retry", systemImage: "arrow.clockwise")
                    .fontWeight(.medium)
            }
            .buttonStyle(.borderedProminent)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Error loading customers: \(message)")
    }
}

#Preview {
    NavigationStack {
        CustomerListView(apiClient: APIClient(authManager: AuthManager()))
    }
}
