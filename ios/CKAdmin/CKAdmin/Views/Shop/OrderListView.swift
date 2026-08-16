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
        VStack(alignment: .leading, spacing: 4) {
            HStack {
                Text(order.customerName ?? "Unknown Customer")
                    .font(.body)
                    .fontWeight(.medium)
                    .lineLimit(1)

                Spacer()

                paymentBadge(order.paymentStatus)
            }

            HStack {
                Text(formattedAmount(order.totalAmount))
                    .font(.subheadline)
                    .fontWeight(.semibold)

                Spacer()

                fulfilmentBadge(order.fulfilmentStatus)
            }

            HStack {
                Label("\(order.itemCount) item\(order.itemCount == 1 ? "" : "s")", systemImage: "shippingbox")
                    .font(.caption)
                    .foregroundStyle(.secondary)

                Spacer()

                if let date = order.createdAt {
                    Text(date, style: .date)
                        .font(.caption)
                        .foregroundStyle(.secondary)
                }
            }
        }
        .padding(.vertical, 2)
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(order.customerName ?? "Unknown"), \(formattedAmount(order.totalAmount)), \(order.paymentStatus)")
    }

    // MARK: - Badges

    private func paymentBadge(_ status: String) -> some View {
        Text(status.replacingOccurrences(of: "_", with: " ").capitalized)
            .font(.caption2)
            .fontWeight(.medium)
            .padding(.horizontal, 8)
            .padding(.vertical, 2)
            .background(paymentColor(status).opacity(0.15))
            .foregroundStyle(paymentColor(status))
            .clipShape(Capsule())
    }

    private func fulfilmentBadge(_ status: String) -> some View {
        Text(status.replacingOccurrences(of: "_", with: " ").capitalized)
            .font(.caption2)
            .fontWeight(.medium)
            .padding(.horizontal, 8)
            .padding(.vertical, 2)
            .background(fulfilmentColor(status).opacity(0.15))
            .foregroundStyle(fulfilmentColor(status))
            .clipShape(Capsule())
    }

    private func paymentColor(_ status: String) -> Color {
        switch status {
        case "paid", "paid_offline": return .green
        case "pending": return .orange
        case "failed": return .red
        default: return .gray
        }
    }

    private func fulfilmentColor(_ status: String) -> Color {
        switch status {
        case "completed": return .green
        case "awaiting_fulfilment": return .blue
        case "pending": return .orange
        default: return .gray
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
            Text("Loading more...").font(.caption).foregroundStyle(.secondary)
            Spacer()
        }
        .listRowSeparator(.hidden)
    }

    private var loadingView: some View {
        VStack(spacing: 16) {
            ProgressView().controlSize(.large)
            Text("Loading orders...").font(.subheadline).foregroundStyle(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }

    private func errorView(message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle")
                .font(.system(size: 48))
                .foregroundStyle(.orange)
            Text("Unable to Load Orders").font(.headline)
            Text(message)
                .font(.subheadline)
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal)
            Button { Task { await viewModel.loadInitial() } } label: {
                Label("Retry", systemImage: "arrow.clockwise").fontWeight(.medium)
            }
            .buttonStyle(.borderedProminent)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}

#Preview {
    NavigationStack {
        OrderListView(apiClient: APIClient(authManager: AuthManager()))
    }
}
