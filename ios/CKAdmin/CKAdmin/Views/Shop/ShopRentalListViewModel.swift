import Foundation
import Observation

/// View model for the rentals/bookings list screen.
@Observable
final class ShopRentalListViewModel {

    // MARK: - State

    private(set) var rentals: [RentalListItem] = []
    private(set) var isLoading = false
    private(set) var isLoadingMore = false
    private(set) var errorMessage: String?

    /// Status filter. Changes reset and reload.
    var selectedStatusFilter: RentalStatusFilter = .all {
        didSet {
            guard selectedStatusFilter != oldValue else { return }
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
            let response = try await fetchRentals(page: nextPage)
            rentals.append(contentsOf: response.data)
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
        rentals = []
        currentPage = 0
        lastPage = 1
        errorMessage = nil
        isLoading = true

        do {
            let response = try await fetchRentals(page: 1)
            rentals = response.data
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

    private func fetchRentals(page: Int) async throws -> PaginatedResponse<RentalListItem> {
        var queryItems: [String: String] = ["page": String(page)]

        if let value = selectedStatusFilter.queryValue {
            queryItems["status"] = value
        }

        let endpoint = Endpoint(path: "/admin/shop/rentals", queryItems: queryItems)
        return try await apiClient.request(endpoint)
    }
}
