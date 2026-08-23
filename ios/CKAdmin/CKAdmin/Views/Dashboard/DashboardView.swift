import SwiftUI

/// Dashboard screen displaying KPI metrics and recent activity.
///
/// Shows ticket statistics, financial metrics, expiring domains,
/// recent tickets, and recent admin logins. Supports pull-to-refresh
/// and displays loading/error states.
///
/// Uses the CKTheme colour palette, CKTypography font scale, and
/// CKMetricCard components for a consistent design system look.
struct DashboardView: View {
    @State private var viewModel: DashboardViewModel
    @State private var showingCreateTicket = false
    @State private var showingCreateInvoice = false
    @Binding var selectedTab: Int

    private let apiClient: APIClient

    init(apiClient: APIClient, selectedTab: Binding<Int> = .constant(0)) {
        self.apiClient = apiClient
        self._selectedTab = selectedTab
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
        .navigationTitle("CK Enterprises UK")
        .navigationBarTitleDisplayMode(.inline)
        .sheet(isPresented: $showingCreateTicket) {
            TicketCreateView(apiClient: apiClient) {
                await viewModel.loadMetrics()
            }
        }
        .sheet(isPresented: $showingCreateInvoice) {
            InvoiceCreateView(apiClient: apiClient, onSave: {
                await viewModel.loadMetrics()
            })
        }
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
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(CKTheme.backgroundPrimary)
        .accessibilityLabel("Loading dashboard metrics")
    }

    // MARK: - Error State

    private func errorView(message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle")
                .font(.system(size: 48))
                .foregroundStyle(CKTheme.warning)

            Text("Unable to Load Dashboard")
                .font(CKTypography.headline)
                .foregroundStyle(CKTheme.textPrimary)

            Text(message)
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
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
            .tint(CKTheme.accent)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(CKTheme.backgroundPrimary)
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Error loading dashboard: \(message)")
    }

    // MARK: - Dashboard Content

    private func dashboardContent(_ dashboard: DashboardResponse) -> some View {
        List {
            // Compact hero header with image background
            Section {
                ZStack(alignment: .leading) {
                    Image("HeroImage")
                        .resizable()
                        .scaledToFill()
                        .frame(height: 100)
                        .clipped()
                        .overlay(
                            LinearGradient(
                                colors: [CKTheme.primary.opacity(0.6), CKTheme.primary.opacity(0.85)],
                                startPoint: .top,
                                endPoint: .bottom
                            )
                        )

                    HStack {
                        VStack(alignment: .leading, spacing: 4) {
                            Image("Logo")
                                .resizable()
                                .scaledToFit()
                                .frame(height: 20)
                            Text("CK Enterprises UK")
                                .font(CKTypography.title)
                                .foregroundStyle(.white)
                        }
                        Spacer()
                    }
                    .padding(.horizontal, 16)
                }
                .listRowInsets(EdgeInsets())
            }

            // Quick Actions — compact single row
            Section {
                ScrollView(.horizontal, showsIndicators: false) {
                    HStack(spacing: 10) {
                        actionButton(title: "New Ticket", icon: "ticket", color: CKTheme.info) {
                            showingCreateTicket = true
                        }
                        actionButton(title: "New Invoice", icon: "doc.text", color: CKTheme.success) {
                            showingCreateInvoice = true
                        }
                        actionButton(title: "Customers", icon: "person.2", color: .purple) {
                            selectedTab = 2
                        }
                        actionButton(title: "CMDB", icon: "desktopcomputer", color: CKTheme.warning) {
                            selectedTab = 3
                        }
                    }
                    .padding(.vertical, 4)
                }
                .listRowInsets(EdgeInsets(top: 8, leading: 16, bottom: 8, trailing: 16))
            }

            ticketStatsSection(dashboard.tickets)
            rentalMetricsSection(dashboard.rentals)
            financialSection(dashboard.financials)
            expiringDomainsSection(dashboard.expiringDomains)
            recentTicketsSection(dashboard.recentTickets)
            recentLoginsSection(dashboard.recentLogins)
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .background(CKTheme.backgroundPrimary)
        .refreshable {
            await viewModel.loadMetrics()
        }
    }

    // MARK: - Action Button

    private func actionButton(title: String, icon: String, color: Color, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            VStack(spacing: 6) {
                Image(systemName: icon)
                    .font(.title3)
                    .foregroundStyle(color)
                Text(title)
                    .font(CKTypography.caption)
                    .fontWeight(.medium)
                    .foregroundStyle(CKTheme.textPrimary)
                    .lineLimit(1)
            }
            .frame(width: 80)
            .padding(.vertical, 10)
            .background(color.opacity(0.1))
            .clipShape(RoundedRectangle(cornerRadius: 10))
        }
        .buttonStyle(.plain)
    }

    // MARK: - Ticket Statistics Section

    private func ticketStatsSection(_ stats: TicketStats) -> some View {
        Section {
            LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 12) {
                Button { selectedTab = 1 } label: {
                    CKMetricCard(title: "Open Tickets", value: "\(stats.openCount)", icon: "envelope.open", color: CKTheme.info)
                }
                .buttonStyle(.plain)
                Button { selectedTab = 1 } label: {
                    CKMetricCard(title: "Critical", value: "\(stats.criticalCount)", icon: "exclamationmark.circle", color: CKTheme.error)
                }
                .buttonStyle(.plain)
                Button { selectedTab = 1 } label: {
                    CKMetricCard(title: "High Priority", value: "\(stats.highCount)", icon: "arrow.up.circle", color: CKTheme.warning)
                }
                .buttonStyle(.plain)
                Button { selectedTab = 1 } label: {
                    CKMetricCard(title: "Overdue", value: "\(stats.overdueCount)", icon: "clock.badge.exclamationmark", color: .purple)
                }
                .buttonStyle(.plain)
            }
            metricRow(label: "Avg Response", value: formatResponseTime(stats.avgResponseTimeMinutes ?? 0), icon: "timer", color: CKTheme.success)
        } header: {
            Label("Ticket Statistics", systemImage: "ticket")
                .font(CKTypography.caption)
                .foregroundStyle(CKTheme.textSecondary)
        }
    }

    // MARK: - Rental Metrics Section

    @ViewBuilder
    private func rentalMetricsSection(_ rentals: RentalStats?) -> some View {
        if let rentals {
            Section {
                LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 12) {
                    Button { selectedTab = 6 } label: {
                        CKMetricCard(title: "Active Rentals", value: "\(rentals.activeRentalsCount)", icon: "box.truck", color: .teal)
                    }
                    .buttonStyle(.plain)
                    Button { selectedTab = 6 } label: {
                        CKMetricCard(title: "Upcoming Returns", value: "\(rentals.upcomingReturnsCount)", icon: "calendar.badge.clock", color: CKTheme.warning)
                    }
                    .buttonStyle(.plain)
                    Button { selectedTab = 6 } label: {
                        CKMetricCard(title: "Recently Returned", value: "\(rentals.recentlyReturnedCount)", icon: "checkmark.circle", color: CKTheme.success)
                    }
                    .buttonStyle(.plain)
                }
            } header: {
                Label("Rentals", systemImage: "shippingbox")
                    .font(CKTypography.caption)
                    .foregroundStyle(CKTheme.textSecondary)
            }
        }
    }

    // MARK: - Financial Section

    private func financialSection(_ financials: FinancialStats) -> some View {
        Section {
            LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 12) {
                CKMetricCard(title: "MRR", value: formatCurrency(financials.mrr), icon: "sterlingsign.circle", color: CKTheme.success)
                CKMetricCard(title: "ARR", value: formatCurrency(financials.arr), icon: "chart.line.uptrend.xyaxis", color: CKTheme.success)
                Button { selectedTab = 4 } label: {
                    CKMetricCard(title: "Overdue Invoices", value: "\(financials.overdueInvoicesCount)", icon: "doc.badge.clock", color: CKTheme.error)
                }
                .buttonStyle(.plain)
                Button { selectedTab = 4 } label: {
                    CKMetricCard(title: "Overdue Amount", value: formatCurrency(financials.overdueInvoicesAmount), icon: "sterlingsign.arrow.circlepath", color: CKTheme.error)
                }
                .buttonStyle(.plain)
            }
            metricRow(label: "Revenue (Month)", value: formatCurrency(financials.revenueThisMonth), icon: "banknote", color: CKTheme.info)
        } header: {
            Label("Financials", systemImage: "chart.bar")
                .font(CKTypography.caption)
                .foregroundStyle(CKTheme.textSecondary)
        }
    }

    // MARK: - Expiring Domains Section

    private func expiringDomainsSection(_ domains: [ExpiringDomain]) -> some View {
        Section {
            if domains.isEmpty {
                Text("No domains expiring soon")
                    .font(CKTypography.body)
                    .foregroundStyle(CKTheme.textSecondary)
            } else {
                ForEach(domains) { domain in
                    HStack {
                        VStack(alignment: .leading, spacing: 4) {
                            Text(domain.domainName)
                                .font(CKTypography.body)
                                .fontWeight(.medium)
                                .foregroundStyle(CKTheme.textPrimary)
                            Text(domain.customerName)
                                .font(CKTypography.caption)
                                .foregroundStyle(CKTheme.textSecondary)
                        }

                        Spacer()

                        Text("\(domain.daysUntilExpiry)d")
                            .font(CKTypography.callout)
                            .fontWeight(.semibold)
                            .foregroundStyle(domain.daysUntilExpiry <= 7 ? CKTheme.error : CKTheme.warning)
                            .accessibilityLabel("\(domain.daysUntilExpiry) days until expiry")
                    }
                    .accessibilityElement(children: .combine)
                }
            }
        } header: {
            Label("Expiring Domains", systemImage: "globe")
                .font(CKTypography.caption)
                .foregroundStyle(CKTheme.textSecondary)
        }
    }

    // MARK: - Recent Tickets Section

    private func recentTicketsSection(_ tickets: [RecentTicket]) -> some View {
        Section {
            if tickets.isEmpty {
                Text("No recent tickets")
                    .font(CKTypography.body)
                    .foregroundStyle(CKTheme.textSecondary)
            } else {
                ForEach(tickets) { ticket in
                    NavigationLink(destination: TicketDetailView(ticketId: ticket.ticketId, apiClient: apiClient)) {
                        HStack {
                            VStack(alignment: .leading, spacing: 4) {
                                Text(ticket.subject)
                                    .font(CKTypography.body)
                                    .foregroundStyle(CKTheme.textPrimary)
                                    .lineLimit(1)
                                Text(ticket.customerName)
                                    .font(CKTypography.caption)
                                    .foregroundStyle(CKTheme.textSecondary)
                            }

                            Spacer()

                            statusBadge(ticket.status)
                        }
                    }
                    .accessibilityElement(children: .combine)
                    .accessibilityLabel("\(ticket.subject), \(ticket.customerName), status: \(ticket.status)")
                }
            }
        } header: {
            Label("Recent Tickets", systemImage: "list.bullet.clipboard")
                .font(CKTypography.caption)
                .foregroundStyle(CKTheme.textSecondary)
        }
    }

    // MARK: - Recent Logins Section

    private func recentLoginsSection(_ logins: [RecentLogin]) -> some View {
        Section {
            if logins.isEmpty {
                Text("No recent logins")
                    .font(CKTypography.body)
                    .foregroundStyle(CKTheme.textSecondary)
            } else {
                ForEach(logins) { login in
                    HStack {
                        Label(login.userName, systemImage: "person.circle")
                            .font(CKTypography.body)
                            .foregroundStyle(CKTheme.textPrimary)

                        Spacer()

                        Text(login.lastLogin, style: .relative)
                            .font(CKTypography.caption)
                            .foregroundStyle(CKTheme.textSecondary)
                    }
                    .accessibilityElement(children: .combine)
                    .accessibilityLabel("\(login.userName), logged in recently")
                }
            }
        } header: {
            Label("Recent Logins", systemImage: "person.badge.clock")
                .font(CKTypography.caption)
                .foregroundStyle(CKTheme.textSecondary)
        }
    }

    // MARK: - Helper Views

    private func metricRow(label: String, value: String, icon: String, color: Color) -> some View {
        HStack {
            Label(label, systemImage: icon)
                .foregroundStyle(color)
                .font(CKTypography.body)

            Spacer()

            Text(value)
                .font(CKTypography.metricSmall)
                .foregroundStyle(CKTheme.textPrimary)
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(label): \(value)")
    }

    private func statusBadge(_ status: String) -> some View {
        Text(status)
            .font(CKTypography.caption)
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
            return CKTheme.info
        case "pending":
            return CKTheme.warning
        case "in progress":
            return .purple
        case "closed", "resolved":
            return CKTheme.success
        default:
            return CKTheme.textTertiary
        }
    }
}

#Preview {
    NavigationStack {
        DashboardView(apiClient: APIClient(authManager: AuthManager()))
    }
}
