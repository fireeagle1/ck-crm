import Foundation

// MARK: - Asset List Item

struct AssetListItem: Decodable, Identifiable, Hashable {
    let deviceId: Int
    let deviceName: String
    let deviceType: String?
    let location: String?
    let assetStatus: String
    let serialNumber: String?
    let customerName: String?
    let customerId: Int?

    var id: Int { deviceId }
}

// MARK: - Asset Current Booking

struct AssetCurrentBooking: Decodable {
    let id: Int
    let status: String
    let fulfilmentStage: String
    let startDate: String
    let endDate: String
    let customerName: String?
}

// MARK: - Asset Inspection

struct AssetInspection: Decodable, Identifiable {
    let id: Int
    let type: String  // "checkout" or "return"
    let conditionNotes: String?
    let damageFlagged: Bool
    let inspectorName: String?
    let inspectedAt: String
}

// MARK: - Asset Detail

struct AssetDetail: Decodable, Identifiable {
    let deviceId: Int
    let deviceName: String
    let deviceType: String?
    let location: String?
    let assetStatus: String
    let serialNumber: String?
    let notes: String?
    let customerId: Int?
    let customerName: String?
    let createdAt: String?
    let updatedAt: String?
    let tickets: [AssetTicket]?
    let currentBooking: AssetCurrentBooking?
    let recentInspections: [AssetInspection]?

    var id: Int { deviceId }
}

struct AssetTicket: Decodable, Identifiable {
    let ticketId: Int
    let subject: String
    let status: String
    let priority: String
    let ticketType: String?
    let createdAt: String?

    var id: Int { ticketId }
}

struct AssetDetailResponse: Decodable {
    let data: AssetDetail
}

struct AssetMutateResponse: Decodable {
    let data: AssetListItem
}

// MARK: - Asset Status Filter

enum AssetStatusFilter: String, CaseIterable, Identifiable {
    case all = "All"
    case active = "Active"
    case decommissioned = "Decommissioned"
    case inRepair = "In Repair"

    var id: String { rawValue }

    var queryValue: String? {
        switch self {
        case .all: return nil
        default: return rawValue
        }
    }
}
