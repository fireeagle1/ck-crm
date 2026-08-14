import Foundation

// MARK: - Service List Item

/// A service record as returned in the paginated list endpoint.
struct ServiceListItem: Decodable, Identifiable, Hashable {
    let serviceId: Int
    let serviceShort: String
    let serviceType: String?
    let domainName: String?
    let status: String
    let serviceMonthlyCharge: Double?
    let customerName: String?

    var id: Int { serviceId }
}

// MARK: - Service Status Filter

/// Available status filters for the service list.
enum ServiceStatusFilter: String, CaseIterable, Identifiable {
    case all = "All"
    case active = "Active"
    case suspended = "Suspended"
    case cancelled = "Cancelled"

    var id: String { rawValue }

    /// The API query value, or nil for "All" (no filter applied).
    var queryValue: String? {
        switch self {
        case .all: return nil
        default: return rawValue
        }
    }
}
