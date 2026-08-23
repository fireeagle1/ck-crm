import SwiftUI

struct AssetDetailView: View {
    @State private var asset: AssetDetail?
    @State private var isLoading = true
    @State private var errorMessage: String?
    @State private var showingEditForm = false
    @State private var showingCreateTicket = false

    private let deviceId: Int
    private let apiClient: APIClient

    init(deviceId: Int, apiClient: APIClient) {
        self.deviceId = deviceId
        self.apiClient = apiClient
    }

    var body: some View {
        Group {
            if isLoading && asset == nil { ProgressView("Loading...").frame(maxWidth: .infinity, maxHeight: .infinity) }
            else if let err = errorMessage, asset == nil { errorView(err) }
            else if let asset { content(asset) }
        }
        .navigationTitle(asset?.deviceName ?? "Asset")
        .navigationBarTitleDisplayMode(.large)
        .toolbar {
            if asset != nil {
                ToolbarItem(placement: .topBarTrailing) { Button { showingEditForm = true } label: { Label("Edit", systemImage: "pencil") } }
            }
        }
        .sheet(isPresented: $showingEditForm) {
            if let asset {
                AssetFormView(mode: .edit(asset), apiClient: apiClient) { _ in await loadAsset() }
            }
        }
        .sheet(isPresented: $showingCreateTicket) {
            if let asset {
                TicketCreateView(apiClient: apiClient, prefilledCustomerId: asset.customerId) {
                    await loadAsset()
                }
            }
        }
        .task { await loadAsset() }
    }

    private func content(_ asset: AssetDetail) -> some View {
        List {
            Section("Details") {
                row("Name", asset.deviceName)
                row("Type", asset.deviceType)
                row("Status", asset.assetStatus)
                row("Serial Number", asset.serialNumber)
                row("Location", asset.location)
                row("Customer", asset.customerName)
            }
            if let notes = asset.notes, !notes.isEmpty {
                Section("Notes") {
                    Text(notes)
                        .font(CKTypography.body)
                        .foregroundStyle(CKTheme.textPrimary)
                }
            }
            // MARK: - Recent Inspections
            Section("Recent Inspections") {
                if let inspections = asset.recentInspections, !inspections.isEmpty {
                    ForEach(inspections) { inspection in
                        HStack(spacing: 10) {
                            Text(inspection.type.capitalized)
                                .font(CKTypography.caption)
                                .fontWeight(.medium)
                                .padding(.horizontal, 8)
                                .padding(.vertical, 3)
                                .background(inspection.type == "checkout" ? CKTheme.info.opacity(0.15) : CKTheme.success.opacity(0.15))
                                .foregroundStyle(inspection.type == "checkout" ? CKTheme.info : CKTheme.success)
                                .clipShape(Capsule())

                            VStack(alignment: .leading, spacing: 2) {
                                Text(inspection.inspectorName ?? "Unknown")
                                    .font(CKTypography.body)
                                    .foregroundStyle(CKTheme.textPrimary)
                                Text(inspection.inspectedAt)
                                    .font(CKTypography.caption)
                                    .foregroundStyle(CKTheme.textSecondary)
                            }

                            Spacer()

                            if inspection.damageFlagged {
                                Image(systemName: "exclamationmark.triangle.fill")
                                    .foregroundStyle(CKTheme.error)
                                    .accessibilityLabel("Damage flagged")
                            }
                        }
                    }
                } else {
                    Text("No inspections recorded")
                        .font(CKTypography.body)
                        .foregroundStyle(CKTheme.textSecondary)
                }
            }

            if let booking = asset.currentBooking {
                Section("Rental Status") {
                    NavigationLink(destination: BookingDetailView(bookingId: booking.id, apiClient: apiClient)) {
                        VStack(alignment: .leading, spacing: 8) {
                            HStack {
                                Text(booking.fulfilmentStage.replacingOccurrences(of: "_", with: " ").capitalized)
                                    .font(CKTypography.caption)
                                    .fontWeight(.semibold)
                                    .padding(.horizontal, 8)
                                    .padding(.vertical, 4)
                                    .background(fulfilmentStageColor(booking.fulfilmentStage).opacity(0.15))
                                    .foregroundStyle(fulfilmentStageColor(booking.fulfilmentStage))
                                    .clipShape(Capsule())
                                Spacer()
                            }
                            HStack(spacing: 4) {
                                Image(systemName: "calendar")
                                    .font(CKTypography.caption)
                                    .foregroundStyle(CKTheme.textSecondary)
                                Text("\(booking.startDate) – \(booking.endDate)")
                                    .font(CKTypography.body)
                                    .foregroundStyle(CKTheme.textPrimary)
                            }
                            if let customerName = booking.customerName {
                                HStack(spacing: 4) {
                                    Image(systemName: "person")
                                        .font(CKTypography.caption)
                                        .foregroundStyle(CKTheme.textSecondary)
                                    Text(customerName)
                                        .font(CKTypography.body)
                                        .foregroundStyle(CKTheme.textSecondary)
                                }
                            }
                        }
                        .padding(.vertical, 4)
                    }
                }
            }

            Section {
                if let tickets = asset.tickets, !tickets.isEmpty {
                    ForEach(tickets) { t in
                        NavigationLink(destination: TicketDetailView(ticketId: t.ticketId, apiClient: apiClient)) {
                            HStack {
                                VStack(alignment: .leading, spacing: 4) {
                                    Text("INC\(t.ticketId): \(t.subject)")
                                        .font(CKTypography.body)
                                        .fontWeight(.medium)
                                        .foregroundStyle(CKTheme.textPrimary)
                                        .lineLimit(2)
                                    HStack(spacing: 6) {
                                        Text(t.status)
                                            .font(CKTypography.caption)
                                            .fontWeight(.medium)
                                            .padding(.horizontal, 6)
                                            .padding(.vertical, 2)
                                            .background(statusColor(t.status).opacity(0.15))
                                            .foregroundStyle(statusColor(t.status))
                                            .clipShape(Capsule())
                                        Text(t.ticketType ?? "Incident")
                                            .font(CKTypography.caption)
                                            .foregroundStyle(CKTheme.textSecondary)
                                        if let date = t.createdAt {
                                            Spacer()
                                            Text(date)
                                                .font(CKTypography.caption)
                                                .foregroundStyle(CKTheme.textTertiary)
                                        }
                                    }
                                }
                                Spacer()
                                Text(t.priority)
                                    .font(CKTypography.caption)
                                    .fontWeight(.medium)
                                    .padding(.horizontal, 6)
                                    .padding(.vertical, 2)
                                    .background(priorityColor(t.priority).opacity(0.15))
                                    .foregroundStyle(priorityColor(t.priority))
                                    .clipShape(Capsule())
                            }
                        }
                    }
                } else {
                    Text("No tickets logged against this asset.")
                        .font(CKTypography.body)
                        .foregroundStyle(CKTheme.textSecondary)
                }
            } header: {
                HStack {
                    Text("Linked Incidents (\(asset.tickets?.count ?? 0))")
                    Spacer()
                    if asset.customerId != nil {
                        Button {
                            showingCreateTicket = true
                        } label: {
                            Label("Log Ticket", systemImage: "plus.circle")
                                .font(CKTypography.caption)
                        }
                    }
                }
            }
        }
        .scrollContentBackground(.hidden)
        .background(CKTheme.backgroundPrimary)
        .refreshable { await loadAsset() }
    }

    private func row(_ label: String, _ value: String?) -> some View {
        HStack {
            Text(label)
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
            Spacer()
            Text(value ?? "—")
                .font(CKTypography.body)
                .foregroundStyle(value != nil ? CKTheme.textPrimary : CKTheme.textTertiary)
        }
    }

    private func priorityColor(_ p: String) -> Color {
        switch p.lowercased() { case "critical": return CKTheme.error; case "high": return CKTheme.warning; case "normal": return CKTheme.info; default: return CKTheme.textTertiary }
    }

    private func statusColor(_ s: String) -> Color {
        switch s.lowercased() { case "open": return CKTheme.info; case "pending": return CKTheme.warning; case "in progress": return .purple; case "closed": return CKTheme.success; default: return CKTheme.textTertiary }
    }

    private func fulfilmentStageColor(_ stage: String) -> Color {
        switch stage.lowercased() {
        case "ordered": return CKTheme.info
        case "packing": return .purple
        case "ready": return .indigo
        case "checked_out": return CKTheme.accent
        case "returned": return CKTheme.warning
        case "inspected": return CKTheme.success
        default: return CKTheme.textTertiary
        }
    }

    @MainActor private func loadAsset() async {
        isLoading = true; errorMessage = nil
        do {
            let r: AssetDetailResponse = try await apiClient.request(Endpoint(path: "/admin/assets/\(deviceId)"))
            asset = r.data
        } catch let e as APIError { errorMessage = e.errorDescription } catch { errorMessage = "An unexpected error occurred." }
        isLoading = false
    }

    private func errorView(_ msg: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle").font(.system(size: 48)).foregroundStyle(CKTheme.warning)
            Text(msg).font(CKTypography.body).foregroundStyle(CKTheme.textSecondary)
            Button { Task { await loadAsset() } } label: { Label("Retry", systemImage: "arrow.clockwise") }.buttonStyle(.borderedProminent).tint(CKTheme.accent)
        }.frame(maxWidth: .infinity, maxHeight: .infinity).background(CKTheme.backgroundPrimary)
    }
}
