import SwiftUI

/// Displays a full ticket detail with conversation thread, activity log, and reply composer.
///
/// Provides toolbar actions to update status, priority, and assignee. Replies are shown
/// in chronological order with author names and timestamps.
struct TicketDetailView: View {
    @Environment(\.dismiss) private var dismiss

    @State private var viewModel: TicketDetailViewModel

    private let apiClient: APIClient

    /// Creates a ticket detail view.
    /// - Parameters:
    ///   - ticketId: The ID of the ticket to display.
    ///   - apiClient: The API client for network requests.
    init(ticketId: Int, apiClient: APIClient) {
        self.apiClient = apiClient
        _viewModel = State(initialValue: TicketDetailViewModel(ticketId: ticketId, apiClient: apiClient))
    }

    var body: some View {
        Group {
            if viewModel.isLoading && viewModel.ticket == nil {
                loadingView
            } else if let errorMessage = viewModel.errorMessage, viewModel.ticket == nil {
                errorView(message: errorMessage)
            } else if let ticket = viewModel.ticket {
                ticketContent(ticket)
            }
        }
        .navigationTitle(viewModel.ticket?.subject ?? "Ticket")
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            if viewModel.ticket != nil {
                ToolbarItem(placement: .topBarTrailing) {
                    actionsMenu
                }
            }
        }
        .task {
            await viewModel.loadTicket()
        }
    }

    // MARK: - Actions Menu

    private var actionsMenu: some View {
        Menu {
            // Status submenu
            Menu {
                ForEach(ticketStatuses, id: \.self) { status in
                    Button(status) {
                        Task {
                            await viewModel.updateStatus(status)
                        }
                    }
                    .disabled(status == viewModel.ticket?.status)
                }
            } label: {
                Label("Change Status", systemImage: "arrow.triangle.2.circlepath")
            }

            // Priority submenu
            Menu {
                ForEach(ticketPriorities, id: \.self) { priority in
                    Button(priority) {
                        Task {
                            await viewModel.updatePriority(priority)
                        }
                    }
                    .disabled(priority == viewModel.ticket?.priority)
                }
            } label: {
                Label("Change Priority", systemImage: "exclamationmark.triangle")
            }
        } label: {
            Label("Actions", systemImage: "ellipsis.circle")
        }
        .disabled(viewModel.isUpdating)
        .accessibilityLabel("Ticket actions")
    }

    // MARK: - Content

    private func ticketContent(_ ticket: TicketDetail) -> some View {
        @Bindable var vm = viewModel

        return VStack(spacing: 0) {
            // Scrollable content area
            List {
                // Header section
                headerSection(ticket)

                // Conversation thread
                if !ticket.replies.isEmpty {
                    repliesSection(ticket.replies)
                }

                // Activity log
                if !ticket.activities.isEmpty {
                    activitySection(ticket.activities)
                }
            }
            .listStyle(.insetGrouped)
            .refreshable {
                await viewModel.loadTicket()
            }

            // Reply composer pinned at bottom
            replyComposer(replyText: $vm.replyText)
        }
    }

    // MARK: - Header Section

    private func headerSection(_ ticket: TicketDetail) -> some View {
        Section("Details") {
            // Status and priority badges row
            HStack {
                statusBadge(ticket.status)
                Spacer()
                priorityBadge(ticket.priority)
            }

            // Description
            if let description = ticket.description, !description.isEmpty {
                VStack(alignment: .leading, spacing: 4) {
                    Text("Description")
                        .font(.caption)
                        .foregroundStyle(.secondary)
                    Text(description)
                        .font(.body)
                }
            }

            // Customer
            if let customerName = ticket.customerName, !customerName.isEmpty {
                detailRow(label: "Customer", value: customerName, icon: "person")
            }

            // Assigned user
            if let assignedUser = ticket.assignedUserName, !assignedUser.isEmpty {
                detailRow(label: "Assigned To", value: assignedUser, icon: "person.badge.shield.checkmark")
            }

            // Asset
            if let asset = ticket.assetName, !asset.isEmpty {
                detailRow(label: "Asset", value: asset, icon: "desktopcomputer")
            }

            // Service
            if let service = ticket.serviceName, !service.isEmpty {
                detailRow(label: "Service", value: service, icon: "server.rack")
            }

            // Created date
            if let createdAt = ticket.createdAt {
                HStack {
                    Label("Created", systemImage: "calendar")
                        .font(.subheadline)
                        .foregroundStyle(.secondary)
                    Spacer()
                    Text(createdAt, style: .relative)
                        .font(.subheadline)
                        .foregroundStyle(.secondary)
                }
            }
        }
    }

    // MARK: - Replies Section

    private func repliesSection(_ replies: [TicketReply]) -> some View {
        Section("Conversation (\(replies.count))") {
            ForEach(replies) { reply in
                replyRow(reply)
            }
        }
    }

    private func replyRow(_ reply: TicketReply) -> some View {
        VStack(alignment: .leading, spacing: 6) {
            // Author and timestamp
            HStack {
                Text(reply.userName ?? "Unknown")
                    .font(.subheadline)
                    .fontWeight(.semibold)

                if reply.isInternal == true {
                    Text("Internal")
                        .font(.caption2)
                        .fontWeight(.medium)
                        .padding(.horizontal, 6)
                        .padding(.vertical, 1)
                        .background(Color.yellow.opacity(0.2))
                        .foregroundStyle(.orange)
                        .clipShape(Capsule())
                }

                Spacer()

                if let createdAt = reply.createdAt {
                    Text(createdAt, style: .relative)
                        .font(.caption)
                        .foregroundStyle(.secondary)
                }
            }

            // Reply body
            Text(reply.body)
                .font(.body)
                .foregroundStyle(.primary)
        }
        .padding(.vertical, 4)
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(reply.userName ?? "Unknown") replied: \(reply.body)")
    }

    // MARK: - Activity Section

    private func activitySection(_ activities: [TicketActivity]) -> some View {
        Section("Activity (\(activities.count))") {
            ForEach(activities) { activity in
                activityRow(activity)
            }
        }
    }

    private func activityRow(_ activity: TicketActivity) -> some View {
        HStack(alignment: .top, spacing: 10) {
            Image(systemName: activityIcon(activity.type))
                .font(.caption)
                .foregroundStyle(.secondary)
                .frame(width: 20)

            VStack(alignment: .leading, spacing: 2) {
                Text(activity.description ?? "Activity")
                    .font(.subheadline)
                    .foregroundStyle(.primary)

                HStack(spacing: 4) {
                    if let userName = activity.userName {
                        Text(userName)
                            .font(.caption)
                            .foregroundStyle(.secondary)
                    }
                    if let createdAt = activity.createdAt {
                        Text("·")
                            .font(.caption)
                            .foregroundStyle(.tertiary)
                        Text(createdAt, style: .relative)
                            .font(.caption)
                            .foregroundStyle(.secondary)
                    }
                }
            }

            Spacer()
        }
        .padding(.vertical, 2)
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(activity.description ?? "Activity") by \(activity.userName ?? "Unknown")")
    }

    // MARK: - Reply Composer

    private func replyComposer(replyText: Binding<String>) -> some View {
        VStack(spacing: 0) {
            Divider()

            HStack(alignment: .bottom, spacing: 10) {
                TextField("Write a reply...", text: replyText, axis: .vertical)
                    .lineLimit(1...5)
                    .textFieldStyle(.plain)
                    .padding(10)
                    .background(Color(.systemGray6))
                    .clipShape(RoundedRectangle(cornerRadius: 10))

                Button {
                    Task {
                        await viewModel.sendReply()
                    }
                } label: {
                    if viewModel.isSendingReply {
                        ProgressView()
                            .controlSize(.small)
                    } else {
                        Image(systemName: "arrow.up.circle.fill")
                            .font(.title2)
                    }
                }
                .disabled(viewModel.replyText.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty || viewModel.isSendingReply)
                .accessibilityLabel("Send reply")
            }
            .padding(.horizontal)
            .padding(.vertical, 8)
            .background(Color(.systemBackground))
        }
    }

    // MARK: - Helper Views

    private func detailRow(label: String, value: String, icon: String) -> some View {
        HStack {
            Label(label, systemImage: icon)
                .font(.subheadline)
                .foregroundStyle(.secondary)
            Spacer()
            Text(value)
                .font(.subheadline)
                .multilineTextAlignment(.trailing)
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(label): \(value)")
    }

    private func statusBadge(_ status: String) -> some View {
        Text(status)
            .font(.caption)
            .fontWeight(.medium)
            .padding(.horizontal, 10)
            .padding(.vertical, 4)
            .background(statusColor(status).opacity(0.15))
            .foregroundStyle(statusColor(status))
            .clipShape(Capsule())
    }

    private func priorityBadge(_ priority: String) -> some View {
        Text(priority)
            .font(.caption)
            .fontWeight(.medium)
            .padding(.horizontal, 10)
            .padding(.vertical, 4)
            .background(priorityColor(priority).opacity(0.15))
            .foregroundStyle(priorityColor(priority))
            .clipShape(Capsule())
    }

    private func statusColor(_ status: String) -> Color {
        switch status.lowercased() {
        case "open": return .blue
        case "pending": return .orange
        case "in progress": return .purple
        case "closed": return .green
        default: return .gray
        }
    }

    private func priorityColor(_ priority: String) -> Color {
        switch priority.lowercased() {
        case "low": return .gray
        case "normal": return .blue
        case "high": return .orange
        case "critical": return .red
        default: return .gray
        }
    }

    private func activityIcon(_ type: String?) -> String {
        switch type?.lowercased() {
        case "status_change": return "arrow.triangle.2.circlepath"
        case "priority_change": return "exclamationmark.triangle"
        case "assignment": return "person.badge.plus"
        case "reply": return "bubble.left"
        default: return "clock"
        }
    }

    // MARK: - Constants

    private var ticketStatuses: [String] {
        ["Open", "Pending", "In Progress", "Closed"]
    }

    private var ticketPriorities: [String] {
        ["Low", "Normal", "High", "Critical"]
    }

    // MARK: - Loading State

    private var loadingView: some View {
        VStack(spacing: 16) {
            ProgressView()
                .controlSize(.large)
            Text("Loading ticket...")
                .font(.subheadline)
                .foregroundStyle(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .accessibilityLabel("Loading ticket details")
    }

    // MARK: - Error State

    private func errorView(message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle")
                .font(.system(size: 48))
                .foregroundStyle(.orange)

            Text("Unable to Load Ticket")
                .font(.headline)

            Text(message)
                .font(.subheadline)
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal)

            Button {
                Task {
                    await viewModel.loadTicket()
                }
            } label: {
                Label("Retry", systemImage: "arrow.clockwise")
                    .fontWeight(.medium)
            }
            .buttonStyle(.borderedProminent)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Error loading ticket: \(message)")
    }
}

#Preview {
    NavigationStack {
        TicketDetailView(ticketId: 1, apiClient: APIClient(authManager: AuthManager()))
    }
}
