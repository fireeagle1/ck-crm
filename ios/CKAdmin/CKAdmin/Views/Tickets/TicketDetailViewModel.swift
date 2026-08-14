import Foundation
import Observation

/// View model for the ticket detail screen.
///
/// Manages loading the full ticket detail (including replies and activities),
/// submitting new replies, and updating ticket status/priority/assignee.
@Observable
final class TicketDetailViewModel {

    // MARK: - State

    /// The loaded ticket detail including replies and activities.
    private(set) var ticket: TicketDetail?

    /// Whether the initial load is in progress.
    private(set) var isLoading = false

    /// Error message from the last failed request.
    private(set) var errorMessage: String?

    /// Whether a reply is currently being submitted.
    private(set) var isSendingReply = false

    /// Whether an update is currently in progress.
    private(set) var isUpdating = false

    /// The text content of the reply being composed.
    var replyText = ""

    /// Success message after a reply or update completes.
    private(set) var successMessage: String?

    // MARK: - Private Properties

    private let ticketId: Int
    private let apiClient: APIClient

    // MARK: - Initialization

    /// Creates a view model for displaying a specific ticket.
    /// - Parameters:
    ///   - ticketId: The ID of the ticket to load.
    ///   - apiClient: The client used to communicate with the API.
    init(ticketId: Int, apiClient: APIClient) {
        self.ticketId = ticketId
        self.apiClient = apiClient
    }

    // MARK: - Public Methods

    /// Loads the full ticket detail from the API.
    @MainActor
    func loadTicket() async {
        isLoading = true
        errorMessage = nil

        do {
            let endpoint = Endpoint(path: "/admin/tickets/\(ticketId)")
            let response: TicketDetailResponse = try await apiClient.request(endpoint)
            ticket = response.data
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "An unexpected error occurred."
        }

        isLoading = false
    }

    /// Submits a reply to the ticket.
    ///
    /// On success, clears the reply text and reloads the ticket to show the new reply.
    @MainActor
    func sendReply() async {
        let body = replyText.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !body.isEmpty else { return }

        isSendingReply = true
        errorMessage = nil

        do {
            let requestBody = TicketReplyRequest(body: body)
            let endpoint = Endpoint(
                method: .post,
                path: "/admin/tickets/\(ticketId)/replies",
                body: requestBody
            )
            let _: TicketReplyResponse = try await apiClient.request(endpoint)
            replyText = ""
            // Reload ticket to reflect the new reply in the thread
            await loadTicket()
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "Failed to send reply."
        }

        isSendingReply = false
    }

    /// Updates the ticket's status.
    /// - Parameter newStatus: The new status value.
    @MainActor
    func updateStatus(_ newStatus: String) async {
        let request = TicketUpdateRequest(status: newStatus)
        await updateTicket(request)
    }

    /// Updates the ticket's priority.
    /// - Parameter newPriority: The new priority value.
    @MainActor
    func updatePriority(_ newPriority: String) async {
        let request = TicketUpdateRequest(priority: newPriority)
        await updateTicket(request)
    }

    /// Updates the ticket's assigned user.
    /// - Parameter userId: The new assignee user ID, or nil to unassign.
    @MainActor
    func updateAssignee(_ userId: Int?) async {
        let request = TicketUpdateRequest(userId: userId)
        await updateTicket(request)
    }

    // MARK: - Private Methods

    /// Sends a PUT request to update the ticket and reloads on success.
    @MainActor
    private func updateTicket(_ request: TicketUpdateRequest) async {
        isUpdating = true
        errorMessage = nil

        do {
            let endpoint = Endpoint(
                method: .put,
                path: "/admin/tickets/\(ticketId)",
                body: request
            )
            let response: TicketUpdateResponse = try await apiClient.request(endpoint)
            ticket = response.data
            successMessage = "Ticket updated."
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "Failed to update ticket."
        }

        isUpdating = false
    }
}
