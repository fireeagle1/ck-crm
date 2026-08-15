import SwiftUI

struct AssetDetailView: View {
    @Environment(\.dismiss) private var dismiss
    @State private var asset: AssetDetail?
    @State private var isLoading = true
    @State private var errorMessage: String?
    @State private var showingEditForm = false
    @State private var showingDeleteConfirmation = false
    @State private var showingCreateTicket = false
    @State private var isDeleting = false

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
        .confirmationDialog("Delete Asset", isPresented: $showingDeleteConfirmation, titleVisibility: .visible) {
            Button("Delete", role: .destructive) { Task { await deleteAsset() } }
            Button("Cancel", role: .cancel) {}
        } message: { Text("This cannot be undone.") }
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
                Section("Notes") { Text(notes) }
            }
            Section {
                if let tickets = asset.tickets, !tickets.isEmpty {
                    ForEach(tickets) { t in
                        NavigationLink(destination: TicketDetailView(ticketId: t.ticketId, apiClient: apiClient)) {
                            HStack {
                                VStack(alignment: .leading, spacing: 4) {
                                    Text("INC\(t.ticketId): \(t.subject)")
                                        .font(.subheadline)
                                        .fontWeight(.medium)
                                        .lineLimit(2)
                                    HStack(spacing: 6) {
                                        Text(t.status)
                                            .font(.caption2)
                                            .fontWeight(.medium)
                                            .padding(.horizontal, 6)
                                            .padding(.vertical, 2)
                                            .background(statusColor(t.status).opacity(0.15))
                                            .foregroundStyle(statusColor(t.status))
                                            .clipShape(Capsule())
                                        Text(t.ticketType ?? "Incident")
                                            .font(.caption2)
                                            .foregroundStyle(.secondary)
                                        if let date = t.createdAt {
                                            Spacer()
                                            Text(date)
                                                .font(.caption2)
                                                .foregroundStyle(.tertiary)
                                        }
                                    }
                                }
                                Spacer()
                                Text(t.priority)
                                    .font(.caption2)
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
                        .font(.subheadline)
                        .foregroundStyle(.secondary)
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
                                .font(.caption)
                        }
                    }
                }
            }
            Section {
                Button(role: .destructive) { showingDeleteConfirmation = true } label: {
                    HStack { Spacer(); Label("Delete Asset", systemImage: "trash"); Spacer() }
                }.disabled(isDeleting)
            }
        }.refreshable { await loadAsset() }
    }

    private func row(_ label: String, _ value: String?) -> some View {
        HStack { Text(label).foregroundStyle(.secondary); Spacer(); Text(value ?? "—").foregroundStyle(value != nil ? .primary : .tertiary) }
    }

    private func priorityColor(_ p: String) -> Color {
        switch p.lowercased() { case "critical": return .red; case "high": return .orange; case "normal": return .blue; default: return .gray }
    }

    private func statusColor(_ s: String) -> Color {
        switch s.lowercased() { case "open": return .blue; case "pending": return .orange; case "in progress": return .purple; case "closed": return .green; default: return .gray }
    }

    @MainActor private func loadAsset() async {
        isLoading = true; errorMessage = nil
        do {
            let r: AssetDetailResponse = try await apiClient.request(Endpoint(path: "/admin/assets/\(deviceId)"))
            asset = r.data
        } catch let e as APIError { errorMessage = e.errorDescription } catch { errorMessage = "An unexpected error occurred." }
        isLoading = false
    }

    @MainActor private func deleteAsset() async {
        isDeleting = true
        do { try await apiClient.requestVoid(Endpoint(method: .delete, path: "/admin/assets/\(deviceId)")); dismiss() }
        catch let e as APIError { errorMessage = e.errorDescription } catch { errorMessage = "Failed to delete." }
        isDeleting = false
    }

    private func errorView(_ msg: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle").font(.system(size: 48)).foregroundStyle(.orange)
            Text(msg).font(.subheadline).foregroundStyle(.secondary)
            Button { Task { await loadAsset() } } label: { Label("Retry", systemImage: "arrow.clockwise") }.buttonStyle(.borderedProminent)
        }.frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}
