import Foundation

// MARK: - Ticket List Item

/// A ticket record as returned in the paginated list endpoint.
struct TicketListItem: Decodable, Identifiable {
    let ticketId: Int
    let subject: String
    let status: String
    let priority: String
    let customerName: String?
    let assignedUserName: String?
    let createdAt: Date

    var id: Int { ticketId }
}

// MARK: - Ticket Status Filter

/// Available status filters for the ticket list.
enum TicketStatusFilter: String, CaseIterable, Identifiable {
    case all = "All"
    case open = "Open"
    case pending = "Pending"
    case inProgress = "In Progress"
    case closed = "Closed"

    var id: String { rawValue }

    /// The API query value, or nil for "All" (no filter applied).
    var queryValue: String? {
        switch self {
        case .all: return nil
        default: return rawValue
        }
    }
}

// MARK: - Ticket Priority Filter

/// Available priority filters for the ticket list.
enum TicketPriorityFilter: String, CaseIterable, Identifiable {
    case all = "All"
    case low = "Low"
    case normal = "Normal"
    case high = "High"
    case critical = "Critical"

    var id: String { rawValue }

    /// The API query value, or nil for "All" (no filter applied).
    var queryValue: String? {
        switch self {
        case .all: return nil
        default: return rawValue
        }
    }
}
