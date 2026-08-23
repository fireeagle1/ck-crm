import Foundation

// MARK: - Booking List Item

/// A booking record as returned in the paginated rentals list endpoint.
/// Used by the Rentals tab to display booking summaries with fulfilment stage filtering.
struct BookingListItem: Decodable, Identifiable {
    let id: Int
    let productName: String?
    let customerName: String?
    let startDate: String?
    let endDate: String?
    let quantity: Int
    let totalPrice: Double
    let status: String
    let fulfilmentStage: String
    let returnedAt: String?

    enum CodingKeys: String, CodingKey {
        case id, productName, customerName, startDate, endDate
        case quantity, totalPrice, status, fulfilmentStage, returnedAt
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(Int.self, forKey: .id)
        productName = try container.decodeIfPresent(String.self, forKey: .productName)
        customerName = try container.decodeIfPresent(String.self, forKey: .customerName)
        startDate = try container.decodeIfPresent(String.self, forKey: .startDate)
        endDate = try container.decodeIfPresent(String.self, forKey: .endDate)
        quantity = try container.decodeIfPresent(Int.self, forKey: .quantity) ?? 1
        status = try container.decode(String.self, forKey: .status)
        fulfilmentStage = try container.decodeIfPresent(String.self, forKey: .fulfilmentStage) ?? "ordered"
        returnedAt = try container.decodeIfPresent(String.self, forKey: .returnedAt)

        // Handle totalPrice as either a numeric or string value from the API
        if let p = try? container.decode(Double.self, forKey: .totalPrice) {
            totalPrice = p
        } else if let pStr = try? container.decode(String.self, forKey: .totalPrice),
                  let p = Double(pStr) {
            totalPrice = p
        } else {
            totalPrice = 0
        }
    }
}

// MARK: - Booking Asset Item

/// An asset assigned to a booking, used in the booking detail view.
struct BookingAssetItem: Decodable, Identifiable {
    let id: Int
    let deviceName: String?
    let serialNumber: String?
    let status: String?
    let assignedAt: String?
    let releasedAt: String?
}

// MARK: - Booking Inspection Detail

/// Inspection details for checkout or return inspections on a booking.
struct BookingInspectionDetail: Decodable {
    let photos: [String]?
    let conditionNotes: String?
    let damageFlagged: Bool?
    let inspectorName: String?
    let inspectedAt: String?
}
