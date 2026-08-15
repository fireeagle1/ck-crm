import Foundation

// MARK: - Order List Item

/// An order record as returned in the paginated list endpoint.
struct OrderListItem: Decodable, Identifiable {
    let id: Int
    let customerName: String?
    let totalAmount: Double
    let paymentStatus: String
    let fulfilmentStatus: String
    let itemCount: Int
    let createdAt: Date?

    enum CodingKeys: String, CodingKey {
        case id, customerName, totalAmount, paymentStatus, fulfilmentStatus, itemCount, createdAt
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(Int.self, forKey: .id)
        customerName = try container.decodeIfPresent(String.self, forKey: .customerName)
        fulfilmentStatus = try container.decode(String.self, forKey: .fulfilmentStatus)
        paymentStatus = try container.decode(String.self, forKey: .paymentStatus)
        itemCount = try container.decode(Int.self, forKey: .itemCount)
        createdAt = try container.decodeIfPresent(Date.self, forKey: .createdAt)

        // Handle total_amount as either number or string
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

// MARK: - Order Detail

/// Full order detail including items and delivery address.
struct OrderDetail: Decodable {
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
    let items: [OrderItemDetail]

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
        items = try container.decode([OrderItemDetail].self, forKey: .items)

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

/// Delivery address on an order.
struct DeliveryAddress: Decodable {
    let line1: String?
    let line2: String?
    let city: String?
    let state: String?
    let postalCode: String?
    let country: String?

    /// Formatted multi-line address string.
    var formatted: String? {
        let parts = [line1, line2, city, state, postalCode, country]
            .compactMap { $0 }
            .filter { !$0.isEmpty }
        return parts.isEmpty ? nil : parts.joined(separator: "\n")
    }
}

/// A single item within an order.
struct OrderItemDetail: Decodable, Identifiable {
    let id: Int
    let productName: String?
    let productType: String?
    let price: Double
    let quantity: Int
    let billingFrequency: String?
    let domainName: String?
    let rentalStartDate: String?
    let rentalEndDate: String?
    let bookingStatus: String?

    enum CodingKeys: String, CodingKey {
        case id, productName, productType, price, quantity
        case billingFrequency, domainName, rentalStartDate, rentalEndDate, bookingStatus
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
        bookingStatus = try container.decodeIfPresent(String.self, forKey: .bookingStatus)

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

// MARK: - Order Detail Response Wrapper

/// Wraps the single-object `data` key from the order show endpoint.
struct OrderDetailResponse: Decodable {
    let data: OrderDetail
}

// MARK: - Product List Item

/// A product record as returned in the paginated list endpoint.
struct ProductListItem: Decodable, Identifiable {
    let id: Int
    let name: String
    let productType: String
    let price: Double
    let billingFrequency: String?
    let stockQuantity: Int?
    let isArchived: Bool
    let isAvailable: Bool

    enum CodingKeys: String, CodingKey {
        case id, name, productType, price, billingFrequency, stockQuantity, isArchived, isAvailable
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(Int.self, forKey: .id)
        name = try container.decode(String.self, forKey: .name)
        productType = try container.decode(String.self, forKey: .productType)
        billingFrequency = try container.decodeIfPresent(String.self, forKey: .billingFrequency)
        stockQuantity = try container.decodeIfPresent(Int.self, forKey: .stockQuantity)
        isArchived = try container.decode(Bool.self, forKey: .isArchived)
        isAvailable = try container.decode(Bool.self, forKey: .isAvailable)

        if let p = try? container.decode(Double.self, forKey: .price) {
            price = p
        } else if let pStr = try? container.decode(String.self, forKey: .price),
                  let p = Double(pStr) {
            price = p
        } else {
            price = 0
        }
    }

    /// Human-readable product type label.
    var productTypeLabel: String {
        switch productType {
        case "equipment_rental": return "Equipment Rental"
        case "one_off": return "One-Off"
        case "hosting": return "Hosting"
        default: return productType.capitalized
        }
    }
}

// MARK: - Rental List Item

/// A rental booking record as returned in the paginated list endpoint.
struct RentalListItem: Decodable, Identifiable {
    let id: Int
    let productName: String?
    let customerName: String?
    let startDate: String?
    let endDate: String?
    let quantity: Int
    let totalPrice: Double
    let status: String
    let returnedAt: Date?

    enum CodingKeys: String, CodingKey {
        case id, productName, customerName, startDate, endDate, quantity, totalPrice, status, returnedAt
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
        returnedAt = try container.decodeIfPresent(Date.self, forKey: .returnedAt)

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

// MARK: - Shop Filters

/// Available payment status filters for orders.
enum OrderPaymentFilter: String, CaseIterable, Identifiable {
    case all = "All"
    case pending = "pending"
    case paid = "paid"
    case failed = "failed"
    case paidOffline = "paid_offline"

    var id: String { rawValue }

    var displayName: String {
        switch self {
        case .all: return "All"
        case .pending: return "Pending"
        case .paid: return "Paid"
        case .failed: return "Failed"
        case .paidOffline: return "Paid Offline"
        }
    }

    var queryValue: String? {
        self == .all ? nil : rawValue
    }
}

/// Available fulfilment status filters for orders.
enum OrderFulfilmentFilter: String, CaseIterable, Identifiable {
    case all = "All"
    case pending = "pending"
    case awaitingFulfilment = "awaiting_fulfilment"
    case completed = "completed"

    var id: String { rawValue }

    var displayName: String {
        switch self {
        case .all: return "All"
        case .pending: return "Pending"
        case .awaitingFulfilment: return "Awaiting Fulfilment"
        case .completed: return "Completed"
        }
    }

    var queryValue: String? {
        self == .all ? nil : rawValue
    }
}

/// Available product type filters.
enum ProductTypeFilter: String, CaseIterable, Identifiable {
    case all = "All"
    case equipmentRental = "equipment_rental"
    case oneOff = "one_off"
    case hosting = "hosting"

    var id: String { rawValue }

    var displayName: String {
        switch self {
        case .all: return "All"
        case .equipmentRental: return "Equipment Rental"
        case .oneOff: return "One-Off"
        case .hosting: return "Hosting"
        }
    }

    var queryValue: String? {
        self == .all ? nil : rawValue
    }
}

/// Available rental status filters.
enum RentalStatusFilter: String, CaseIterable, Identifiable {
    case all = "All"
    case confirmed = "confirmed"
    case active = "active"
    case returned = "returned"
    case cancelled = "cancelled"

    var id: String { rawValue }

    var displayName: String {
        switch self {
        case .all: return "All"
        case .confirmed: return "Confirmed"
        case .active: return "Active"
        case .returned: return "Returned"
        case .cancelled: return "Cancelled"
        }
    }

    var queryValue: String? {
        self == .all ? nil : rawValue
    }
}
