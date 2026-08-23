import SwiftUI
import UIKit
import Observation

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


// MARK: - Stage Action

/// Contextual actions available for a Booking based on its current Fulfilment_Stage.
/// Maps each stage to its valid set of quick-action buttons per Requirement 18.
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

    /// Display title for the action button.
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

    /// SF Symbol icon name for the action button.
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

    /// Whether this action is the primary (visually emphasised) action for its stage (Req 18.9).
    var isPrimary: Bool {
        switch self {
        case .advanceToPacking, .markReady, .checkOut, .returnInspection, .completeInspection:
            return true
        case .markReturned:
            return false
        }
    }

    /// Whether this action launches an inspection flow rather than a simple API call.
    var launchesInspection: Bool {
        switch self {
        case .checkOut, .returnInspection, .completeInspection:
            return true
        case .advanceToPacking, .markReady, .markReturned:
            return false
        }
    }

    /// Returns the valid actions for a given fulfilment_stage string.
    ///
    /// Mapping per Requirement 18:
    /// - ordered → [advanceToPacking] (Req 18.1)
    /// - packing → [markReady] (Req 18.2)
    /// - ready → [checkOut] (Req 18.3)
    /// - checked_out → [markReturned, returnInspection] (Req 18.4)
    /// - returned → [completeInspection] (Req 18.5)
    /// - inspected → [] (Req 18.6)
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

// MARK: - Stage Configuration

/// Defines the visual configuration for a single fulfilment stage.
struct StageConfig {
    let stage: String
    let label: String
    let color: Color
    let iconName: String

    /// All fulfilment stages in sequential order with their assigned colours and SF Symbols.
    static let allStages: [StageConfig] = [
        StageConfig(stage: "ordered", label: "Ordered", color: .gray, iconName: "clock"),
        StageConfig(stage: "packing", label: "Packing", color: .blue, iconName: "shippingbox"),
        StageConfig(stage: "ready", label: "Ready", color: .green, iconName: "checkmark.circle"),
        StageConfig(stage: "checked_out", label: "Checked Out", color: .teal, iconName: "person.fill.checkmark"),
        StageConfig(stage: "returned", label: "Returned", color: .orange, iconName: "arrow.uturn.backward"),
        StageConfig(stage: "inspected", label: "Inspected", color: .green, iconName: "checkmark.seal.fill"),
    ]

    /// Returns the index of the given stage in the sequence, or 0 if not found.
    static func indexOf(_ stage: String) -> Int {
        allStages.firstIndex(where: { $0.stage == stage }) ?? 0
    }
}

// MARK: - FulfilmentStageIndicator View

/// A horizontal progress indicator showing all fulfilment stages with visual state:
/// - Completed stages: checkmark icon with reduced opacity colour
/// - Active stage: full colour with assigned icon, slightly emphasised
/// - Future stages: grey/muted styling
struct FulfilmentStageIndicator: View {
    let currentStage: String

    private var currentIndex: Int {
        StageConfig.indexOf(currentStage)
    }

    var body: some View {
        HStack(spacing: 4) {
            ForEach(Array(StageConfig.allStages.enumerated()), id: \.element.stage) { index, config in
                stageItem(config: config, index: index)

                if index < StageConfig.allStages.count - 1 {
                    connector(completed: index < currentIndex)
                }
            }
        }
        .padding(.vertical, 8)
    }

    // MARK: - Stage Item

    @ViewBuilder
    private func stageItem(config: StageConfig, index: Int) -> some View {
        VStack(spacing: 4) {
            ZStack {
                Circle()
                    .fill(backgroundColor(for: index))
                    .frame(width: 32, height: 32)

                Image(systemName: iconName(config: config, index: index))
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundStyle(iconColor(for: index))
            }

            Text(config.label)
                .font(.system(size: 9, weight: index == currentIndex ? .semibold : .regular))
                .foregroundStyle(labelColor(for: index))
                .lineLimit(1)
                .minimumScaleFactor(0.8)
        }
        .frame(maxWidth: .infinity)
    }

    // MARK: - Connector

    private func connector(completed: Bool) -> some View {
        Rectangle()
            .fill(completed ? Color.green.opacity(0.6) : Color.gray.opacity(0.2))
            .frame(height: 2)
            .frame(maxWidth: 12)
            .offset(y: -8) // Align with circle centre
    }

    // MARK: - Styling Helpers

    private func backgroundColor(for index: Int) -> Color {
        let config = StageConfig.allStages[index]
        if index == currentIndex {
            return config.color.opacity(0.2)
        } else if index < currentIndex {
            return config.color.opacity(0.1)
        } else {
            return Color.gray.opacity(0.08)
        }
    }

    private func iconName(config: StageConfig, index: Int) -> String {
        if index < currentIndex {
            return "checkmark" // Completed stages show checkmark overlay
        }
        return config.iconName
    }

    private func iconColor(for index: Int) -> Color {
        let config = StageConfig.allStages[index]
        if index == currentIndex {
            return config.color // Full opacity for active stage
        } else if index < currentIndex {
            return config.color.opacity(0.7) // Reduced opacity for completed
        } else {
            return Color.gray.opacity(0.4) // Muted grey for future
        }
    }

    private func labelColor(for index: Int) -> Color {
        if index == currentIndex {
            return CKTheme.textPrimary
        } else if index < currentIndex {
            return CKTheme.textSecondary
        } else {
            return CKTheme.textTertiary
        }
    }
}

// MARK: - CheckoutInspectionViewModel

/// Manages the multi-step checkout inspection (Handover Mode) flow.
///
/// Steps:
/// 1. Photos — capture equipment condition photos (at least 1 required)
/// 2. Notes — optional condition notes
/// 3. Signature — optional customer signature capture
@Observable
final class CheckoutInspectionViewModel {

    // MARK: - Step Definition

    enum Step: Int, CaseIterable {
        case photos = 0
        case notes = 1
        case signature = 2

        var title: String {
            switch self {
            case .photos: return "Photos"
            case .notes: return "Condition Notes"
            case .signature: return "Signature"
            }
        }
    }

    static let totalSteps = Step.allCases.count

    // MARK: - State

    var photos: [UIImage] = []
    var conditionNotes: String = ""
    var signatureImage: UIImage? = nil
    var currentStep: Int = 0

    private(set) var isSubmitting = false
    var error: String? = nil

    // MARK: - Private

    private let apiClient: APIClient

    // MARK: - Init

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    // MARK: - Computed Properties

    /// Whether the user can proceed from the current step.
    /// Step 0 (photos) requires at least one photo.
    var canProceed: Bool {
        switch Step(rawValue: currentStep) {
        case .photos:
            return !photos.isEmpty
        case .notes, .signature:
            return true
        case .none:
            return false
        }
    }

    /// Whether the inspection can be submitted (at least 1 photo, not already submitting).
    var canSubmit: Bool {
        !photos.isEmpty && !isSubmitting
    }

    // MARK: - Navigation

    func nextStep() {
        guard currentStep < Self.totalSteps - 1 else { return }
        currentStep += 1
    }

    func previousStep() {
        guard currentStep > 0 else { return }
        currentStep -= 1
    }

    // MARK: - Submission

    /// Submits the checkout inspection to the backend.
    ///
    /// Builds multipart form data with photos (JPEG), condition notes,
    /// and base64-encoded signature, then calls the inspect endpoint.
    ///
    /// - Parameters:
    ///   - orderId: The order ID for the booking.
    ///   - bookingId: The booking ID to inspect.
    @MainActor
    func submit(orderId: Int, bookingId: Int) async {
        guard canSubmit else { return }

        isSubmitting = true
        error = nil

        do {
            let formData = buildMultipartFormData()
            let _: MessageResponse = try await apiClient.uploadMultipart(
                path: "/admin/shop/orders/\(orderId)/bookings/\(bookingId)/inspect",
                formData: formData
            )
        } catch let apiError as APIError {
            error = apiError.errorDescription
        } catch {
            self.error = "An unexpected error occurred."
        }

        isSubmitting = false
    }

    // MARK: - Private Helpers

    private func buildMultipartFormData() -> MultipartFormData {
        var formData = MultipartFormData()

        // Add photos as JPEG data
        for (index, photo) in photos.enumerated() {
            guard let imageData = photo.jpegData(compressionQuality: 0.8) else { continue }
            formData.addFile(
                name: "photos[\(index)]",
                fileName: "photo_\(index).jpg",
                mimeType: "image/jpeg",
                data: imageData
            )
        }

        // Add condition notes if provided
        if !conditionNotes.isEmpty {
            formData.addField(name: "condition_notes", value: conditionNotes)
        }

        // Add base64-encoded signature if captured
        if let signature = signatureImage, let pngData = signature.pngData() {
            let base64String = pngData.base64EncodedString()
            formData.addField(name: "signature_data", value: base64String)
        }

        return formData
    }
}

// MARK: - ReturnInspectionViewModel

/// ViewModel managing the return inspection multi-step flow.
/// Steps: Photos → Notes → Damage Flag
@Observable
final class ReturnInspectionViewModel {

    // MARK: - State

    var photos: [UIImage] = []
    var conditionNotes: String = ""
    var isDamaged: Bool = false
    var currentStep: Int = 0
    var isSubmitting: Bool = false
    var error: String?

    // MARK: - Constants

    /// Total number of steps in the return inspection flow.
    static let stepCount = 3

    /// Step titles for display.
    static let stepTitles = ["Photos", "Condition Notes", "Damage Check"]

    // MARK: - Dependencies

    private let apiClient: APIClient

    // MARK: - Init

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    // MARK: - Computed Properties

    /// Whether the user can proceed from the current step.
    /// Step 0 (Photos): at least 1 photo required.
    /// Steps 1 and 2: always allowed.
    var canProceed: Bool {
        switch currentStep {
        case 0:
            return !photos.isEmpty
        default:
            return true
        }
    }

    /// Whether the inspection can be submitted (photos required and not already submitting).
    var canSubmit: Bool {
        !photos.isEmpty && !isSubmitting
    }

    // MARK: - Navigation

    func nextStep() {
        guard currentStep < Self.stepCount - 1 else { return }
        currentStep += 1
    }

    func previousStep() {
        guard currentStep > 0 else { return }
        currentStep -= 1
    }

    // MARK: - Submission

    /// Submit the return inspection to the backend.
    /// Builds MultipartFormData with photos (JPEG), condition notes, and damage flag,
    /// then calls the inspect endpoint via APIClient.uploadMultipart.
    @MainActor
    func submit(orderId: Int, bookingId: Int) async {
        isSubmitting = true
        error = nil

        do {
            var formData = MultipartFormData()

            // Add photos as JPEG data
            for (index, photo) in photos.enumerated() {
                guard let imageData = photo.jpegData(compressionQuality: 0.8) else { continue }
                formData.addFile(
                    name: "photos[\(index)]",
                    fileName: "photo_\(index).jpg",
                    mimeType: "image/jpeg",
                    data: imageData
                )
            }

            // Add condition notes if provided
            if !conditionNotes.isEmpty {
                formData.addField(name: "condition_notes", value: conditionNotes)
            }

            // Add damage flag
            formData.addField(name: "damage_flagged", value: isDamaged ? "1" : "0")

            let _: MessageResponse = try await apiClient.uploadMultipart(
                path: "/admin/shop/orders/\(orderId)/bookings/\(bookingId)/inspect",
                formData: formData
            )
        } catch let apiError as APIError {
            error = apiError.errorDescription
        } catch {
            self.error = "An unexpected error occurred."
        }

        isSubmitting = false
    }
}

// MARK: - CheckoutInspectionView

/// Multi-step checkout inspection view (Handover Mode).
///
/// Presents a sheet with a StepProgressBar, step content
/// (PhotoCaptureStep → ConditionNotesStep → SignatureStep),
/// and navigation buttons (Back/Next/Submit).
/// On successful submission, dismisses the sheet and triggers
/// the provided completion callback so the parent can refresh.
///
/// Requirements: 16.2, 16.3, 16.4, 16.5, 16.6, 16.7
struct CheckoutInspectionView: View {
    let orderId: Int
    let bookingId: Int
    let agreementText: String?
    var onComplete: (() -> Void)?

    @State private var viewModel: CheckoutInspectionViewModel
    @Environment(\.dismiss) private var dismiss

    init(orderId: Int, bookingId: Int, agreementText: String? = nil, apiClient: APIClient, onComplete: (() -> Void)? = nil) {
        self.orderId = orderId
        self.bookingId = bookingId
        self.agreementText = agreementText
        self.onComplete = onComplete
        self._viewModel = State(initialValue: CheckoutInspectionViewModel(apiClient: apiClient))
    }

    // MARK: - Step Titles

    private let stepTitles = ["Photos", "Notes", "Signature"]

    // MARK: - Body

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                // Step progress indicator
                StepProgressBar(
                    steps: stepTitles,
                    currentStep: viewModel.currentStep
                )
                .padding(.vertical, 12)

                Divider()

                // Step content
                checkoutStepContent
                    .frame(maxWidth: .infinity, maxHeight: .infinity)

                Divider()

                // Navigation buttons
                checkoutNavigationButtons
                    .padding()
            }
            .navigationTitle("Checkout Inspection")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") {
                        dismiss()
                    }
                }
            }
            .alert("Error", isPresented: showCheckoutErrorBinding) {
                Button("OK") {
                    viewModel.error = nil
                }
            } message: {
                Text(viewModel.error ?? "")
            }
        }
    }

    // MARK: - Step Content

    @ViewBuilder
    private var checkoutStepContent: some View {
        switch CheckoutInspectionViewModel.Step(rawValue: viewModel.currentStep) {
        case .photos:
            PhotoCaptureStep(photos: $viewModel.photos)
        case .notes:
            ConditionNotesStep(notes: $viewModel.conditionNotes)
        case .signature:
            SignatureStep(
                signatureImage: $viewModel.signatureImage,
                agreementText: agreementText
            )
        case .none:
            EmptyView()
        }
    }

    // MARK: - Navigation Buttons

    private var checkoutNavigationButtons: some View {
        HStack(spacing: 16) {
            // Back button (hidden on first step)
            if viewModel.currentStep > 0 {
                Button {
                    viewModel.previousStep()
                } label: {
                    HStack(spacing: 4) {
                        Image(systemName: "chevron.left")
                        Text("Back")
                    }
                    .frame(maxWidth: .infinity)
                }
                .buttonStyle(.bordered)
            }

            // Next or Submit button
            if viewModel.currentStep < CheckoutInspectionViewModel.totalSteps - 1 {
                Button {
                    viewModel.nextStep()
                } label: {
                    HStack(spacing: 4) {
                        Text("Next")
                        Image(systemName: "chevron.right")
                    }
                    .frame(maxWidth: .infinity)
                }
                .buttonStyle(.borderedProminent)
                .disabled(!viewModel.canProceed)
            } else {
                Button {
                    Task {
                        await viewModel.submit(orderId: orderId, bookingId: bookingId)
                        if viewModel.error == nil {
                            onComplete?()
                            dismiss()
                        }
                    }
                } label: {
                    if viewModel.isSubmitting {
                        ProgressView()
                            .frame(maxWidth: .infinity)
                    } else {
                        Text("Submit")
                            .frame(maxWidth: .infinity)
                    }
                }
                .buttonStyle(.borderedProminent)
                .tint(CKTheme.accent)
                .disabled(!viewModel.canSubmit)
            }
        }
    }

    // MARK: - Helpers

    private var showCheckoutErrorBinding: Binding<Bool> {
        Binding(
            get: { viewModel.error != nil },
            set: { if !$0 { viewModel.error = nil } }
        )
    }
}

// MARK: - ReturnInspectionView

/// Multi-step return inspection view presented as a sheet.
/// Flow: Photos → Condition Notes → Damage Flag
///
/// On successful submission, dismisses the sheet and triggers a booking detail refresh.
///
/// Requirements: 17.2, 17.3, 17.4, 17.5, 17.6, 17.7, 17.8
struct ReturnInspectionView: View {
    let orderId: Int
    let bookingId: Int
    var onComplete: (() -> Void)?

    @Environment(\.dismiss) private var dismiss
    @State private var viewModel: ReturnInspectionViewModel

    init(orderId: Int, bookingId: Int, apiClient: APIClient, onComplete: (() -> Void)? = nil) {
        self.orderId = orderId
        self.bookingId = bookingId
        self.onComplete = onComplete
        self._viewModel = State(initialValue: ReturnInspectionViewModel(apiClient: apiClient))
    }

    // MARK: - Step Titles

    private let returnStepTitles = ["Photos", "Notes", "Damage"]

    // MARK: - Body

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                // Step progress bar
                StepProgressBar(steps: returnStepTitles, currentStep: viewModel.currentStep)
                    .padding(.vertical, 12)

                Divider()

                // Step content
                returnStepContent
                    .frame(maxWidth: .infinity, maxHeight: .infinity)

                Divider()

                // Navigation buttons
                returnNavigationButtons
                    .padding()
            }
            .navigationTitle("Return Inspection")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") {
                        dismiss()
                    }
                }
            }
            .alert("Error", isPresented: showReturnErrorBinding) {
                Button("OK") {
                    viewModel.error = nil
                }
            } message: {
                Text(viewModel.error ?? "An unknown error occurred.")
            }
        }
    }

    // MARK: - Step Content

    @ViewBuilder
    private var returnStepContent: some View {
        switch viewModel.currentStep {
        case 0:
            PhotoCaptureStep(photos: $viewModel.photos)
        case 1:
            ConditionNotesStep(notes: $viewModel.conditionNotes)
        case 2:
            DamageFlagStep(isDamaged: $viewModel.isDamaged)
        default:
            EmptyView()
        }
    }

    // MARK: - Navigation Buttons

    @ViewBuilder
    private var returnNavigationButtons: some View {
        HStack(spacing: 16) {
            // Back button (hidden on first step)
            if viewModel.currentStep > 0 {
                Button {
                    viewModel.previousStep()
                } label: {
                    HStack(spacing: 4) {
                        Image(systemName: "chevron.left")
                        Text("Back")
                    }
                    .frame(maxWidth: .infinity)
                }
                .buttonStyle(.bordered)
            }

            // Next or Submit button
            if viewModel.currentStep < ReturnInspectionViewModel.stepCount - 1 {
                Button {
                    viewModel.nextStep()
                } label: {
                    HStack(spacing: 4) {
                        Text("Next")
                        Image(systemName: "chevron.right")
                    }
                    .frame(maxWidth: .infinity)
                }
                .buttonStyle(.borderedProminent)
                .disabled(!viewModel.canProceed)
            } else {
                Button {
                    Task {
                        await viewModel.submit(orderId: orderId, bookingId: bookingId)
                        if viewModel.error == nil {
                            onComplete?()
                            dismiss()
                        }
                    }
                } label: {
                    if viewModel.isSubmitting {
                        ProgressView()
                            .frame(maxWidth: .infinity)
                    } else {
                        Text("Submit")
                            .frame(maxWidth: .infinity)
                    }
                }
                .buttonStyle(.borderedProminent)
                .tint(.green)
                .disabled(!viewModel.canSubmit)
            }
        }
    }

    // MARK: - Helpers

    private var showReturnErrorBinding: Binding<Bool> {
        Binding(
            get: { viewModel.error != nil },
            set: { if !$0 { viewModel.error = nil } }
        )
    }
}
