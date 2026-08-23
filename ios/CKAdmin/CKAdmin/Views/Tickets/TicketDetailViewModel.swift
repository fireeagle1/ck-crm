import Foundation
import Observation

@Observable
final class TicketDetailViewModel {

    private(set) var ticket: TicketDetail?
    private(set) var isLoading = false
    private(set) var errorMessage: String?
    private(set) var isSendingReply = false
    private(set) var isUpdating = false
    var replyText = ""
    var isInternalNote = false
    private(set) var successMessage: String?

    private let ticketId: Int
    private let apiClient: APIClient

    init(ticketId: Int, apiClient: APIClient) {
        self.ticketId = ticketId
        self.apiClient = apiClient
    }

    @MainActor
    func loadTicket() async {
        isLoading = true; errorMessage = nil
        do {
            let response: TicketDetailResponse = try await apiClient.request(Endpoint(path: "/admin/tickets/\(ticketId)"))
            ticket = response.data
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "Failed to load ticket: \(error.localizedDescription)"
        }
        isLoading = false
    }

    @MainActor
    func sendReply() async {
        let body = replyText.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !body.isEmpty else { return }

        isSendingReply = true; errorMessage = nil
        do {
            let requestBody = TicketReplyWithInternal(body: body, isInternal: isInternalNote)
            let endpoint = Endpoint(method: .post, path: "/admin/tickets/\(ticketId)/replies", body: requestBody)
            let _: TicketReplyResponse = try await apiClient.request(endpoint)
            replyText = ""
            isInternalNote = false
            await loadTicket()
        } catch let error as APIError { errorMessage = error.errorDescription }
        catch { errorMessage = "Failed to send reply." }
        isSendingReply = false
    }

    @MainActor func updateStatus(_ newStatus: String) async { await updateTicket(TicketUpdateRequest(status: newStatus)) }
    @MainActor func updatePriority(_ newPriority: String) async { await updateTicket(TicketUpdateRequest(priority: newPriority)) }
    @MainActor func updateType(_ newType: String) async { await updateTicket(TicketUpdateRequest(ticketType: newType)) }

    @MainActor
    private func updateTicket(_ request: TicketUpdateRequest) async {
        isUpdating = true; errorMessage = nil
        do {
            let endpoint = Endpoint(method: .put, path: "/admin/tickets/\(ticketId)", body: request)
            let _: TicketUpdateResponse = try await apiClient.request(endpoint)
            // Reload full detail to get activities
            await loadTicket()
            successMessage = "Ticket updated."
        } catch let error as APIError { errorMessage = error.errorDescription }
        catch { errorMessage = "Failed to update ticket." }
        isUpdating = false
    }
}

// MARK: - Request types

struct TicketReplyWithInternal: Encodable {
    let body: String
    let isInternal: Bool
    enum CodingKeys: String, CodingKey { case body; case isInternal = "is_internal" }
}
