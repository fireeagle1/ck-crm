import SwiftUI

/// Displays full booking details fetched from `/admin/shop/rentals/{id}`.
///
/// Shows booking info, assigned assets, and inspection records (checkout/return).
struct BookingDetailView: View {
    let bookingId: Int
    let apiClient: APIClient

    @State private var booking: BookingDetail?
    @State private var isLoading = true
    @State private var errorMessage: String?
    @State private var showInspection = false
    @State private var inspectionType: String = "checkout"
    @State private var isAdvancing = false
    @State private var advanceError: String?

    var body: some View {
        Group {
            if isLoading && booking == nil {
                ProgressView("Loading...")
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
                    .background(CKTheme.backgroundPrimary)
            } else if let error = errorMessage, booking == nil {
                errorView(error)
            } else if let booking {
                content(booking)
            }
        }
        .navigationTitle("Booking #\(bookingId)")
        .navigationBarTitleDisplayMode(.large)
        .task { await loadBooking() }
    }

    // MARK: - Content

    @ViewBuilder
    private func content(_ booking: BookingDetail) -> some View {
        List {
            bookingInfoSection(booking)
            fulfilmentStageSection(booking)
            stageActionsSection(booking)
            assignedAssetsSection(booking)
            checkoutInspectionSection(booking)
            returnInspectionSection(booking)
        }
        .listStyle(.plain)
        .scrollContentBackground(.hidden)
        .background(CKTheme.backgroundPrimary)
        .refreshable { await loadBooking() }
        .overlay { advancingOverlay }
        .sheet(isPresented: $showInspection) {
            InspectionUploadView(
                apiClient: apiClient,
                orderId: booking.orderId ?? 0,
                bookingId: bookingId,
                inspectionType: inspectionType,
                onComplete: { Task { await loadBooking() } }
            )
        }
        .alert("Error", isPresented: showAdvanceErrorBinding) {
            Button("OK") { advanceError = nil }
        } message: {
            Text(advanceError ?? "An unknown error occurred.")
        }
    }

    @ViewBuilder
    private var advancingOverlay: some View {
        if isAdvancing {
            ProgressView("Advancing…")
                .padding()
                .background(.ultraThinMaterial)
                .clipShape(RoundedRectangle(cornerRadius: 12))
        }
    }

    // MARK: - Fulfilment Stage Section

    private func fulfilmentStageSection(_ booking: BookingDetail) -> some View {
        Section {
            FulfilmentStageIndicator(currentStage: booking.fulfilmentStage)
        } header: {
            Text("Fulfilment Progress")
                .font(CKTypography.callout)
                .foregroundStyle(CKTheme.textSecondary)
        }
        .listRowBackground(CKTheme.backgroundCard)
    }

    // MARK: - Stage Actions Section

    @ViewBuilder
    private func stageActionsSection(_ booking: BookingDetail) -> some View {
        let actions = StageAction.actions(for: booking.fulfilmentStage)
        if !actions.isEmpty {
            Section {
                ForEach(actions) { action in
                    actionButton(action)
                }
            } header: {
                Text("Actions")
                    .font(CKTypography.callout)
                    .foregroundStyle(CKTheme.textSecondary)
            }
            .listRowBackground(CKTheme.backgroundCard)
        }
    }

    @ViewBuilder
    private func actionButton(_ action: StageAction) -> some View {
        if action.isPrimary {
            Button {
                handleAction(action)
            } label: {
                Label(action.title, systemImage: action.icon)
                    .frame(maxWidth: .infinity)
            }
            .buttonStyle(.borderedProminent)
            .tint(CKTheme.accent)
            .controlSize(.large)
        } else {
            Button {
                handleAction(action)
            } label: {
                Label(action.title, systemImage: action.icon)
                    .frame(maxWidth: .infinity)
            }
            .buttonStyle(.bordered)
            .controlSize(.large)
        }
    }

    // MARK: - Booking Info Section

    private func bookingInfoSection(_ booking: BookingDetail) -> some View {
        Section {
            infoRow("Product", booking.productName)
            infoRow("Customer", booking.customerName)
            infoRow("Start Date", booking.startDate.map { formattedDate($0) })
            infoRow("End Date", booking.endDate.map { formattedDate($0) })
            infoRow("Quantity", "\(booking.quantity)")
            infoRow("Total Price", formattedPrice(booking.totalPrice))
            statusRow("Status", booking.status, statusColor(booking.status))
            statusRow("Fulfilment Stage", booking.fulfilmentStage.replacingOccurrences(of: "_", with: " "), stageColor(booking.fulfilmentStage))
        } header: {
            Text("Booking Info")
                .font(CKTypography.callout)
                .foregroundStyle(CKTheme.textSecondary)
        }
        .listRowBackground(CKTheme.backgroundCard)
    }

    // MARK: - Assigned Assets Section

    private func assignedAssetsSection(_ booking: BookingDetail) -> some View {
        Section {
            if booking.assignedAssets.isEmpty {
                Text("No assets assigned")
                    .font(CKTypography.body)
                    .foregroundStyle(CKTheme.textTertiary)
            } else {
                ForEach(booking.assignedAssets) { asset in
                    assetRow(asset)
                }
            }
        } header: {
            Text("Assigned Assets")
                .font(CKTypography.callout)
                .foregroundStyle(CKTheme.textSecondary)
        }
        .listRowBackground(CKTheme.backgroundCard)
    }

    private func assetRow(_ asset: AssignedAsset) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(asset.deviceName ?? "Unknown Device")
                .font(CKTypography.headline)
                .foregroundStyle(CKTheme.textPrimary)
            HStack(spacing: 12) {
                if let serial = asset.serialNumber {
                    Label(serial, systemImage: "barcode")
                        .font(CKTypography.caption)
                        .foregroundStyle(CKTheme.textSecondary)
                }
                if let status = asset.status {
                    Text(status.capitalized)
                        .font(CKTypography.caption)
                        .fontWeight(.medium)
                        .padding(.horizontal, 6)
                        .padding(.vertical, 2)
                        .background(CKTheme.textSecondary.opacity(0.12))
                        .foregroundStyle(CKTheme.textSecondary)
                        .clipShape(Capsule())
                }
            }
        }
        .padding(.vertical, 2)
    }

    // MARK: - Inspection Sections

    @ViewBuilder
    private func checkoutInspectionSection(_ booking: BookingDetail) -> some View {
        if let inspection = booking.checkoutInspection {
            inspectionSection(title: "Checkout Inspection", inspection: inspection)
        }
    }

    @ViewBuilder
    private func returnInspectionSection(_ booking: BookingDetail) -> some View {
        if let inspection = booking.returnInspection {
            inspectionSection(title: "Return Inspection", inspection: inspection)
        }
    }

    private func inspectionSection(title: String, inspection: InspectionRecord) -> some View {
        Section {
            if !inspection.photos.isEmpty {
                ScrollView(.horizontal, showsIndicators: false) {
                    HStack(spacing: 8) {
                        ForEach(inspection.photos, id: \.self) { photoPath in
                            AsyncImage(url: photoURL(photoPath)) { phase in
                                switch phase {
                                case .success(let image):
                                    image.resizable().scaledToFill()
                                case .failure:
                                    Image(systemName: "photo").foregroundStyle(.gray)
                                default:
                                    ProgressView()
                                }
                            }
                            .frame(width: 80, height: 80)
                            .clipShape(RoundedRectangle(cornerRadius: 8))
                        }
                    }
                    .padding(.vertical, 4)
                }
            }
            infoRow("Condition Notes", inspection.conditionNotes)
            damageRow(inspection.damageFlagged)
            infoRow("Inspector", inspection.inspectorName)
            if let inspectedAt = inspection.inspectedAt {
                infoRow("Date", formattedDateTime(inspectedAt))
            }
            Button(role: .destructive) {
                Task { await deleteInspection(type: title.contains("Checkout") ? "checkout" : "return") }
            } label: {
                Label("Delete Inspection", systemImage: "trash")
            }
        } header: {
            Text(title)
                .font(CKTypography.callout)
                .foregroundStyle(CKTheme.textSecondary)
        }
        .listRowBackground(CKTheme.backgroundCard)
    }

    private func damageRow(_ flagged: Bool?) -> some View {
        HStack {
            Text("Damage Flagged")
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
            Spacer()
            if flagged == true {
                Label("Yes", systemImage: "exclamationmark.triangle.fill")
                    .font(CKTypography.body)
                    .foregroundStyle(CKTheme.error)
            } else {
                Text("No")
                    .font(CKTypography.body)
                    .foregroundStyle(CKTheme.success)
            }
        }
    }

    // MARK: - Helper Views

    private func infoRow(_ label: String, _ value: String?) -> some View {
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

    private func statusRow(_ label: String, _ value: String, _ color: Color) -> some View {
        HStack {
            Text(label)
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
            Spacer()
            Text(value.capitalized)
                .font(CKTypography.caption)
                .fontWeight(.medium)
                .padding(.horizontal, 8)
                .padding(.vertical, 3)
                .background(color.opacity(0.15))
                .foregroundStyle(color)
                .clipShape(Capsule())
        }
    }

    private func errorView(_ message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle")
                .font(.system(size: 48))
                .foregroundStyle(CKTheme.warning)
            Text(message)
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
                .multilineTextAlignment(.center)
            Button {
                Task { await loadBooking() }
            } label: {
                Label("Retry", systemImage: "arrow.clockwise")
            }
            .buttonStyle(.borderedProminent)
            .tint(CKTheme.accent)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(CKTheme.backgroundPrimary)
    }

    // MARK: - Action Handling

    private func handleAction(_ action: StageAction) {
        switch action {
        case .checkOut:
            inspectionType = "checkout"
            showInspection = true
        case .returnInspection, .completeInspection:
            inspectionType = "return"
            showInspection = true
        case .advanceToPacking, .markReady, .markReturned:
            Task { await advanceStage() }
        }
    }

    @MainActor
    private func advanceStage() async {
        guard let booking else { return }
        isAdvancing = true
        do {
            let _: MessageResponse = try await apiClient.request(
                Endpoint(
                    method: .post,
                    path: "/admin/shop/orders/\(booking.orderId ?? 0)/bookings/\(bookingId)/advance-stage"
                )
            )
            await loadBooking()
        } catch let error as APIError {
            advanceError = error.errorDescription
        } catch {
            advanceError = "Failed to advance stage."
        }
        isAdvancing = false
    }

    @MainActor
    private func deleteInspection(type: String) async {
        guard let booking else { return }
        isAdvancing = true
        do {
            let _: MessageResponse = try await apiClient.request(
                Endpoint(
                    method: .delete,
                    path: "/admin/shop/orders/\(booking.orderId ?? 0)/bookings/\(bookingId)/inspection/\(type)"
                )
            )
            await loadBooking()
        } catch let error as APIError {
            advanceError = error.errorDescription
        } catch {
            advanceError = "Failed to delete inspection."
        }
        isAdvancing = false
    }

    private func photoURL(_ path: String) -> URL? {
        APIConfig.baseURL.appendingPathComponent("/api/admin/shop/bookings/inspection-photo/\(path)")
    }

    private var showAdvanceErrorBinding: Binding<Bool> {
        Binding(
            get: { advanceError != nil },
            set: { if !$0 { advanceError = nil } }
        )
    }

    // MARK: - Data Loading

    @MainActor
    private func loadBooking() async {
        isLoading = true
        errorMessage = nil
        do {
            let response: BookingDetailResponse = try await apiClient.request(
                Endpoint(path: "/admin/shop/rentals/\(bookingId)")
            )
            booking = response.data
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "An unexpected error occurred."
        }
        isLoading = false
    }

    // MARK: - Formatting Helpers

    private func formattedDate(_ dateString: String) -> String {
        let formatter = DateFormatter()
        formatter.dateFormat = "yyyy-MM-dd"
        formatter.locale = Locale(identifier: "en_US_POSIX")
        guard let date = formatter.date(from: dateString) else { return dateString }
        let output = DateFormatter()
        output.dateStyle = .medium
        return output.string(from: date)
    }

    private func formattedDateTime(_ date: Date) -> String {
        let formatter = DateFormatter()
        formatter.dateStyle = .medium
        formatter.timeStyle = .short
        return formatter.string(from: date)
    }

    private func formattedPrice(_ price: Double) -> String {
        let formatter = NumberFormatter()
        formatter.numberStyle = .currency
        formatter.currencyCode = "GBP"
        return formatter.string(from: NSNumber(value: price)) ?? "£\(String(format: "%.2f", price))"
    }

    private func statusColor(_ status: String) -> Color {
        switch status.lowercased() {
        case "active": return CKTheme.success
        case "pending": return CKTheme.warning
        case "cancelled": return CKTheme.error
        case "completed": return CKTheme.info
        default: return CKTheme.textTertiary
        }
    }

    private func stageColor(_ stage: String) -> Color {
        switch stage.lowercased() {
        case "ordered": return CKTheme.info
        case "packing": return CKTheme.warning
        case "ready", "checked_out": return CKTheme.accent
        case "returned": return CKTheme.success
        case "inspected": return CKTheme.textSecondary
        default: return CKTheme.textTertiary
        }
    }
}

// MARK: - StageAction

/// Contextual actions available for a Booking based on its current fulfilment stage.
enum StageAction: Identifiable {
    case advanceToPacking
    case markReady
    case checkOut
    case markReturned
    case returnInspection
    case completeInspection

    var id: String {
        switch self {
        case .advanceToPacking: return "advance_packing"
        case .markReady: return "mark_ready"
        case .checkOut: return "check_out"
        case .markReturned: return "mark_returned"
        case .returnInspection: return "return_inspection"
        case .completeInspection: return "complete_inspection"
        }
    }

    var title: String {
        switch self {
        case .advanceToPacking: return "Advance to Packing"
        case .markReady: return "Mark Ready"
        case .checkOut: return "Check Out"
        case .markReturned: return "Mark Returned"
        case .returnInspection: return "Return Inspection"
        case .completeInspection: return "Complete Inspection"
        }
    }

    var icon: String {
        switch self {
        case .advanceToPacking: return "shippingbox"
        case .markReady: return "checkmark.circle"
        case .checkOut: return "person.fill.checkmark"
        case .markReturned: return "arrow.uturn.backward"
        case .returnInspection: return "camera.fill"
        case .completeInspection: return "camera.fill"
        }
    }

    var isPrimary: Bool {
        switch self {
        case .advanceToPacking, .markReady, .checkOut, .returnInspection, .completeInspection: return true
        case .markReturned: return false
        }
    }

    static func actions(for stage: String) -> [StageAction] {
        switch stage {
        case "ordered": return [.advanceToPacking]
        case "packing": return [.markReady]
        case "ready": return [.checkOut]
        case "checked_out": return [.markReturned, .returnInspection]
        case "returned": return [.completeInspection]
        case "inspected": return []
        default: return []
        }
    }
}

// MARK: - StageConfig

struct StageConfig {
    let stage: String
    let label: String
    let color: Color
    let iconName: String

    static let allStages: [StageConfig] = [
        StageConfig(stage: "ordered", label: "Ordered", color: .gray, iconName: "clock"),
        StageConfig(stage: "packing", label: "Packing", color: .blue, iconName: "shippingbox"),
        StageConfig(stage: "ready", label: "Ready", color: .green, iconName: "checkmark.circle"),
        StageConfig(stage: "checked_out", label: "Checked Out", color: .teal, iconName: "person.fill.checkmark"),
        StageConfig(stage: "returned", label: "Returned", color: .orange, iconName: "arrow.uturn.backward"),
        StageConfig(stage: "inspected", label: "Inspected", color: .green, iconName: "checkmark.seal.fill"),
    ]

    static func indexOf(_ stage: String) -> Int {
        allStages.firstIndex(where: { $0.stage == stage }) ?? 0
    }
}

// MARK: - FulfilmentStageIndicator

struct FulfilmentStageIndicator: View {
    let currentStage: String

    private var currentIndex: Int { StageConfig.indexOf(currentStage) }

    var body: some View {
        HStack(spacing: 4) {
            ForEach(Array(StageConfig.allStages.enumerated()), id: \.element.stage) { index, config in
                VStack(spacing: 4) {
                    ZStack {
                        Circle()
                            .fill(bgColor(index))
                            .frame(width: 28, height: 28)
                        Image(systemName: index < currentIndex ? "checkmark" : config.iconName)
                            .font(.system(size: 12, weight: .semibold))
                            .foregroundStyle(fgColor(index, config: config))
                    }
                    Text(config.label)
                        .font(.system(size: 8, weight: index == currentIndex ? .semibold : .regular))
                        .foregroundStyle(index <= currentIndex ? CKTheme.textPrimary : CKTheme.textTertiary)
                        .lineLimit(1)
                        .minimumScaleFactor(0.7)
                }
                .frame(maxWidth: .infinity)
                if index < StageConfig.allStages.count - 1 {
                    Rectangle()
                        .fill(index < currentIndex ? Color.green.opacity(0.6) : Color.gray.opacity(0.2))
                        .frame(height: 2)
                        .frame(maxWidth: 10)
                        .offset(y: -6)
                }
            }
        }
        .padding(.vertical, 8)
    }

    private func bgColor(_ index: Int) -> Color {
        let c = StageConfig.allStages[index].color
        if index == currentIndex { return c.opacity(0.2) }
        if index < currentIndex { return c.opacity(0.1) }
        return Color.gray.opacity(0.08)
    }

    private func fgColor(_ index: Int, config: StageConfig) -> Color {
        if index == currentIndex { return config.color }
        if index < currentIndex { return config.color.opacity(0.7) }
        return Color.gray.opacity(0.4)
    }
}
