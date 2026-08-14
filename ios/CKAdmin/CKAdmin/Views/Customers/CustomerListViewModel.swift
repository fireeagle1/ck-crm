import Foundation
import Observation

/// View model for the customer list screen.
///
/// Manages paginated loading, search filtering, and loading/error states.
/// Uses the `@Observable` macro (iOS 17+) for automatic SwiftUI integration.
@Observable
final class CustomerListViewModel {

    // MARK: - State

    /// The loaded customer records.
    private(set) var customers: [CustomerListItem] = []

    /// Whether an initial load or search is in progress (no data yet).
    private(set) var isLoading = false

    /// Whether an additional page is currently being fetched.
    private(set) var isLoadingMore = false

    /// Error message from the last failed request.
    private(set) var errorMessage: String?

    /// The current search query. Changes reset pagination and reload.
    var searchText: String = "" {
        didSet {
            guard searchText != oldValue else { return }
            searchTask?.cancel()
            searchTask = Task { @MainActor in
                // Debounce: wait 300ms before executing search
                try? await Task.sleep(for: .milliseconds(300))
                guard !Task.isCancelled else { return }
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
    private var searchTask: Task<Void, Never>?

    // MARK: - Initialization

    /// Creates a view model with the given API client.
    /// - Parameter apiClient: The client used to fetch customer data.
    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    // MARK: - Public Methods

    /// Loads the first page of customers. Resets existing data.
    @MainActor
    func loadInitial() async {
        guard !isLoading else { return }
        await resetAndLoad()
    }

    /// Loads the next page of customers if available.
    ///
    /// Call this when the last item in the list appears on screen.
    @MainActor
    func loadNextPage() async {
        guard !isLoadingMore, hasMorePages else { return }

        isLoadingMore = true

        do {
            let nextPage = currentPage + 1
            let response = try await fetchCustomers(page: nextPage)
            customers.append(contentsOf: response.data)
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
        customers = []
        currentPage = 0
        lastPage = 1
        errorMessage = nil
        isLoading = true

        do {
            let response = try await fetchCustomers(page: 1)
            customers = response.data
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

    /// Fetches a page of customers from the API.
    /// - Parameter page: The page number to fetch.
    /// - Returns: The paginated response with customer data and metadata.
    private func fetchCustomers(page: Int) async throws -> PaginatedResponse<CustomerListItem> {
        var queryItems: [String: String] = ["page": String(page)]

        let trimmedSearch = searchText.trimmingCharacters(in: .whitespacesAndNewlines)
        if !trimmedSearch.isEmpty {
            queryItems["search"] = trimmedSearch
        }

        let endpoint = Endpoint(path: "/admin/customers", queryItems: queryItems)
        return try await apiClient.request(endpoint)
    }
}
