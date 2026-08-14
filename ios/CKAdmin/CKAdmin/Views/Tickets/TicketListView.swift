import SwiftUI

/// Ticket list screen with status/priority filters and infinite-scroll pagination.
///
/// Displays subject, status badge, priority badge, customer name, assigned user,
/// and relative creation time for each record. Supports combined status and priority
/// filtering via toolbar pickers and loads additional pages when the last item
/// appears on screen.
struct TicketListView: View {
    @State private var viewModel: TicketListViewModel
    @State private var showingCreateSheet = false

    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
        _viewModel = State(initialValue: TicketListViewModel(apiClient: apiClient))
    }

    var body: some View {
        @Bindable var viewModel = viewModel

        Group {
            if viewModel.isLoading && viewModel.tickets.isEmpty {
                loadingView
            } else if let errorMessage = viewModel.errorMessage, viewModel.tickets.isEmpty {
                errorView(message: errorMessage)
            } else {
                ticketList
            }
        }
        .navigationTitle("Tickets")
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                HStack(spacing: 12) {
                    Button {
                        showingCreateSheet = true
                    } label: {
                        Label("New Ticket", systemImage: "plus")
                    }
                    .accessibilityLabel("Create new ticket")

                    Menu {
                        Picker("Status", selection: $viewModel.selectedStatus) {
                            ForEach(TicketStatusFilter.allCases) { status in
                                Text(status.rawValue).tag(status)
                            }
                        }

                        Picker("Priority", selection: $viewModel.selectedPriority) {
                            ForEach(TicketPriorityFilter.allCases) { priority in
                                Text(priority.rawValue).tag(priority)
                            }
                        }
                    } label: {
                        Label(filterLabel, systemImage: filterIcon)
                    }
                    .accessibilityLabel("Filter tickets")
                }
            }
        }
        .sheet(isPresented: $showingCreateSheet) {
            TicketCreateView(apiClient: apiClient) {
                await viewModel.loadInitial()
            }
        }
        .task {
            if viewModel.tickets.isEmpty {
                await viewModel.loadInitial()
            }
        }
    }

    // MARK: - Filter Label

    /// Dynamic label showing active filter state.
    private var filterLabel: String {
        let hasStatusFilter = viewModel.selectedStatus != .all
        let hasPriorityFilter = viewModel.selectedPriority != .all

        if hasStatusFilter && hasPriorityFilter {
            return "\(viewModel.selectedStatus.rawValue) / \(viewModel.selectedPriority.rawValue)"
        } else if hasStatusFilter {
            return viewModel.selectedStatus.rawValue
        } else if hasPriorityFilter {
            return viewModel.selectedPriority.rawValue
        }
        return "Filter"
    }

    /// Icon reflecting whether filters are active.
    private var filterIcon: String {
        let hasFilter = viewModel.selectedStatus != .all || viewModel.selectedPriority != .all
        return hasFilter ? "line.3.horizontal.decrease.circle.fill" : "line.3.horizontal.decrease.circle"
    }

    // MARK: - Ticket List

    private var ticketList: some View {
        List {
            ForEach(viewModel.tickets) { ticket in
                NavigationLink(destination: TicketDetailView(ticketId: ticket.ticketId, apiClient: apiClient)) {
                    ticketRow(ticket)
                }
                .onAppear {
                    if ticket.id == viewModel.tickets.last?.id {
                        Task {
                            await viewModel.loadNextPage()
                        }
                    }
                }
            }

            if viewModel.isLoadingMore {
                loadingMoreRow
            }
        }
        .listStyle(.plain)
        .refreshable {
            await viewModel.loadInitial()
        }
        .overlay {
            if viewModel.tickets.isEmpty && !viewModel.isLoading {
                ContentUnavailableView(
                    "No Tickets",
                    systemImage: "ticket",
                    description: Text("No tickets match the current filters.")
                )
            }
        }
    }

    // MARK: - Ticket Row

    private func ticketRow(_ ticket: TicketListItem) -> some View {
        VStack(alignment: .leading, spacing: 6) {
            // Subject line with priority badge
            HStack {
                Text(ticket.subject)
                    .font(.body)
                    .fontWeight(.medium)
                    .lineLimit(2)

                Spacer()

                priorityBadge(ticket.priority)
            }

            // Status badge and created time
            HStack {
                statusBadge(ticket.status)

                Spacer()

                Text(ticket.createdAt, style: .relative)
                    .font(.caption)
                    .foregroundStyle(.secondary)
            }

            // Customer and assigned user
            HStack {
                if let customerName = ticket.customerName, !customerName.isEmpty {
                    Label(customerName, systemImage: "person")
                        .font(.caption)
                        .foregroundStyle(.secondary)
                        .lineLimit(1)
                }

                Spacer()

                if let assignedUser = ticket.assignedUserName, !assignedUser.isEmpty {
                    Label(assignedUser, systemImage: "person.badge.shield.checkmark")
                        .font(.caption)
                        .foregroundStyle(.secondary)
                        .lineLimit(1)
                }
            }
        }
        .padding(.vertical, 2)
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(ticket.subject), \(ticket.status), \(ticket.priority) priority")
    }

    // MARK: - Status Badge

    private func statusBadge(_ status: String) -> some View {
        Text(status)
            .font(.caption2)
            .fontWeight(.medium)
            .padding(.horizontal, 8)
            .padding(.vertical, 2)
            .background(statusColor(status).opacity(0.15))
            .foregroundStyle(statusColor(status))
            .clipShape(Capsule())
    }

    private func statusColor(_ status: String) -> Color {
        switch status.lowercased() {
        case "open":
            return .blue
        case "pending":
            return .orange
        case "in progress":
            return .purple
        case "closed":
            return .green
        default:
            return .gray
        }
    }

    // MARK: - Priority Badge

    private func priorityBadge(_ priority: String) -> some View {
        Text(priority)
            .font(.caption2)
            .fontWeight(.medium)
            .padding(.horizontal, 8)
            .padding(.vertical, 2)
            .background(priorityColor(priority).opacity(0.15))
            .foregroundStyle(priorityColor(priority))
            .clipShape(Capsule())
    }

    private func priorityColor(_ priority: String) -> Color {
        switch priority.lowercased() {
        case "low":
            return .gray
        case "normal":
            return .blue
        case "high":
            return .orange
        case "critical":
            return .red
        default:
            return .gray
        }
    }

    // MARK: - Loading More Row

    private var loadingMoreRow: some View {
        HStack {
            Spacer()
            ProgressView()
                .controlSize(.small)
            Text("Loading more...")
                .font(.caption)
                .foregroundStyle(.secondary)
            Spacer()
        }
        .listRowSeparator(.hidden)
        .accessibilityLabel("Loading more tickets")
    }

    // MARK: - Loading State

    private var loadingView: some View {
        VStack(spacing: 16) {
            ProgressView()
                .controlSize(.large)
            Text("Loading tickets...")
                .font(.subheadline)
                .foregroundStyle(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .accessibilityLabel("Loading ticket list")
    }

    // MARK: - Error State

    private func errorView(message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle")
                .font(.system(size: 48))
                .foregroundStyle(.orange)

            Text("Unable to Load Tickets")
                .font(.headline)

            Text(message)
                .font(.subheadline)
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal)

            Button {
                Task {
                    await viewModel.loadInitial()
                }
            } label: {
                Label("Retry", systemImage: "arrow.clockwise")
                    .fontWeight(.medium)
            }
            .buttonStyle(.borderedProminent)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Error loading tickets: \(message)")
    }
}

#Preview {
    NavigationStack {
        TicketListView(apiClient: APIClient(authManager: AuthManager()))
    }
}
