import Foundation
import Observation

/// View model for the shop products list screen.
@Observable
final class ProductListViewModel {

    // MARK: - State

    private(set) var products: [ProductListItem] = []
    private(set) var isLoading = false
    private(set) var isLoadingMore = false
    private(set) var errorMessage: String?

    /// Product type filter. Changes reset and reload.
    var selectedTypeFilter: ProductTypeFilter = .all {
        didSet {
            guard selectedTypeFilter != oldValue else { return }
            Task { @MainActor in await resetAndLoad() }
        }
    }

    /// Whether to show archived products.
    var showArchived = false {
        didSet {
            guard showArchived != oldValue else { return }
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
            let response = try await fetchProducts(page: nextPage)
            products.append(contentsOf: response.data)
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
        products = []
        currentPage = 0
        lastPage = 1
        errorMessage = nil
        isLoading = true

        do {
            let response = try await fetchProducts(page: 1)
            products = response.data
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

    private func fetchProducts(page: Int) async throws -> PaginatedResponse<ProductListItem> {
        var queryItems: [String: String] = ["page": String(page)]

        if let value = selectedTypeFilter.queryValue {
            queryItems["product_type"] = value
        }
        if showArchived {
            queryItems["show_archived"] = "true"
        }

        let endpoint = Endpoint(path: "/admin/shop/products", queryItems: queryItems)
        return try await apiClient.request(endpoint)
    }
}
