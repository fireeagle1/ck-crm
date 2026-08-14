import Foundation

// MARK: - Invoice List Item

/// An invoice record as returned in the paginated list endpoint.
struct InvoiceListItem: Decodable, Identifiable {
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
