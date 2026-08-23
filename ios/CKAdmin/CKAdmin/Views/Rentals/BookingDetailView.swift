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
    @State private var showCheckoutInspection = false
    @State private var showReturnInspection = false
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

    private func content(_ booking: BookingDetail) -> some View {
        List {
            bookingInfoSection(booking)
            fulfilmentStageSection(booking)
            stageActionsSection(booking)
            assignedAssetsSection(booking)
            if let inspection = booking.checkoutInspection {
                inspectionSection(title: "Checkout Inspection", inspection: inspection)
            }
            if let inspection = booking.returnInspection {
                inspectionSection(title: "Return Inspection", inspection: inspection)
            }
        }
        .listStyle(.plain)
        .scrollContentBackground(.hidden)
        .background(CKTheme.backgroundPrimary)
        .refreshable { await loadBooking() }
        .overlay {
            if isAdvancing {
                ProgressView("Advancing…")
                    .padding()
                    .background(.ultraThinMaterial)
                    .clipShape(RoundedRectangle(cornerRadius: 12))
            }
        }
        .sheet(isPresented: $showCheckoutInspection) {
            CheckoutInspectionView(
                orderId: booking.orderId ?? 0,
                bookingId: bookingId,
                agreementText: booking.agreementText,
                apiClient: apiClient,
                onComplete: { Task { await loadBooking() } }
            )
        }
        .sheet(isPresented: $showReturnInspection) {
            ReturnInspectionView(
                orderId: booking.orderId ?? 0,
                bookingId: bookingId,
                apiClient: apiClient,
                onComplete: { Task { await loadBooking() } }
            )
        }
        .alert("Error", isPresented: showAdvanceErrorBinding) {
            Button("OK") { advanceError = nil }
        } message: {
            Text(advanceError ?? "An unknown error occurred.")
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

    private func stageActionsSection(_ booking: BookingDetail) -> some View {
        let actions = StageAction.actions(for: booking.fulfilmentStage)

        return Group {
            if !actions.isEmpty {
                Section {
                    ForEach(actions) { action in
                        Button {
                            handleAction(action)
                        } label: {
                            Label(action.title, systemImage: action.icon)
                                .frame(maxWidth: .infinity)
                        }
                        .buttonStyle(action.isPrimary ? .borderedProminent : .bordered)
                        .tint(action.isPrimary ? CKTheme.accent : nil)
                        .controlSize(.large)
                    }
                } header: {
                    Text("Actions")
                        .font(CKTypography.callout)
                        .foregroundStyle(CKTheme.textSecondary)
                }
                .listRowBackground(CKTheme.backgroundCard)
            }
        }
    }

    // MARK: - Booking Info Section

    private func bookingInfoSection(_ booking: BookingDetail) -> some View {
        Section {
            row("Product", booking.productName)
            row("Customer", booking.customerName)
            row("Start Date", booking.startDate.map { formattedDate($0) })
            row("End Date", booking.endDate.map { formattedDate($0) })
            row("Quantity", "\(booking.quantity)")
            row("Total Price", formattedPrice(booking.totalPrice))
            HStack {
                Text("Status")
                    .font(CKTypography.body)
                    .foregroundStyle(CKTheme.textSecondary)
                Spacer()
                Text(booking.status.capitalized)
                    .font(CKTypography.caption)
                    .fontWeight(.medium)
                    .padding(.horizontal, 8)
                    .padding(.vertical, 3)
                    .background(statusColor(booking.status).opacity(0.15))
                    .foregroundStyle(statusColor(booking.status))
                    .clipShape(Capsule())
            }
            HStack {
                Text("Fulfilment Stage")
                    .font(CKTypography.body)
                    .foregroundStyle(CKTheme.textSecondary)
                Spacer()
                Text(booking.fulfilmentStage.replacingOccurrences(of: "_", with: " ").capitalized)
                    .font(CKTypography.caption)
                    .fontWeight(.medium)
                    .padding(.horizontal, 8)
                    .padding(.vertical, 3)
                    .background(stageColor(booking.fulfilmentStage).opacity(0.15))
                    .foregroundStyle(stageColor(booking.fulfilmentStage))
                    .clipShape(Capsule())
            }
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
            }
        } header: {
            Text("Assigned Assets")
                .font(CKTypography.callout)
                .foregroundStyle(CKTheme.textSecondary)
        }
        .listRowBackground(CKTheme.backgroundCard)
    }

    // MARK: - Inspection Section

    private func inspectionSection(title: String, inspection: InspectionRecord) -> some View {
        Section {
            if !inspection.photos.isEmpty {
                row("Photos", "\(inspection.photos.count)")
            } else {
                row("Photos", "0")
            }
            row("Condition Notes", inspection.conditionNotes)
            HStack {
                Text("Damage Flagged")
                    .font(CKTypography.body)
                    .foregroundStyle(CKTheme.textSecondary)
                Spacer()
                if inspection.damageFlagged == true {
                    Label("Yes", systemImage: "exclamationmark.triangle.fill")
                        .font(CKTypography.body)
                        .foregroundStyle(CKTheme.error)
                } else {
                    Text("No")
                        .font(CKTypography.body)
                        .foregroundStyle(CKTheme.success)
                }
            }
            row("Inspector", inspection.inspectorName)
            if let inspectedAt = inspection.inspectedAt {
                row("Date", formattedDateTime(inspectedAt))
            } else {
                row("Date", nil)
            }
        } header: {
            Text(title)
                .font(CKTypography.callout)
                .foregroundStyle(CKTheme.textSecondary)
        }
        .listRowBackground(CKTheme.backgroundCard)
    }

    // MARK: - Helper Views

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
            showCheckoutInspection = true
        case .returnInspection, .completeInspection:
            showReturnInspection = true
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
        let inputFormatter = DateFormatter()
        inputFormatter.dateFormat = "yyyy-MM-dd"
        inputFormatter.locale = Locale(identifier: "en_US_POSIX")

        guard let date = inputFormatter.date(from: dateString) else { return dateString }

        let outputFormatter = DateFormatter()
        outputFormatter.dateStyle = .medium
        outputFormatter.timeStyle = .none
        return outputFormatter.string(from: date)
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
        case "ready": return CKTheme.accent
        case "checked_out": return CKTheme.accent
        case "returned": return CKTheme.success
        case "inspected": return CKTheme.textSecondary
        default: return CKTheme.textTertiary
        }
    }
}
