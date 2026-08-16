import SwiftUI

/// Full order detail view with action buttons (fulfil, cancel, mark paid, notes)
/// and embedded booking fulfilment pipeline.
struct OrderActionDetailView: View {
    @State private var order: EnhancedOrderDetail?
    @State private var isLoading = true
    @State private var errorMessage: String?
    @State private var actionMessage: String?
    @State private var isPerformingAction = false
    @State private var showNoteSheet = false
    @State private var noteText = ""
    @State private var showInspectionSheet = false
    @State private var inspectionBookingId: Int?
    @State private var showPackingScanSheet = false
    @State private var packingBookingId: Int?

    private let apiClient: APIClient
    private let orderId: Int

    init(apiClient: APIClient, orderId: Int) {
        self.apiClient = apiClient
        self.orderId = orderId
    }

    var body: some View {
        Group {
            if isLoading && order == nil {
                ProgressView("Loading order...")
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
            } else if let errorMessage, order == nil {
                errorView(message: errorMessage)
            } else if let order {
                orderContent(order)
            }
        }
        .navigationTitle("Order #\(orderId)")
        .navigationBarTitleDisplayMode(.inline)
        .task { await loadOrder() }
        .sheet(isPresented: $showNoteSheet) { addNoteSheet }
        .sheet(isPresented: $showInspectionSheet) {
            if let bookingId = inspectionBookingId {
                InspectionUploadView(apiClient: apiClient, orderId: orderId, bookingId: bookingId) {
                    showInspectionSheet = false
                    Task { await loadOrder() }
                }
            }
        }
        .sheet(isPresented: $showPackingScanSheet) {
            if let bookingId = packingBookingId {
                PackingScanView(apiClient: apiClient, orderId: orderId, bookingId: bookingId) {
                    showPackingScanSheet = false
                    Task { await loadOrder() }
                }
            }
        }
    }

    // MARK: - Content

    private func orderContent(_ order: EnhancedOrderDetail) -> some View {
        List {
            // Action message banner
            if let actionMessage {
                Section {
                    HStack {
                        Image(systemName: "checkmark.circle.fill")
                            .foregroundStyle(.green)
                        Text(actionMessage)
                            .font(.subheadline)
                    }
                }
            }

            // Summary
            Section("Summary") {
                LabeledContent("Customer", value: order.customerName ?? "Unknown")
                LabeledContent("Total", value: formattedAmount(order.totalAmount))
                LabeledContent("Payment") {
                    statusBadge(order.paymentStatus, color: paymentColor(order.paymentStatus))
                }
                LabeledContent("Fulfilment") {
                    statusBadge(order.fulfilmentStatus, color: fulfilmentColor(order.fulfilmentStatus))
                }
                if let createdAt = order.createdAt {
                    LabeledContent("Placed", value: createdAt, format: .dateTime.day().month().year())
                }
            }

            // Quick Actions
            Section("Actions") {
                if !["paid", "paid_offline"].contains(order.paymentStatus) {
                    Button { Task { await performAction("mark-paid-offline") } } label: {
                        Label("Mark Paid Offline", systemImage: "banknote")
                    }
                    .disabled(isPerformingAction)
                }

                if order.fulfilmentStatus != "completed" && order.fulfilmentStatus != "cancelled" {
                    Button { Task { await performAction("fulfil") } } label: {
                        Label("Mark Fulfilled", systemImage: "checkmark.circle")
                    }
                    .disabled(isPerformingAction)
                }

                Button { showNoteSheet = true } label: {
                    Label("Add Note", systemImage: "note.text.badge.plus")
                }

                if order.fulfilmentStatus != "cancelled" {
                    Button(role: .destructive) { Task { await performAction("cancel") } } label: {
                        Label("Cancel Order", systemImage: "xmark.circle")
                    }
                    .disabled(isPerformingAction)
                }
            }

            // Delivery address
            if let address = order.deliveryAddress?.formatted {
                Section("Delivery Address") {
                    Text(address).font(.subheadline)
                }
            }

            // Items
            Section("Items (\(order.items.count))") {
                ForEach(order.items) { item in
                    itemRow(item)
                }
            }

            // Rental Fulfilment (per booking)
            let rentalItems = order.items.filter { $0.booking != nil }
            if !rentalItems.isEmpty {
                ForEach(rentalItems) { item in
                    if let booking = item.booking {
                        Section("Rental: \(item.productName ?? "Unknown")") {
                            fulfilmentSection(booking: booking, orderId: order.id)
                        }
                    }
                }
            }

            // Admin notes
            if let notes = order.adminNotes, !notes.isEmpty {
                Section("Notes") {
                    Text(notes).font(.caption).foregroundStyle(.secondary)
                }
            }
        }
        .refreshable { await loadOrder() }
    }

    // MARK: - Fulfilment Section

    private func fulfilmentSection(booking: OrderItemBooking, orderId: Int) -> some View {
        Group {
            // Stage timeline
            LabeledContent("Stage") {
                statusBadge(booking.fulfilmentStage, color: stageColor(booking.fulfilmentStage))
            }

            LabeledContent("Status") {
                statusBadge(booking.status, color: rentalStatusColor(booking.status))
            }

            // Assigned assets
            if !booking.assignedAssets.isEmpty {
                DisclosureGroup("Assigned Assets (\(booking.assignedAssets.count))") {
                    ForEach(booking.assignedAssets) { asset in
                        HStack {
                            VStack(alignment: .leading) {
                                Text(asset.deviceName ?? "Unknown")
                                    .font(.subheadline)
                                if let serial = asset.serialNumber {
                                    Text(serial).font(.caption).foregroundStyle(.secondary)
                                }
                            }
                            Spacer()
                            if asset.releasedAt != nil {
                                Text("Released").font(.caption2).foregroundStyle(.secondary)
                            }
                        }
                    }
                }
            }

            // Inspection status
            HStack {
                Label(booking.hasCheckoutInspection ? "Checkout done" : "Checkout pending",
                      systemImage: booking.hasCheckoutInspection ? "checkmark.circle.fill" : "circle")
                    .font(.caption)
                    .foregroundStyle(booking.hasCheckoutInspection ? .green : .secondary)
            }
            HStack {
                Label(booking.hasReturnInspection ? "Return done" : "Return pending",
                      systemImage: booking.hasReturnInspection ? "checkmark.circle.fill" : "circle")
                    .font(.caption)
                    .foregroundStyle(booking.hasReturnInspection ? .green : .secondary)
            }

            // Actions
            if let nextStage = booking.nextStage {
                if nextStage == "checked_out" && !booking.hasCheckoutInspection {
                    Button {
                        inspectionBookingId = booking.id
                        showInspectionSheet = true
                    } label: {
                        Label("Checkout Inspection (Photos)", systemImage: "camera")
                    }
                } else if nextStage == "inspected" && !booking.hasReturnInspection {
                    Button {
                        inspectionBookingId = booking.id
                        showInspectionSheet = true
                    } label: {
                        Label("Return Inspection (Photos)", systemImage: "camera")
                    }
                } else if ["packing", "ready"].contains(nextStage) {
                    // Packing stage — show scan-to-pack button
                    Button {
                        packingBookingId = booking.id
                        showPackingScanSheet = true
                    } label: {
                        Label("Scan Items to Pack", systemImage: "qrcode.viewfinder")
                    }

                    Button {
                        Task { await advanceStage(orderId: orderId, bookingId: booking.id) }
                    } label: {
                        Label("Advance to \(nextStage.replacingOccurrences(of: "_", with: " ").capitalized)",
                              systemImage: "arrow.right.circle")
                    }
                    .disabled(isPerformingAction)
                } else {
                    Button {
                        Task { await advanceStage(orderId: orderId, bookingId: booking.id) }
                    } label: {
                        Label("Advance to \(nextStage.replacingOccurrences(of: "_", with: " ").capitalized)",
                              systemImage: "arrow.right.circle")
                    }
                    .disabled(isPerformingAction)
                }
            }

            // Mark returned (one-tap)
            if booking.status != "returned" && ["active", "confirmed"].contains(booking.status) && booking.fulfilmentStage == "checked_out" {
                Button {
                    Task { await markReturned(orderId: orderId, bookingId: booking.id) }
                } label: {
                    Label("Mark Returned", systemImage: "arrow.uturn.left.circle")
                }
                .disabled(isPerformingAction)
            }
        }
    }

    // MARK: - Item Row

    private func itemRow(_ item: EnhancedOrderItemDetail) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            HStack {
                Text(item.productName ?? "Unknown")
                    .font(.body).fontWeight(.medium)
                Spacer()
                Text(formattedAmount(item.price))
                    .font(.subheadline).fontWeight(.semibold)
            }
            HStack {
                if let type = item.productType {
                    Text(productTypeLabel(type))
                        .font(.caption2).padding(.horizontal, 6).padding(.vertical, 1)
                        .background(Color.secondary.opacity(0.12))
                        .clipShape(Capsule())
                }
                if item.quantity > 1 {
                    Text("Qty: \(item.quantity)").font(.caption).foregroundStyle(.secondary)
                }
            }
        }
        .padding(.vertical, 2)
    }

    // MARK: - Add Note Sheet

    private var addNoteSheet: some View {
        NavigationStack {
            Form {
                Section("Add a note") {
                    TextField("Note...", text: $noteText, axis: .vertical)
                        .lineLimit(3...6)
                }
            }
            .navigationTitle("Add Note")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { showNoteSheet = false }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Save") {
                        Task {
                            await addNote()
                            showNoteSheet = false
                        }
                    }
                    .disabled(noteText.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty)
                }
            }
        }
        .presentationDetents([.medium])
    }

    // MARK: - API Calls

    private func loadOrder() async {
        isLoading = true
        errorMessage = nil
        do {
            let endpoint = Endpoint(path: "/admin/shop/orders/\(orderId)")
            let response: EnhancedOrderDetailResponse = try await apiClient.request(endpoint)
            order = response.data
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "An unexpected error occurred."
        }
        isLoading = false
    }

    private func performAction(_ action: String) async {
        isPerformingAction = true
        actionMessage = nil
        do {
            let endpoint = Endpoint(method: .post, path: "/admin/shop/orders/\(orderId)/\(action)")
            let response: MessageResponse = try await apiClient.request(endpoint)
            actionMessage = response.message
            await loadOrder()
        } catch let error as APIError {
            actionMessage = error.errorDescription
        } catch {
            actionMessage = "Action failed."
        }
        isPerformingAction = false
    }

    private func addNote() async {
        guard !noteText.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty else { return }
        isPerformingAction = true
        do {
            struct NoteBody: Encodable { let note: String }
            let endpoint = Endpoint(method: .post, path: "/admin/shop/orders/\(orderId)/note", body: NoteBody(note: noteText))
            let _: MessageResponse = try await apiClient.request(endpoint)
            noteText = ""
            await loadOrder()
        } catch {}
        isPerformingAction = false
    }

    private func advanceStage(orderId: Int, bookingId: Int) async {
        isPerformingAction = true
        actionMessage = nil
        do {
            let endpoint = Endpoint(method: .post, path: "/admin/shop/orders/\(orderId)/bookings/\(bookingId)/advance-stage")
            let response: MessageResponse = try await apiClient.request(endpoint)
            actionMessage = response.message
            await loadOrder()
        } catch let error as APIError {
            actionMessage = error.errorDescription
        } catch {
            actionMessage = "Failed to advance stage."
        }
        isPerformingAction = false
    }

    private func markReturned(orderId: Int, bookingId: Int) async {
        isPerformingAction = true
        actionMessage = nil
        do {
            let endpoint = Endpoint(method: .post, path: "/admin/shop/orders/\(orderId)/bookings/\(bookingId)/mark-returned")
            let response: MessageResponse = try await apiClient.request(endpoint)
            actionMessage = response.message
            await loadOrder()
        } catch let error as APIError {
            actionMessage = error.errorDescription
        } catch {
            actionMessage = "Failed to mark returned."
        }
        isPerformingAction = false
    }

    // MARK: - Helpers

    private func statusBadge(_ text: String, color: Color) -> some View {
        Text(text.replacingOccurrences(of: "_", with: " ").capitalized)
            .font(.caption2).fontWeight(.medium)
            .padding(.horizontal, 8).padding(.vertical, 2)
            .background(color.opacity(0.15))
            .foregroundStyle(color)
            .clipShape(Capsule())
    }

    private func paymentColor(_ status: String) -> Color {
        switch status {
        case "paid", "paid_offline": return .green
        case "pending": return .orange
        case "failed": return .red
        default: return .gray
        }
    }

    private func fulfilmentColor(_ status: String) -> Color {
        switch status {
        case "completed": return .green
        case "awaiting_fulfilment": return .blue
        case "cancelled": return .red
        default: return .orange
        }
    }

    private func stageColor(_ stage: String) -> Color {
        switch stage {
        case "ordered": return .blue
        case "packing": return .orange
        case "ready": return .purple
        case "checked_out": return .green
        case "returned": return .mint
        case "inspected": return .gray
        default: return .gray
        }
    }

    private func rentalStatusColor(_ status: String) -> Color {
        switch status {
        case "active": return .blue
        case "confirmed": return .green
        case "returned": return .gray
        case "cancelled": return .red
        default: return .gray
        }
    }

    private func productTypeLabel(_ type: String) -> String {
        switch type {
        case "equipment_rental": return "Rental"
        case "one_off": return "One-Off"
        case "hosting": return "Hosting"
        default: return type.capitalized
        }
    }

    private func formattedAmount(_ amount: Double) -> String {
        let formatter = NumberFormatter()
        formatter.numberStyle = .currency
        formatter.currencyCode = "GBP"
        return formatter.string(from: NSNumber(value: amount)) ?? "£\(String(format: "%.2f", amount))"
    }

    private func errorView(message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle").font(.system(size: 48)).foregroundStyle(.orange)
            Text("Unable to Load Order").font(.headline)
            Text(message).font(.subheadline).foregroundStyle(.secondary).multilineTextAlignment(.center)
            Button { Task { await loadOrder() } } label: {
                Label("Retry", systemImage: "arrow.clockwise").fontWeight(.medium)
            }.buttonStyle(.borderedProminent)
        }.frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}
