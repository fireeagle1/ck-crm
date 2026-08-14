import SwiftUI

/// Dashboard screen displaying KPI metrics and recent activity.
///
/// Shows ticket statistics, financial metrics, expiring domains,
/// recent tickets, and recent admin logins. Supports pull-to-refresh
/// and displays loading/error states.
struct DashboardView: View {
    @State private var viewModel: DashboardViewModel

    init(apiClient: APIClient) {
        _viewModel = State(initialValue: DashboardViewModel(apiClient: apiClient))
    }

    var body: some View {
        Group {
            if viewModel.isLoading && viewModel.dashboard == nil {
                loadingView
            } else if let errorMessage = viewModel.errorMessage, viewModel.dashboard == nil {
                errorView(message: errorMessage)
            } else if let dashboard = viewModel.dashboard {
                dashboardContent(dashboard)
            } else {
                loadingView
            }
        }
        .navigationTitle("Dashboard")
        .task {
            await viewModel.loadMetrics()
        }
    }

    // MARK: - Loading State

    private var loadingView: some View {
        VStack(spacing: 16) {
            ProgressView()
                .controlSize(.large)
            Text("Loading dashboard...")
                .font(.subheadline)
                .foregroundStyle(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .accessibilityLabel("Loading dashboard metrics")
    }

    // MARK: - Error State

    private func errorView(message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle")
                .font(.system(size: 48))
                .foregroundStyle(.orange)

            Text("Unable to Load Dashboard")
                .font(.headline)

            Text(message)
                .font(.subheadline)
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal)

            Button {
                Task {
                    await viewModel.loadMetrics()
                }
            } label: {
                Label("Retry", systemImage: "arrow.clockwise")
                    .fontWeight(.medium)
            }
            .buttonStyle(.borderedProminent)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Error loading dashboard: \(message)")
    }

    // MARK: - Dashboard Content

    private func dashboardContent(_ dashboard: DashboardResponse) -> some View {
        List {
            ticketStatsSection(dashboard.tickets)
            financialSection(dashboard.financials)
            expiringDomainsSection(dashboard.expiringDomains)
            recentTicketsSection(dashboard.recentTickets)
            recentLoginsSection(dashboard.recentLogins)
        }
        .listStyle(.insetGrouped)
        .refreshable {
            await viewModel.loadMetrics()
        }
    }

    // MARK: - Ticket Statistics Section

    private func ticketStatsSection(_ stats: TicketStats) -> some View {
        Section {
            metricRow(label: "Open Tickets", value: "\(stats.openCount)", icon: "envelope.open", color: .blue)
            metricRow(label: "Critical", value: "\(stats.criticalCount)", icon: "exclamationmark.circle", color: .red)
            metricRow(label: "High Priority", value: "\(stats.highCount)", icon: "arrow.up.circle", color: .orange)
            metricRow(label: "Overdue", value: "\(stats.overdueCount)", icon: "clock.badge.exclamationmark", color: .purple)
            metricRow(label: "Avg Response", value: formatResponseTime(stats.avgResponseTimeMinutes), icon: "timer", color: .green)
        } header: {
            Label("Ticket Statistics", systemImage: "ticket")
        }
    }

    // MARK: - Financial Section

    private func financialSection(_ financials: FinancialStats) -> some View {
        Section {
            metricRow(label: "MRR", value: formatCurrency(financials.mrr), icon: "sterlingsign.circle", color: .green)
            metricRow(label: "ARR", value: formatCurrency(financials.arr), icon: "chart.line.uptrend.xyaxis", color: .green)
            metricRow(label: "Overdue Invoices", value: "\(financials.overdueInvoicesCount)", icon: "doc.badge.clock", color: .red)
            metricRow(label: "Overdue Amount", value: formatCurrency(financials.overdueInvoicesAmount), icon: "sterlingsign.arrow.circlepath", color: .red)
            metricRow(label: "Revenue (Month)", value: formatCurrency(financials.revenueThisMonth), icon: "banknote", color: .blue)
        } header: {
            Label("Financials", systemImage: "chart.bar")
        }
    }

    // MARK: - Expiring Domains Section

    private func expiringDomainsSection(_ domains: [ExpiringDomain]) -> some View {
        Section {
            if domains.isEmpty {
                Text("No domains expiring soon")
                    .font(.subheadline)
                    .foregroundStyle(.secondary)
            } else {
                ForEach(domains) { domain in
                    HStack {
                        VStack(alignment: .leading, spacing: 4) {
                            Text(domain.domainName)
                                .font(.body)
                                .fontWeight(.medium)
                            Text(domain.customerName)
                                .font(.caption)
                                .foregroundStyle(.secondary)
                        }

                        Spacer()

                        Text("\(domain.daysUntilExpiry)d")
                            .font(.callout)
                            .fontWeight(.semibold)
                            .foregroundStyle(domain.daysUntilExpiry <= 7 ? .red : .orange)
                            .accessibilityLabel("\(domain.daysUntilExpiry) days until expiry")
                    }
                    .accessibilityElement(children: .combine)
                }
            }
        } header: {
            Label("Expiring Domains", systemImage: "globe")
        }
    }

    // MARK: - Recent Tickets Section

    private func recentTicketsSection(_ tickets: [RecentTicket]) -> some View {
        Section {
            if tickets.isEmpty {
                Text("No recent tickets")
                    .font(.subheadline)
                    .foregroundStyle(.secondary)
            } else {
                ForEach(tickets) { ticket in
                    HStack {
                        VStack(alignment: .leading, spacing: 4) {
                            Text(ticket.subject)
                                .font(.body)
                                .lineLimit(1)
                            Text(ticket.customerName)
                                .font(.caption)
                                .foregroundStyle(.secondary)
                        }

                        Spacer()

                        statusBadge(ticket.status)
                    }
                    .accessibilityElement(children: .combine)
                    .accessibilityLabel("\(ticket.subject), \(ticket.customerName), status: \(ticket.status)")
                }
            }
        } header: {
            Label("Recent Tickets", systemImage: "list.bullet.clipboard")
        }
    }

    // MARK: - Recent Logins Section

    private func recentLoginsSection(_ logins: [RecentLogin]) -> some View {
        Section {
            if logins.isEmpty {
                Text("No recent logins")
                    .font(.subheadline)
                    .foregroundStyle(.secondary)
            } else {
                ForEach(logins) { login in
                    HStack {
                        Label(login.userName, systemImage: "person.circle")
                            .font(.body)

                        Spacer()

                        Text(login.lastLogin, style: .relative)
                            .font(.caption)
                            .foregroundStyle(.secondary)
                    }
                    .accessibilityElement(children: .combine)
                    .accessibilityLabel("\(login.userName), logged in recently")
                }
            }
        } header: {
            Label("Recent Logins", systemImage: "person.badge.clock")
        }
    }

    // MARK: - Helper Views

    private func metricRow(label: String, value: String, icon: String, color: Color) -> some View {
        HStack {
            Label(label, systemImage: icon)
                .foregroundStyle(color)
                .font(.subheadline)

            Spacer()

            Text(value)
                .font(.body)
                .fontWeight(.semibold)
                .monospacedDigit()
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(label): \(value)")
    }

    private func statusBadge(_ status: String) -> some View {
        Text(status)
            .font(.caption2)
            .fontWeight(.medium)
            .padding(.horizontal, 8)
            .padding(.vertical, 3)
            .background(statusColor(for: status).opacity(0.15))
            .foregroundStyle(statusColor(for: status))
            .clipShape(Capsule())
    }

    // MARK: - Formatting

    private func formatCurrency(_ amount: Double) -> String {
        let formatter = NumberFormatter()
        formatter.numberStyle = .currency
        formatter.currencyCode = "GBP"
        formatter.maximumFractionDigits = 2
        formatter.minimumFractionDigits = 2
        return formatter.string(from: NSNumber(value: amount)) ?? "£\(String(format: "%.2f", amount))"
    }

    private func formatResponseTime(_ minutes: Double) -> String {
        if minutes < 60 {
            return "\(Int(minutes.rounded()))m"
        }
        let hours = Int(minutes) / 60
        let remainingMinutes = Int(minutes) % 60
        return "\(hours)h \(remainingMinutes)m"
    }

    private func statusColor(for status: String) -> Color {
        switch status.lowercased() {
        case "open":
            return .blue
        case "pending":
            return .orange
        case "in progress":
            return .purple
        case "closed", "resolved":
            return .green
        default:
            return .gray
        }
    }
}

#Preview {
    NavigationStack {
        DashboardView(apiClient: APIClient(authManager: AuthManager()))
    }
}
