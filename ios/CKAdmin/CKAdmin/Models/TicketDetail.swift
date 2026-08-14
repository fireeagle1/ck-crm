import Foundation

// MARK: - Ticket Detail

/// Full ticket record as returned by the show endpoint.
///
/// Includes all ticket fields plus nested replies and activities arrays.
struct TicketDetail: Decodable, Identifiable {
    let ticketId: Int
    let subject: String
    let description: String?
    let status: String
    let priority: String
    let customerName: String?
    let assignedUserName: String?
    let companyId: Int?
    let userId: Int?
    let assetName: String?
    let serviceName: String?
    let dueAt: Date?
    let firstRepliedAt: Date?
    let createdAt: Date?
    let updatedAt: Date?

    /// Replies ordered by created_at ascending.
    let replies: [TicketReply]

    /// Activity log entries ordered by created_at ascending.
    let activities: [TicketActivity]

    var id: Int { ticketId }
}

// MARK: - Ticket Reply

/// A single reply in a ticket's conversation thread.
struct TicketReply: Decodable, Identifiable {
    let id: Int
    let body: String
    let userName: String?
    let isInternal: Bool?
    let createdAt: Date?
}

// MARK: - Ticket Activity

/// A single activity log entry for a ticket.
struct TicketActivity: Decodable, Identifiable {
    let id: Int
    let type: String?
    let description: String?
    let userName: String?
    let createdAt: Date?
}

// MARK: - Ticket Detail Response

/// Wrapper for the single-ticket detail API response.
struct TicketDetailResponse: Decodable {
    let data: TicketDetail
}

// MARK: - Ticket Update Request

/// Request body for updating a ticket's status, priority, or assignee.
struct TicketUpdateRequest: Encodable {
    var status: String?
    var priority: String?
    var userId: Int?

    enum CodingKeys: String, CodingKey {
        case status
        case priority
        case userId = "user_id"
    }
}

// MARK: - Ticket Reply Request

/// Request body for adding a reply to a ticket.
struct TicketReplyRequest: Encodable {
    let body: String
}

// MARK: - Ticket Reply Response

/// Response wrapper for a created ticket reply.
struct TicketReplyResponse: Decodable {
    let data: TicketReply
}

// MARK: - Ticket Update Response

/// Response wrapper for an updated ticket.
struct TicketUpdateResponse: Decodable {
    let data: TicketDetail
}
