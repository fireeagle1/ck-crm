import Foundation
import Observation

/// Reminder action result for UI feedback.
struct ReminderResult {
    let success: Bool
    let message: String
}

/// Response from the remind endpoint.
private struct RemindResponse: Decodable {
    let message: String
}

/// View model for the invoice list screen.
///
/// Manages paginated loading, status filtering, and payment reminder actions.
/// Uses the `@Observable` macro (iOS 17+) for automatic SwiftUI integration.
@Observable
final class InvoiceListViewModel {

    // MARK: - State

    /// The loaded invoice records.
    private(set) var invoices: [InvoiceListItem] = []

    /// Whether an initial load is in progress (no data yet).
    private(set) var isLoading = false

    /// Whether an additional page is currently being fetched.
    private(set) var isLoadingMore = false

    /// Error message from the last failed request.
    private(set) var errorMessage: String?

    /// Result of the last reminder action, used for alert display.
    var reminderResult: ReminderResult?

    /// The selected status filter. Changes reset pagination and reload.
    var selectedStatus: InvoiceStatusFilter = .all {
        didSet {
            guard selectedStatus != oldValue else { return }
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
    /// - Parameter apiClient: The client used to fetch invoice data.
    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    // MARK: - Public Methods

    /// Loads the first page of invoices. Resets existing data.
    @MainActor
    func loadInitial() async {
        guard !isLoading else { return }
        await resetAndLoad()
    }

    /// Loads the next page of invoices if available.
    ///
    /// Call this when the last item in the list appears on screen.
    @MainActor
    func loadNextPage() async {
        guard !isLoadingMore, hasMorePages else { return }

        isLoadingMore = true

        do {
            let nextPage = currentPage + 1
            let response = try await fetchInvoices(page: nextPage)
            invoices.append(contentsOf: response.data)
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

    /// Sends a payment reminder for the given invoice.
    /// - Parameter invoice: The invoice to remind about.
    @MainActor
    func sendReminder(for invoice: InvoiceListItem) async {
        let endpoint = Endpoint(
            method: .post,
            path: "/admin/invoices/\(invoice.invoiceId)/remind"
        )

        do {
            let response: RemindResponse = try await apiClient.request(endpoint)
            reminderResult = ReminderResult(
                success: true,
                message: response.message
            )
        } catch let error as APIError {
            reminderResult = ReminderResult(
                success: false,
                message: error.errorDescription ?? "Failed to send reminder."
            )
        } catch {
            reminderResult = ReminderResult(
                success: false,
                message: "An unexpected error occurred."
            )
        }
    }

    // MARK: - Private Methods

    /// Resets pagination state and loads the first page.
    @MainActor
    private func resetAndLoad() async {
        invoices = []
        currentPage = 0
        lastPage = 1
        errorMessage = nil
        isLoading = true

        do {
            let response = try await fetchInvoices(page: 1)
            invoices = response.data
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

    /// Fetches a page of invoices from the API.
    /// - Parameter page: The page number to fetch.
    /// - Returns: The paginated response with invoice data and metadata.
    private func fetchInvoices(page: Int) async throws -> PaginatedResponse<InvoiceListItem> {
        var queryItems: [String: String] = ["page": String(page)]

        if let statusValue = selectedStatus.queryValue {
            queryItems["status"] = statusValue
        }

        let endpoint = Endpoint(path: "/admin/invoices", queryItems: queryItems)
        return try await apiClient.request(endpoint)
    }
}
