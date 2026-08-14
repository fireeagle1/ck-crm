import Foundation

// MARK: - Ticket Detail

struct TicketDetail: Decodable, Identifiable {
    let ticketId: Int
    let subject: String
    let description: String?
    let status: String
    let priority: String
    let ticketType: String?
    let requestCategory: String?
    let companyId: Int?
    let customerName: String?
    let userId: Int?
    let assignedUserName: String?
    let assetId: Int?
    let assetName: String?
    let serviceId: Int?
    let serviceName: String?
    let dueAt: Date?
    let firstRepliedAt: Date?
    let createdAt: Date?
    let updatedAt: Date?
    let replies: [TicketReply]
    let activities: [TicketActivity]

    var id: Int { ticketId }
}

struct TicketReply: Decodable, Identifiable {
    let id: Int
    let body: String
    let userName: String?
    let isInternal: Bool?
    let attachmentPath: String?
    let createdAt: Date?
}

struct TicketActivity: Decodable, Identifiable {
    let id: Int
    let type: String?
    let oldValue: String?
    let newValue: String?
    let userName: String?
    let createdAt: Date?
}

struct TicketDetailResponse: Decodable { let data: TicketDetail }

struct TicketUpdateRequest: Encodable {
    var status: String?
    var priority: String?
    var ticketType: String?
    var userId: Int?
    enum CodingKeys: String, CodingKey { case status; case priority; case ticketType = "ticket_type"; case userId = "user_id" }
}

struct TicketReplyRequest: Encodable { let body: String }
struct TicketReplyResponse: Decodable { let data: TicketReply }
struct TicketUpdateResponse: Decodable { let data: TicketDetail }
