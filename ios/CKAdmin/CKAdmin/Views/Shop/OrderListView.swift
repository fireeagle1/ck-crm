import SwiftUI

/// Shop orders list with payment and fulfilment status filters.
struct OrderListView: View {
    @State private var viewModel: OrderListViewModel

    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
        _viewModel = State(initialValue: OrderListViewModel(apiClient: apiClient))
    }

    var body: some View {
        @Bindable var viewModel = viewModel

        Group {
            if viewModel.isLoading && viewModel.orders.isEmpty {
                loadingView
            } else if let errorMessage = viewModel.errorMessage, viewModel.orders.isEmpty {
                errorView(message: errorMessage)
            } else {
                orderList
            }
        }
        .navigationTitle("Orders")
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                Menu {
                    Section("Payment Status") {
                        Picker("Payment", selection: $viewModel.selectedPaymentFilter) {
                            ForEach(OrderPaymentFilter.allCases) { filter in
                                Text(filter.displayName).tag(filter)
                            }
                        }
                    }
                    Section("Fulfilment") {
                        Picker("Fulfilment", selection: $viewModel.selectedFulfilmentFilter) {
                            ForEach(OrderFulfilmentFilter.allCases) { filter in
                                Text(filter.displayName).tag(filter)
                            }
                        }
                    }
                } label: {
                    Label("Filter", systemImage: "line.3.horizontal.decrease.circle")
                }
                .accessibilityLabel("Filter orders")
            }
        }
        .task {
            if viewModel.orders.isEmpty {
                await viewModel.loadInitial()
            }
        }
    }

    // MARK: - Order List

    private var orderList: some View {
        List {
            ForEach(viewModel.orders) { order in
                NavigationLink(value: order.id) {
                    orderRow(order)
                }
                .onAppear {
                    if order.id == viewModel.orders.last?.id {
                        Task { await viewModel.loadNextPage() }
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
        .refreshable {
            await viewModel.loadInitial()
        }
        .navigationDestination(for: Int.self) { orderId in
            OrderActionDetailView(apiClient: apiClient, orderId: orderId)
        }
        .overlay {
            if viewModel.orders.isEmpty && !viewModel.isLoading {
                ContentUnavailableView(
                    "No Orders",
                    systemImage: "bag",
                    description: Text("No orders match the current filters.")
                )
            }
        }
    }

    // MARK: - Order Row

    private func orderRow(_ order: OrderListItem) -> some View {
        CKRow {
            VStack(alignment: .leading, spacing: 4) {
                Text(order.customerName ?? "Unknown Customer")
                    .font(CKTypography.headline)
                    .foregroundStyle(CKTheme.textPrimary)
                    .lineLimit(1)

                Text(formattedAmount(order.totalAmount))
                    .font(CKTypography.callout)
                    .foregroundStyle(CKTheme.textPrimary)

                HStack {
                    Label("\(order.itemCount) item\(order.itemCount == 1 ? "" : "s")", systemImage: "shippingbox")
                        .font(CKTypography.caption)
                        .foregroundStyle(CKTheme.textSecondary)

                    Spacer()

                    if let date = order.createdAt {
                        Text(date, style: .date)
                            .font(CKTypography.caption)
                            .foregroundStyle(CKTheme.textSecondary)
                    }
                }
            }
        } trailing: {
            VStack(alignment: .trailing, spacing: 6) {
                paymentBadge(order.paymentStatus)
                fulfilmentBadge(order.fulfilmentStatus)
            }
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(order.customerName ?? "Unknown"), \(formattedAmount(order.totalAmount)), \(order.paymentStatus)")
    }

    // MARK: - Badges

    private func paymentBadge(_ status: String) -> some View {
        Text(status.replacingOccurrences(of: "_", with: " ").capitalized)
            .font(CKTypography.caption)
            .fontWeight(.medium)
            .padding(.horizontal, 8)
            .padding(.vertical, 2)
            .background(paymentColor(status).opacity(0.15))
            .foregroundStyle(paymentColor(status))
            .clipShape(Capsule())
    }

    private func fulfilmentBadge(_ status: String) -> some View {
        Text(status.replacingOccurrences(of: "_", with: " ").capitalized)
            .font(CKTypography.caption)
            .fontWeight(.medium)
            .padding(.horizontal, 8)
            .padding(.vertical, 2)
            .background(fulfilmentColor(status).opacity(0.15))
            .foregroundStyle(fulfilmentColor(status))
            .clipShape(Capsule())
    }

    private func paymentColor(_ status: String) -> Color {
        switch status {
        case "paid", "paid_offline": return CKTheme.success
        case "pending": return CKTheme.warning
        case "failed": return CKTheme.error
        default: return CKTheme.textTertiary
        }
    }

    private func fulfilmentColor(_ status: String) -> Color {
        switch status {
        case "completed": return CKTheme.success
        case "awaiting_fulfilment": return CKTheme.info
        case "pending": return CKTheme.warning
        default: return CKTheme.textTertiary
        }
    }

    // MARK: - Formatting

    private func formattedAmount(_ amount: Double) -> String {
        let formatter = NumberFormatter()
        formatter.numberStyle = .currency
        formatter.currencyCode = "GBP"
        formatter.maximumFractionDigits = 2
        return formatter.string(from: NSNumber(value: amount)) ?? "£\(String(format: "%.2f", amount))"
    }

    // MARK: - Loading States

    private var loadingMoreRow: some View {
        HStack {
            Spacer()
            ProgressView().controlSize(.small)
            Text("Loading more...")
                .font(CKTypography.caption)
                .foregroundStyle(CKTheme.textSecondary)
            Spacer()
        }
        .listRowSeparator(.hidden)
    }

    private var loadingView: some View {
        VStack(spacing: 16) {
            ProgressView().controlSize(.large)
            Text("Loading orders...")
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(CKTheme.backgroundPrimary)
    }

    private func errorView(message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle")
                .font(.system(size: 48))
                .foregroundStyle(CKTheme.warning)
            Text("Unable to Load Orders")
                .font(CKTypography.headline)
                .foregroundStyle(CKTheme.textPrimary)
            Text(message)
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal)
            Button { Task { await viewModel.loadInitial() } } label: {
                Label("Retry", systemImage: "arrow.clockwise").fontWeight(.medium)
            }
            .buttonStyle(.borderedProminent)
            .tint(CKTheme.accent)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(CKTheme.backgroundPrimary)
    }
}

#Preview {
    NavigationStack {
        OrderListView(apiClient: APIClient(authManager: AuthManager()))
    }
}
