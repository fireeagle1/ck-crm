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
                    CKRow {
                        VStack(alignment: .leading, spacing: 4) {
                            Text(customer.companyName)
                                .font(CKTypography.headline)
                                .foregroundStyle(CKTheme.textPrimary)
                                .lineLimit(1)

                            Text(customer.customerName)
                                .font(CKTypography.body)
                                .foregroundStyle(CKTheme.textSecondary)
                                .lineLimit(1)

                            if let phone = customer.phoneNumber, !phone.isEmpty {
                                Label(phone, systemImage: "phone")
                                    .font(CKTypography.caption)
                                    .foregroundStyle(CKTheme.textTertiary)
                                    .lineLimit(1)
                            }
                        }
                    } trailing: {
                        Image(systemName: "chevron.right")
                            .font(CKTypography.caption)
                            .foregroundStyle(CKTheme.textTertiary)
                    }
                    .accessibilityElement(children: .combine)
                    .accessibilityLabel("\(customer.companyName), \(customer.customerName)")
                }
                .listRowInsets(EdgeInsets())
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
        .scrollContentBackground(.hidden)
        .background(CKTheme.backgroundPrimary)
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

    // MARK: - Loading More Row

    private var loadingMoreRow: some View {
        HStack {
            Spacer()
            ProgressView()
                .controlSize(.small)
            Text("Loading more...")
                .font(CKTypography.caption)
                .foregroundStyle(CKTheme.textSecondary)
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
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(CKTheme.backgroundPrimary)
        .accessibilityLabel("Loading customer list")
    }

    // MARK: - Error State

    private func errorView(message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle")
                .font(.system(size: 48))
                .foregroundStyle(CKTheme.warning)

            Text("Unable to Load Customers")
                .font(CKTypography.headline)
                .foregroundStyle(CKTheme.textPrimary)

            Text(message)
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
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
            .tint(CKTheme.accent)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(CKTheme.backgroundPrimary)
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Error loading customers: \(message)")
    }
}

#Preview {
    NavigationStack {
        CustomerListView(apiClient: APIClient(authManager: AuthManager()))
    }
}
