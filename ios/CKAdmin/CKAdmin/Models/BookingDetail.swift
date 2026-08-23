import Foundation

// MARK: - Booking Detail (Full)

/// Full booking detail from the /shop/rentals/{booking} endpoint.
struct BookingDetail: Decodable {
    let id: Int
    let productName: String?
    let customerName: String?
    let orderId: Int?
    let startDate: String?
    let endDate: String?
    let quantity: Int
    let totalPrice: Double
    let status: String
    let fulfilmentStage: String
    let returnedAt: Date?
    let nextStage: String?
    let preConditions: [String]
    let assignedAssets: [AssignedAsset]
    let checkoutInspection: InspectionRecord?
    let returnInspection: InspectionRecord?
    let agreementText: String?

    enum CodingKeys: String, CodingKey {
        case id, productName, customerName, orderId, startDate, endDate
        case quantity, totalPrice, status, fulfilmentStage, returnedAt
        case nextStage, preConditions, assignedAssets, checkoutInspection, returnInspection
        case agreementText
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(Int.self, forKey: .id)
        productName = try container.decodeIfPresent(String.self, forKey: .productName)
        customerName = try container.decodeIfPresent(String.self, forKey: .customerName)
        orderId = try container.decodeIfPresent(Int.self, forKey: .orderId)
        startDate = try container.decodeIfPresent(String.self, forKey: .startDate)
        endDate = try container.decodeIfPresent(String.self, forKey: .endDate)
        quantity = try container.decodeIfPresent(Int.self, forKey: .quantity) ?? 1
        status = try container.decode(String.self, forKey: .status)
        fulfilmentStage = try container.decode(String.self, forKey: .fulfilmentStage)
        returnedAt = try container.decodeIfPresent(Date.self, forKey: .returnedAt)
        nextStage = try container.decodeIfPresent(String.self, forKey: .nextStage)
        preConditions = try container.decodeIfPresent([String].self, forKey: .preConditions) ?? []
        assignedAssets = try container.decodeIfPresent([AssignedAsset].self, forKey: .assignedAssets) ?? []
        checkoutInspection = try container.decodeIfPresent(InspectionRecord.self, forKey: .checkoutInspection)
        returnInspection = try container.decodeIfPresent(InspectionRecord.self, forKey: .returnInspection)
        agreementText = try container.decodeIfPresent(String.self, forKey: .agreementText)

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

/// Response wrapper for booking detail.
struct BookingDetailResponse: Decodable {
    let data: BookingDetail
}

// MARK: - Assigned Asset

struct AssignedAsset: Decodable, Identifiable {
    let id: Int
    let deviceName: String?
    let serialNumber: String?
    let status: String?
    let assignedAt: Date?
    let releasedAt: Date?
}

// MARK: - Inspection Record

struct InspectionRecord: Decodable {
    let photos: [String]
    let conditionNotes: String?
    let damageFlagged: Bool?
    let inspectorName: String?
    let inspectedAt: Date?
}

// MARK: - Calendar Response

struct BookingCalendarResponse: Decodable {
    let data: CalendarData
}

struct CalendarData: Decodable {
    let year: Int
    let month: Int
    let rangeStart: String
    let rangeEnd: String
    let bookings: [CalendarBooking]
    let products: [CalendarProduct]
}

struct CalendarBooking: Decodable, Identifiable {
    let id: Int
    let productName: String?
    let productId: Int?
    let customerName: String?
    let startDate: String
    let endDate: String
    let quantity: Int
    let status: String
    let fulfilmentStage: String?
}

struct CalendarProduct: Decodable, Identifiable {
    let id: Int
    let name: String
    let stockQuantity: Int?
}

// MARK: - Enhanced Order Detail (with booking fulfilment)

/// Booking info as nested within order items from the enhanced show endpoint.
struct OrderItemBooking: Decodable {
    let id: Int
    let status: String
    let fulfilmentStage: String
    let quantity: Int
    let totalPrice: Double
    let returnedAt: Date?
    let assignedAssets: [AssignedAsset]
    let hasCheckoutInspection: Bool
    let hasReturnInspection: Bool
    let nextStage: String?

    enum CodingKeys: String, CodingKey {
        case id, status, fulfilmentStage, quantity, totalPrice, returnedAt
        case assignedAssets, hasCheckoutInspection, hasReturnInspection, nextStage
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(Int.self, forKey: .id)
        status = try container.decode(String.self, forKey: .status)
        fulfilmentStage = try container.decode(String.self, forKey: .fulfilmentStage)
        quantity = try container.decodeIfPresent(Int.self, forKey: .quantity) ?? 1
        returnedAt = try container.decodeIfPresent(Date.self, forKey: .returnedAt)
        assignedAssets = try container.decodeIfPresent([AssignedAsset].self, forKey: .assignedAssets) ?? []
        hasCheckoutInspection = try container.decodeIfPresent(Bool.self, forKey: .hasCheckoutInspection) ?? false
        hasReturnInspection = try container.decodeIfPresent(Bool.self, forKey: .hasReturnInspection) ?? false
        nextStage = try container.decodeIfPresent(String.self, forKey: .nextStage)

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

// MARK: - Enhanced Order Item (replaces old OrderItemDetail for the new show endpoint)

struct EnhancedOrderItemDetail: Decodable, Identifiable {
    let id: Int
    let productName: String?
    let productType: String?
    let price: Double
    let quantity: Int
    let billingFrequency: String?
    let domainName: String?
    let rentalStartDate: String?
    let rentalEndDate: String?
    let booking: OrderItemBooking?

    enum CodingKeys: String, CodingKey {
        case id, productName, productType, price, quantity
        case billingFrequency, domainName, rentalStartDate, rentalEndDate, booking
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(Int.self, forKey: .id)
        productName = try container.decodeIfPresent(String.self, forKey: .productName)
        productType = try container.decodeIfPresent(String.self, forKey: .productType)
        quantity = try container.decodeIfPresent(Int.self, forKey: .quantity) ?? 1
        billingFrequency = try container.decodeIfPresent(String.self, forKey: .billingFrequency)
        domainName = try container.decodeIfPresent(String.self, forKey: .domainName)
        rentalStartDate = try container.decodeIfPresent(String.self, forKey: .rentalStartDate)
        rentalEndDate = try container.decodeIfPresent(String.self, forKey: .rentalEndDate)
        booking = try container.decodeIfPresent(OrderItemBooking.self, forKey: .booking)

        if let p = try? container.decode(Double.self, forKey: .price) {
            price = p
        } else if let pStr = try? container.decode(String.self, forKey: .price),
                  let p = Double(pStr) {
            price = p
        } else {
            price = 0
        }
    }
}

// MARK: - Enhanced Order Detail

struct EnhancedOrderDetail: Decodable {
    let id: Int
    let customerName: String?
    let companyId: Int?
    let totalAmount: Double
    let paymentStatus: String
    let fulfilmentStatus: String
    let adminNotes: String?
    let fulfilledAt: Date?
    let createdAt: Date?
    let deliveryAddress: DeliveryAddress?
    let items: [EnhancedOrderItemDetail]

    enum CodingKeys: String, CodingKey {
        case id, customerName, companyId, totalAmount, paymentStatus, fulfilmentStatus
        case adminNotes, fulfilledAt, createdAt, deliveryAddress, items
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(Int.self, forKey: .id)
        customerName = try container.decodeIfPresent(String.self, forKey: .customerName)
        companyId = try container.decodeIfPresent(Int.self, forKey: .companyId)
        paymentStatus = try container.decode(String.self, forKey: .paymentStatus)
        fulfilmentStatus = try container.decode(String.self, forKey: .fulfilmentStatus)
        adminNotes = try container.decodeIfPresent(String.self, forKey: .adminNotes)
        fulfilledAt = try container.decodeIfPresent(Date.self, forKey: .fulfilledAt)
        createdAt = try container.decodeIfPresent(Date.self, forKey: .createdAt)
        deliveryAddress = try container.decodeIfPresent(DeliveryAddress.self, forKey: .deliveryAddress)
        items = try container.decode([EnhancedOrderItemDetail].self, forKey: .items)

        if let amount = try? container.decode(Double.self, forKey: .totalAmount) {
            totalAmount = amount
        } else if let amountStr = try? container.decode(String.self, forKey: .totalAmount),
                  let amount = Double(amountStr) {
            totalAmount = amount
        } else {
            totalAmount = 0
        }
    }
}

struct EnhancedOrderDetailResponse: Decodable {
    let data: EnhancedOrderDetail
}

// MARK: - API Message Response

struct MessageResponse: Decodable {
    let message: String
    let fulfilmentStage: String?
    let adminNotes: String?
}

// MARK: - Fulfilment Stage Filter

enum FulfilmentStageFilter: String, CaseIterable, Identifiable {
    case all = "All"
    case ordered = "ordered"
    case packing = "packing"
    case ready = "ready"
    case checkedOut = "checked_out"
    case returned = "returned"
    case inspected = "inspected"

    var id: String { rawValue }

    var displayName: String {
        switch self {
        case .all: return "All"
        case .ordered: return "Ordered"
        case .packing: return "Packing"
        case .ready: return "Ready"
        case .checkedOut: return "Checked Out"
        case .returned: return "Returned"
        case .inspected: return "Inspected"
        }
    }

    var queryValue: String? {
        self == .all ? nil : rawValue
    }
}
