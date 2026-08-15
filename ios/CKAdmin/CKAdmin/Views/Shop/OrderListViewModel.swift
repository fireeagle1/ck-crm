import Foundation
import Observation

/// View model for the shop orders list screen.
///
/// Manages paginated loading and status filtering for orders.
@Observable
final class OrderListViewModel {

    // MARK: - State

    private(set) var orders: [OrderListItem] = []
    private(set) var isLoading = false
    private(set) var isLoadingMore = false
    private(set) var errorMessage: String?

    /// Payment status filter. Changes reset and reload.
    var selectedPaymentFilter: OrderPaymentFilter = .all {
        didSet {
            guard selectedPaymentFilter != oldValue else { return }
            Task { @MainActor in await resetAndLoad() }
        }
    }

    /// Fulfilment status filter. Changes reset and reload.
    var selectedFulfilmentFilter: OrderFulfilmentFilter = .all {
        didSet {
            guard selectedFulfilmentFilter != oldValue else { return }
            Task { @MainActor in await resetAndLoad() }
        }
    }

    // MARK: - Pagination

    private(set) var currentPage = 0
    private(set) var lastPage = 1
    var hasMorePages: Bool { currentPage < lastPage }

    // MARK: - Private

    private let apiClient: APIClient

    // MARK: - Init

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    // MARK: - Public Methods

    @MainActor
    func loadInitial() async {
        guard !isLoading else { return }
        await resetAndLoad()
    }

    @MainActor
    func loadNextPage() async {
        guard !isLoadingMore, hasMorePages else { return }

        isLoadingMore = true

        do {
            let nextPage = currentPage + 1
            let response = try await fetchOrders(page: nextPage)
            orders.append(contentsOf: response.data)
            currentPage = response.meta.currentPage
            lastPage = response.meta.lastPage
        } catch is CancellationError {
            // Ignored
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "An unexpected error occurred."
        }

        isLoadingMore = false
    }

    // MARK: - Private Methods

    @MainActor
    private func resetAndLoad() async {
        orders = []
        currentPage = 0
        lastPage = 1
        errorMessage = nil
        isLoading = true

        do {
            let response = try await fetchOrders(page: 1)
            orders = response.data
            currentPage = response.meta.currentPage
            lastPage = response.meta.lastPage
        } catch is CancellationError {
            // Ignored
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "An unexpected error occurred."
        }

        isLoading = false
    }

    private func fetchOrders(page: Int) async throws -> PaginatedResponse<OrderListItem> {
        var queryItems: [String: String] = ["page": String(page)]

        if let value = selectedPaymentFilter.queryValue {
            queryItems["payment_status"] = value
        }
        if let value = selectedFulfilmentFilter.queryValue {
            queryItems["fulfilment_status"] = value
        }

        let endpoint = Endpoint(path: "/admin/shop/orders", queryItems: queryItems)
        return try await apiClient.request(endpoint)
    }
}
