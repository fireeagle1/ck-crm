import Foundation
import Observation

/// View model for the ticket list screen.
///
/// Manages paginated loading, status and priority filtering, and loading/error states.
/// Uses the `@Observable` macro (iOS 17+) for automatic SwiftUI integration.
@Observable
final class TicketListViewModel {

    // MARK: - State

    /// The loaded ticket records.
    private(set) var tickets: [TicketListItem] = []

    /// Whether an initial load is in progress (no data yet).
    private(set) var isLoading = false

    /// Whether an additional page is currently being fetched.
    private(set) var isLoadingMore = false

    /// Error message from the last failed request.
    private(set) var errorMessage: String?

    /// The selected status filter. Changes reset pagination and reload.
    var selectedStatus: TicketStatusFilter = .all {
        didSet {
            guard selectedStatus != oldValue else { return }
            Task { @MainActor in
                await resetAndLoad()
            }
        }
    }

    /// The selected priority filter. Changes reset pagination and reload.
    var selectedPriority: TicketPriorityFilter = .all {
        didSet {
            guard selectedPriority != oldValue else { return }
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
    /// - Parameter apiClient: The client used to fetch ticket data.
    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    // MARK: - Public Methods

    /// Loads the first page of tickets. Resets existing data.
    @MainActor
    func loadInitial() async {
        guard !isLoading else { return }
        await resetAndLoad()
    }

    /// Loads the next page of tickets if available.
    ///
    /// Call this when the last item in the list appears on screen.
    @MainActor
    func loadNextPage() async {
        guard !isLoadingMore, hasMorePages else { return }

        isLoadingMore = true

        do {
            let nextPage = currentPage + 1
            let response = try await fetchTickets(page: nextPage)
            tickets.append(contentsOf: response.data)
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
        tickets = []
        currentPage = 0
        lastPage = 1
        errorMessage = nil
        isLoading = true

        do {
            let response = try await fetchTickets(page: 1)
            tickets = response.data
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

    /// Fetches a page of tickets from the API.
    /// - Parameter page: The page number to fetch.
    /// - Returns: The paginated response with ticket data and metadata.
    private func fetchTickets(page: Int) async throws -> PaginatedResponse<TicketListItem> {
        var queryItems: [String: String] = ["page": String(page)]

        if let statusValue = selectedStatus.queryValue {
            queryItems["status"] = statusValue
        }

        if let priorityValue = selectedPriority.queryValue {
            queryItems["priority"] = priorityValue
        }

        let endpoint = Endpoint(path: "/admin/tickets", queryItems: queryItems)
        return try await apiClient.request(endpoint)
    }
}
