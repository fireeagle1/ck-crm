import Foundation

// MARK: - Dashboard Response

/// Top-level response from GET /api/admin/dashboard containing all KPI metrics.
struct DashboardResponse: Decodable {
    let tickets: TicketStats
    let financials: FinancialStats
    let recentTickets: [RecentTicket]
    let recentLogins: [RecentLogin]
    let expiringDomains: [ExpiringDomain]
}

// MARK: - Ticket Statistics

/// Aggregated ticket metrics for the dashboard.
struct TicketStats: Decodable {
    let openCount: Int
    let criticalCount: Int
    let highCount: Int
    let overdueCount: Int
    let avgResponseTimeMinutes: Double?
}

// MARK: - Financial Statistics

/// Revenue and billing metrics for the dashboard.
struct FinancialStats: Decodable {
    let mrr: Double
    let arr: Double
    let overdueInvoicesCount: Int
    let overdueInvoicesAmount: Double
    let revenueThisMonth: Double
}

// MARK: - Recent Ticket

/// A recently created ticket shown on the dashboard.
struct RecentTicket: Decodable, Identifiable {
    let ticketId: Int
    let subject: String
    let customerName: String
    let assignedUserName: String?
    let status: String
    let priority: String

    var id: Int { ticketId }
}

// MARK: - Recent Login

/// A recent admin login shown on the dashboard.
struct RecentLogin: Decodable, Identifiable {
    let userName: String
    let lastLogin: Date

    var id: String { "\(userName)-\(lastLogin.timeIntervalSince1970)" }
}

// MARK: - Expiring Domain

/// A domain expiring within 30 days shown on the dashboard.
struct ExpiringDomain: Decodable, Identifiable {
    let domainName: String
    let customerName: String
    let expiryDate: String
    let daysUntilExpiry: Int

    var id: String { domainName }
}
