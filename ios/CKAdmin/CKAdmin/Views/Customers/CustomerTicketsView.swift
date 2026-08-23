import SwiftUI

/// Shows tickets belonging to a specific customer.
struct CustomerTicketsView: View {
    @State private var tickets: [TicketListItem] = []
    @State private var isLoading = true
    @State private var errorMessage: String?

    private let companyId: Int
    private let apiClient: APIClient

    init(companyId: Int, apiClient: APIClient) {
        self.companyId = companyId
        self.apiClient = apiClient
    }

    var body: some View {
        Group {
            if isLoading {
                ProgressView("Loading tickets...")
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
            } else if let errorMessage {
                errorView(errorMessage)
            } else if tickets.isEmpty {
                ContentUnavailableView("No Tickets", systemImage: "ticket", description: Text("No tickets found for this customer."))
            } else {
                ticketList
            }
        }
        .navigationTitle("Tickets")
        .navigationBarTitleDisplayMode(.inline)
        .background(CKTheme.backgroundPrimary)
        .task { await loadTickets() }
    }

    private var ticketList: some View {
        List(tickets) { ticket in
            NavigationLink(destination: TicketDetailView(ticketId: ticket.ticketId, apiClient: apiClient)) {
                VStack(alignment: .leading, spacing: 4) {
                    Text(ticket.subject)
                        .font(CKTypography.headline)
                        .foregroundStyle(CKTheme.textPrimary)
                        .lineLimit(2)
                    HStack {
                        Text(ticket.status)
                            .font(.caption2)
                            .fontWeight(.medium)
                            .padding(.horizontal, 6)
                            .padding(.vertical, 2)
                            .background(statusColor(ticket.status).opacity(0.15))
                            .foregroundStyle(statusColor(ticket.status))
                            .clipShape(Capsule())
                        Text(ticket.priority)
                            .font(.caption2)
                            .fontWeight(.medium)
                            .padding(.horizontal, 6)
                            .padding(.vertical, 2)
                            .background(priorityColor(ticket.priority).opacity(0.15))
                            .foregroundStyle(priorityColor(ticket.priority))
                            .clipShape(Capsule())
                        Spacer()
                        Text(ticket.createdAt, style: .relative)
                            .font(CKTypography.caption)
                            .foregroundStyle(CKTheme.textTertiary)
                    }
                }
                .padding(.vertical, 2)
            }
        }
        .listStyle(.plain)
        .scrollContentBackground(.hidden)
        .background(CKTheme.backgroundPrimary)
    }

    private func statusColor(_ s: String) -> Color {
        switch s.lowercased() {
        case "open": return .blue
        case "pending": return .orange
        case "in progress": return .purple
        case "closed": return .green
        default: return .gray
        }
    }

    private func priorityColor(_ p: String) -> Color {
        switch p.lowercased() {
        case "critical": return .red
        case "high": return .orange
        case "normal": return .blue
        default: return .gray
        }
    }

    @MainActor
    private func loadTickets() async {
        isLoading = true
        errorMessage = nil
        do {
            let endpoint = Endpoint(path: "/admin/tickets", queryItems: ["customer_id": String(companyId)])
            let response: PaginatedResponse<TicketListItem> = try await apiClient.request(endpoint)
            tickets = response.data
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "An unexpected error occurred."
        }
        isLoading = false
    }

    private func errorView(_ message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle").font(.system(size: 48)).foregroundStyle(CKTheme.warning)
            Text(message).font(CKTypography.body).foregroundStyle(CKTheme.textSecondary)
            Button { Task { await loadTickets() } } label: { Label("Retry", systemImage: "arrow.clockwise") }.buttonStyle(.borderedProminent).tint(CKTheme.accent)
        }.frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}
