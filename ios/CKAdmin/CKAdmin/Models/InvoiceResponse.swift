import Foundation

// MARK: - Invoice List Item

/// An invoice record as returned in the paginated list endpoint.
struct InvoiceListItem: Identifiable {
    let invoiceId: Int
    let invoiceStatus: String
    let invoiceAmount: Double
    let invoiceDate: String
    let dueDate: String
    let paidDate: String?
    let customerName: String?

    var id: Int { invoiceId }

    /// Whether this invoice is overdue (Unpaid and past due date).
    var isOverdue: Bool {
        guard invoiceStatus == "Unpaid" else { return false }
        guard let due = Self.dateFormatter.date(from: dueDate) else { return false }
        return due < Date()
    }

    private static let dateFormatter: DateFormatter = {
        let formatter = DateFormatter()
        formatter.dateFormat = "yyyy-MM-dd"
        formatter.locale = Locale(identifier: "en_US_POSIX")
        return formatter
    }()
}

extension InvoiceListItem: Decodable {
    enum CodingKeys: String, CodingKey {
        case invoiceId, invoiceStatus, invoiceAmount, invoiceDate, dueDate, paidDate, customerName
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        invoiceId = try container.decode(Int.self, forKey: .invoiceId)
        invoiceStatus = try container.decode(String.self, forKey: .invoiceStatus)
        invoiceDate = try container.decode(String.self, forKey: .invoiceDate)
        dueDate = try container.decode(String.self, forKey: .dueDate)
        paidDate = try container.decodeIfPresent(String.self, forKey: .paidDate)
        customerName = try container.decodeIfPresent(String.self, forKey: .customerName)

        // Handle invoice_amount as either a number or a string (Laravel decimal cast returns string)
        if let amount = try? container.decode(Double.self, forKey: .invoiceAmount) {
            invoiceAmount = amount
        } else if let amountString = try? container.decode(String.self, forKey: .invoiceAmount),
                  let amount = Double(amountString) {
            invoiceAmount = amount
        } else {
            invoiceAmount = 0
        }
    }
}

// MARK: - Invoice Status Filter

/// Available status filters for the invoice list.
enum InvoiceStatusFilter: String, CaseIterable, Identifiable {
    case all = "All"
    case unpaid = "Unpaid"
    case paid = "Paid"
    case overdue = "Overdue"

    var id: String { rawValue }

    /// The API query value, or nil for "All" (no filter applied).
    var queryValue: String? {
        switch self {
        case .all: return nil
        default: return rawValue
        }
    }
}
