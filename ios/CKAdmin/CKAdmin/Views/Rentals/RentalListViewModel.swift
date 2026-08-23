import Foundation
import Observation

/// View model for the rental list screen.
///
/// Manages paginated loading of bookings with fulfilment stage filtering.
/// Uses the `@Observable` macro (iOS 17+) for automatic SwiftUI integration.
@Observable
final class RentalListViewModel {

    // MARK: - State

    /// The loaded booking records.
    private(set) var bookings: [BookingListItem] = []

    /// Whether an initial load is in progress (no data yet).
    private(set) var isLoading = false

    /// Whether an additional page is currently being fetched.
    private(set) var isLoadingMore = false

    /// Error message from the last failed request.
    private(set) var errorMessage: String?

    /// The selected fulfilment stage filter. Changes reset pagination and reload.
    var selectedStage: FulfilmentStageFilter = .all {
        didSet {
            guard selectedStage != oldValue else { return }
            Task { @MainActor in
                await resetAndLoad()
            }
        }
    }

    // MARK: - Pagination State

    /// The current page that has been loaded.
    private(set) var currentPage = 0

    /// The last page available from the API.
    private(set) var lastPage = 1

    /// Whether more pages are available to load.
    var hasMorePages: Bool { currentPage < lastPage }

    // MARK: - Private Properties

    private let apiClient: APIClient

    // MARK: - Initialization

    /// Creates a view model with the given API client.
    /// - Parameter apiClient: The client used to fetch rental data.
    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    // MARK: - Public Methods

    /// Loads the first page of bookings. Resets existing data.
    @MainActor
    func loadInitial() async {
        guard !isLoading else { return }
        await resetAndLoad()
    }

    /// Loads the next page of bookings if available.
    ///
    /// Call this when the last item in the list appears on screen.
    @MainActor
    func loadNextPage() async {
        guard !isLoadingMore, hasMorePages else { return }

        isLoadingMore = true

        do {
            let nextPage = currentPage + 1
            let response = try await fetchBookings(page: nextPage)
            bookings.append(contentsOf: response.data)
            currentPage = response.meta.currentPage
            lastPage = response.meta.lastPage
        } catch is CancellationError {
            // Cancelled — ignore
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "An unexpected error occurred."
        }

        isLoadingMore = false
    }

    // MARK: - Private Methods

    /// Resets pagination state and loads the first page.
    @MainActor
    private func resetAndLoad() async {
        bookings = []
        currentPage = 0
        lastPage = 1
        errorMessage = nil
        isLoading = true

        do {
            let response = try await fetchBookings(page: 1)
            bookings = response.data
            currentPage = response.meta.currentPage
            lastPage = response.meta.lastPage
        } catch is CancellationError {
            // Cancelled — ignore
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "An unexpected error occurred."
        }

        isLoading = false
    }

    /// Fetches a page of bookings from the API.
    /// - Parameter page: The page number to fetch.
    /// - Returns: The paginated response with booking data and metadata.
    private func fetchBookings(page: Int) async throws -> PaginatedResponse<BookingListItem> {
        var queryItems: [String: String] = ["page": String(page)]

        if let stageValue = selectedStage.queryValue {
            queryItems["stage"] = stageValue
        }

        let endpoint = Endpoint(path: "/admin/shop/rentals", queryItems: queryItems)
        return try await apiClient.request(endpoint)
    }
}
