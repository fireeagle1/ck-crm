import Foundation
import Observation

/// View model for the Dashboard screen.
///
/// Fetches KPI metrics from GET /api/admin/dashboard and exposes
/// loading, error, and data states for the view to render.
@Observable
final class DashboardViewModel {

    // MARK: - State

    /// The loaded dashboard data, or nil if not yet loaded.
    private(set) var dashboard: DashboardResponse?

    /// Whether a network request is currently in progress.
    private(set) var isLoading = false

    /// An error message to display if the last request failed.
    private(set) var errorMessage: String?

    // MARK: - Dependencies

    private let apiClient: APIClient

    // MARK: - Initialization

    /// Creates a view model with the given API client.
    /// - Parameter apiClient: The API client to use for fetching dashboard data.
    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    // MARK: - Public Methods

    /// Fetches the dashboard metrics from the API.
    ///
    /// Sets `isLoading` during the request and populates either `dashboard`
    /// on success or `errorMessage` on failure.
    @MainActor
    func loadMetrics() async {
        isLoading = true
        errorMessage = nil

        do {
            let response: DashboardResponse = try await apiClient.request(
                Endpoint(path: "/admin/dashboard")
            )
            dashboard = response
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "An unexpected error occurred."
        }

        isLoading = false
    }
}
