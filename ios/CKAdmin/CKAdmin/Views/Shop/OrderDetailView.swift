import SwiftUI

/// Read-only detail view for a single shop order.
struct OrderDetailView: View {
    @State private var order: OrderDetail?
    @State private var isLoading = true
    @State private var errorMessage: String?

    private let apiClient: APIClient
    private let orderId: Int

    init(apiClient: APIClient, orderId: Int) {
        self.apiClient = apiClient
        self.orderId = orderId
    }

    var body: some View {
        Group {
            if isLoading {
                ProgressView("Loading order...")
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
                    .background(CKTheme.backgroundPrimary)
            } else if let errorMessage {
                errorView(message: errorMessage)
            } else if let order {
                orderContent(order)
            }
        }
        .navigationTitle("Order #\(orderId)")
        .navigationBarTitleDisplayMode(.inline)
        .task {
            await loadOrder()
        }
    }

    // MARK: - Content

    private func orderContent(_ order: OrderDetail) -> some View {
        List {
            // Summary section
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
                if let fulfilledAt = order.fulfilledAt {
                    LabeledContent("Fulfilled", value: fulfilledAt, format: .dateTime.day().month().year())
                }
            }

            // Delivery address
            if let address = order.deliveryAddress?.formatted {
                Section("Delivery Address") {
                    Text(address)
                        .font(CKTypography.body)
                        .foregroundStyle(CKTheme.textPrimary)
                }
            }

            // Items
            Section("Items (\(order.items.count))") {
                ForEach(order.items) { item in
                    itemRow(item)
                }
            }

            // Admin notes
            if let notes = order.adminNotes, !notes.isEmpty {
                Section("Admin Notes") {
                    Text(notes)
                        .font(CKTypography.body)
                        .foregroundStyle(CKTheme.textSecondary)
                }
            }
        }
        .scrollContentBackground(.hidden)
        .background(CKTheme.backgroundPrimary)
    }

    // MARK: - Item Row

    private func itemRow(_ item: OrderItemDetail) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            HStack {
                Text(item.productName ?? "Unknown Product")
                    .font(CKTypography.headline)
                    .foregroundStyle(CKTheme.textPrimary)

                Spacer()

                Text(formattedAmount(item.price))
                    .font(CKTypography.callout)
                    .foregroundStyle(CKTheme.textPrimary)
            }

            HStack {
                if let type = item.productType {
                    Text(productTypeLabel(type))
                        .font(CKTypography.caption)
                        .padding(.horizontal, 6)
                        .padding(.vertical, 1)
                        .background(CKTheme.textSecondary.opacity(0.12))
                        .clipShape(Capsule())
                }

                if item.quantity > 1 {
                    Text("Qty: \(item.quantity)")
                        .font(CKTypography.caption)
                        .foregroundStyle(CKTheme.textSecondary)
                }

                if let freq = item.billingFrequency {
                    Text("(\(freq))")
                        .font(CKTypography.caption)
                        .foregroundStyle(CKTheme.textSecondary)
                }
            }

            if let domain = item.domainName, !domain.isEmpty {
                Label(domain, systemImage: "globe")
                    .font(CKTypography.caption)
                    .foregroundStyle(CKTheme.info)
            }

            if let start = item.rentalStartDate, let end = item.rentalEndDate {
                Label("\(start) – \(end)", systemImage: "calendar")
                    .font(CKTypography.caption)
                    .foregroundStyle(CKTheme.textSecondary)
            }

            if let bookingStatus = item.bookingStatus {
                HStack {
                    Text("Booking:")
                        .font(CKTypography.caption)
                        .foregroundStyle(CKTheme.textSecondary)
                    statusBadge(bookingStatus, color: rentalStatusColor(bookingStatus))
                }
            }
        }
        .padding(.vertical, 2)
    }

    // MARK: - Helpers

    private func statusBadge(_ text: String, color: Color) -> some View {
        Text(text.replacingOccurrences(of: "_", with: " ").capitalized)
            .font(CKTypography.caption)
            .fontWeight(.medium)
            .padding(.horizontal, 8)
            .padding(.vertical, 2)
            .background(color.opacity(0.15))
            .foregroundStyle(color)
            .clipShape(Capsule())
    }

    private func paymentColor(_ status: String) -> Color {
        switch status {
        case "paid", "paid_offline": return CKTheme.success
        case "pending": return CKTheme.warning
        case "failed": return CKTheme.error
        default: return CKTheme.textTertiary
        }
    }

    private func fulfilmentColor(_ status: String) -> Color {
        switch status {
        case "completed": return CKTheme.success
        case "awaiting_fulfilment": return CKTheme.info
        case "pending": return CKTheme.warning
        default: return CKTheme.textTertiary
        }
    }

    private func rentalStatusColor(_ status: String) -> Color {
        switch status {
        case "active": return CKTheme.info
        case "confirmed": return CKTheme.success
        case "returned": return CKTheme.textTertiary
        case "cancelled": return CKTheme.error
        default: return CKTheme.textTertiary
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
        formatter.maximumFractionDigits = 2
        return formatter.string(from: NSNumber(value: amount)) ?? "£\(String(format: "%.2f", amount))"
    }

    // MARK: - Loading

    private func loadOrder() async {
        isLoading = true
        errorMessage = nil

        do {
            let endpoint = Endpoint(path: "/admin/shop/orders/\(orderId)")
            let response: OrderDetailResponse = try await apiClient.request(endpoint)
            order = response.data
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "An unexpected error occurred."
        }

        isLoading = false
    }

    private func errorView(message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle")
                .font(.system(size: 48))
                .foregroundStyle(CKTheme.warning)
            Text("Unable to Load Order")
                .font(CKTypography.headline)
                .foregroundStyle(CKTheme.textPrimary)
            Text(message)
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal)
            Button { Task { await loadOrder() } } label: {
                Label("Retry", systemImage: "arrow.clockwise").fontWeight(.medium)
            }
            .buttonStyle(.borderedProminent)
            .tint(CKTheme.accent)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(CKTheme.backgroundPrimary)
    }
}

#Preview {
    NavigationStack {
        OrderDetailView(apiClient: APIClient(authManager: AuthManager()), orderId: 1)
    }
}
