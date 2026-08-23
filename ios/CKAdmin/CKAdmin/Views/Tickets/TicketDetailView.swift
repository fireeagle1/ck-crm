import SwiftUI

struct TicketDetailView: View {
    @State private var viewModel: TicketDetailViewModel
    private let apiClient: APIClient

    init(ticketId: Int, apiClient: APIClient) {
        self.apiClient = apiClient
        _viewModel = State(initialValue: TicketDetailViewModel(ticketId: ticketId, apiClient: apiClient))
    }

    var body: some View {
        Group {
            if viewModel.isLoading && viewModel.ticket == nil {
                ProgressView("Loading...").frame(maxWidth: .infinity, maxHeight: .infinity)
            } else if let err = viewModel.errorMessage, viewModel.ticket == nil {
                errorView(err)
            } else if let ticket = viewModel.ticket {
                ticketContent(ticket)
            } else {
                // Fallback: trigger reload if we ended up in an unexpected state
                ProgressView("Loading...")
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
                    .task { await viewModel.loadTicket() }
            }
        }
        .navigationTitle(viewModel.ticket.map { "INC\($0.ticketId)" } ?? "Ticket")
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            if viewModel.ticket != nil { ToolbarItem(placement: .topBarTrailing) { actionsMenu } }
        }
        .task { await viewModel.loadTicket() }
    }

    // MARK: - Actions Menu

    private var actionsMenu: some View {
        Menu {
            Menu { ForEach(["Open", "Pending", "In Progress", "Closed"], id: \.self) { s in Button(s) { Task { await viewModel.updateStatus(s) } }.disabled(s == viewModel.ticket?.status) } } label: { Label("Status", systemImage: "arrow.triangle.2.circlepath") }
            Menu { ForEach(["Low", "Normal", "High", "Critical"], id: \.self) { p in Button(p) { Task { await viewModel.updatePriority(p) } }.disabled(p == viewModel.ticket?.priority) } } label: { Label("Priority", systemImage: "exclamationmark.triangle") }
            Menu { ForEach(["Incident", "Service Request"], id: \.self) { t in Button(t) { Task { await viewModel.updateType(t) } }.disabled(t == viewModel.ticket?.ticketType) } } label: { Label("Type", systemImage: "tag") }
        } label: { Label("Actions", systemImage: "ellipsis.circle") }
        .disabled(viewModel.isUpdating)
    }

    // MARK: - Content

    private func ticketContent(_ ticket: TicketDetail) -> some View {
        @Bindable var vm = viewModel
        return VStack(spacing: 0) {
            List {
                headerSection(ticket)
                if !ticket.replies.isEmpty { repliesSection(ticket.replies) }
                if !ticket.activities.isEmpty { activitySection(ticket.activities) }
            }
            .listStyle(.insetGrouped)
            .scrollContentBackground(.hidden)
            .background(CKTheme.backgroundPrimary)
            .refreshable { await viewModel.loadTicket() }

            replyComposer(replyText: $vm.replyText, isInternal: $vm.isInternalNote)
        }
    }

    // MARK: - Header

    private func headerSection(_ ticket: TicketDetail) -> some View {
        Section("Details") {
            HStack { badge(ticket.status, color: statusColor(ticket.status)); Spacer(); badge(ticket.priority, color: priorityColor(ticket.priority)) }
            if let type = ticket.ticketType { row("Type", type) }
            if let desc = ticket.description, !desc.isEmpty {
                VStack(alignment: .leading, spacing: 4) { Text("Description").font(CKTypography.caption).foregroundStyle(CKTheme.textSecondary); Text(desc).font(CKTypography.body).foregroundStyle(CKTheme.textPrimary) }
            }
            if let c = ticket.customerName { row("Customer", c) }
            if let u = ticket.assignedUserName { row("Assigned To", u) }
            if let a = ticket.assetName { row("Asset", a) }
            if let s = ticket.serviceName { row("Service", s) }
            if let cat = ticket.requestCategory, !cat.isEmpty { row("Category", cat) }
            if let created = ticket.createdAt { HStack { Text("Created").font(CKTypography.body).foregroundStyle(CKTheme.textSecondary); Spacer(); Text(created, style: .relative).font(CKTypography.body).foregroundStyle(CKTheme.textSecondary) } }
        }
    }

    // MARK: - Replies

    private func repliesSection(_ replies: [TicketReply]) -> some View {
        Section("Conversation (\(replies.count))") {
            ForEach(replies) { reply in
                VStack(alignment: .leading, spacing: 6) {
                    HStack {
                        Text(reply.userName ?? "Unknown").font(CKTypography.headline).foregroundStyle(CKTheme.textPrimary)
                        if reply.isInternal == true {
                            Text("Internal").font(.caption2).fontWeight(.medium).padding(.horizontal, 6).padding(.vertical, 1)
                                .background(CKTheme.warning.opacity(0.2)).foregroundStyle(CKTheme.warning).clipShape(Capsule())
                        }
                        Spacer()
                        if let t = reply.createdAt { Text(t, style: .relative).font(CKTypography.caption).foregroundStyle(CKTheme.textTertiary) }
                    }
                    Text(reply.body).font(CKTypography.body).foregroundStyle(CKTheme.textPrimary)
                }.padding(.vertical, 4)
            }
        }
    }

    // MARK: - Activity

    private func activitySection(_ activities: [TicketActivity]) -> some View {
        Section("Activity (\(activities.count))") {
            ForEach(activities) { activity in
                HStack(alignment: .top, spacing: 10) {
                    Image(systemName: activityIcon(activity.type)).font(CKTypography.caption).foregroundStyle(CKTheme.textTertiary).frame(width: 20)
                    VStack(alignment: .leading, spacing: 2) {
                        if let old = activity.oldValue, let new = activity.newValue {
                            Text("\(activity.type?.replacingOccurrences(of: "_", with: " ").capitalized ?? "Change"): \(old) → \(new)").font(CKTypography.body).foregroundStyle(CKTheme.textPrimary)
                        }
                        HStack(spacing: 4) {
                            if let u = activity.userName { Text(u).font(CKTypography.caption).foregroundStyle(CKTheme.textSecondary) }
                            if let t = activity.createdAt { Text("·").font(CKTypography.caption).foregroundStyle(CKTheme.textTertiary); Text(t, style: .relative).font(CKTypography.caption).foregroundStyle(CKTheme.textSecondary) }
                        }
                    }
                    Spacer()
                }.padding(.vertical, 2)
            }
        }
    }

    // MARK: - Reply Composer

    private func replyComposer(replyText: Binding<String>, isInternal: Binding<Bool>) -> some View {
        VStack(spacing: 0) {
            Divider()
            VStack(spacing: 8) {
                HStack {
                    Toggle("Internal note", isOn: isInternal)
                        .font(CKTypography.caption)
                        .toggleStyle(.switch)
                        .controlSize(.mini)
                }
                HStack(alignment: .bottom, spacing: 10) {
                    TextField("Write a reply...", text: replyText, axis: .vertical)
                        .lineLimit(1...5)
                        .textFieldStyle(.plain)
                        .font(CKTypography.body)
                        .padding(10)
                        .background(CKTheme.backgroundSecondary)
                        .clipShape(RoundedRectangle(cornerRadius: 10))
                    Button { Task { await viewModel.sendReply() } } label: {
                        if viewModel.isSendingReply { ProgressView().controlSize(.small) }
                        else { Image(systemName: "arrow.up.circle.fill").font(.title2).foregroundStyle(CKTheme.accent) }
                    }
                    .disabled(viewModel.replyText.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty || viewModel.isSendingReply)
                }
            }
            .padding(.horizontal)
            .padding(.vertical, 8)
            .background(CKTheme.backgroundCard)
        }
    }

    // MARK: - Helpers

    private func row(_ label: String, _ value: String) -> some View {
        HStack { Text(label).font(CKTypography.body).foregroundStyle(CKTheme.textSecondary); Spacer(); Text(value).font(CKTypography.body).foregroundStyle(CKTheme.textPrimary) }
    }

    private func badge(_ text: String, color: Color) -> some View {
        Text(text).font(CKTypography.caption).fontWeight(.medium).padding(.horizontal, 10).padding(.vertical, 4)
            .background(color.opacity(0.15)).foregroundStyle(color).clipShape(Capsule())
    }

    private func statusColor(_ s: String) -> Color {
        switch s.lowercased() { case "open": return .blue; case "pending": return .orange; case "in progress": return .purple; case "closed": return .green; default: return .gray }
    }

    private func priorityColor(_ p: String) -> Color {
        switch p.lowercased() { case "low": return .gray; case "normal": return .blue; case "high": return .orange; case "critical": return .red; default: return .gray }
    }

    private func activityIcon(_ type: String?) -> String {
        switch type { case "status_changed": return "arrow.triangle.2.circlepath"; case "priority_changed": return "exclamationmark.triangle"; case "assigned_changed": return "person.badge.plus"; case "type_changed": return "tag"; default: return "clock" }
    }

    private func errorView(_ msg: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle").font(.system(size: 48)).foregroundStyle(CKTheme.warning)
            Text(msg).font(CKTypography.body).foregroundStyle(CKTheme.textSecondary)
            Button { Task { await viewModel.loadTicket() } } label: { Label("Retry", systemImage: "arrow.clockwise") }.buttonStyle(.borderedProminent)
        }.frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(CKTheme.backgroundPrimary)
    }
}
