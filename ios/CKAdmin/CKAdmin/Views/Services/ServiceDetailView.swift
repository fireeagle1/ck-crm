import SwiftUI

/// Displays a full service record with all fields and associated customer name.
///
/// Loads the service detail from the API on appear, provides a toolbar action
/// for editing the service.
struct ServiceDetailView: View {
    @State private var service: ServiceDetail?
    @State private var isLoading = true
    @State private var errorMessage: String?
    @State private var showingEditForm = false

    private let serviceId: Int
    private let apiClient: APIClient

    /// Creates a service detail view.
    /// - Parameters:
    ///   - serviceId: The ID of the service to display.
    ///   - apiClient: The API client for network requests.
    init(serviceId: Int, apiClient: APIClient) {
        self.serviceId = serviceId
        self.apiClient = apiClient
    }

    var body: some View {
        Group {
            if isLoading && service == nil {
                loadingView
            } else if let errorMessage, service == nil {
                errorView(message: errorMessage)
            } else if let service {
                serviceContent(service)
            }
        }
        .navigationTitle(service?.serviceShort ?? "Service")
        .navigationBarTitleDisplayMode(.large)
        .toolbar {
            if service != nil {
                ToolbarItem(placement: .topBarTrailing) {
                    Button {
                        showingEditForm = true
                    } label: {
                        Label("Edit", systemImage: "pencil")
                    }
                }
            }
        }
        .sheet(isPresented: $showingEditForm) {
            if let service {
                NavigationStack {
                    ServiceFormView(
                        mode: .edit(service),
                        apiClient: apiClient
                    ) { _ in
                        await loadService()
                    }
                }
            }
        }
        .task {
            await loadService()
        }
    }

    // MARK: - Service Content

    private func serviceContent(_ service: ServiceDetail) -> some View {
        List {
            // Status and Type Overview
            Section("Overview") {
                detailRow(label: "Status", value: service.status, badge: true)
                detailRow(label: "Service Type", value: service.serviceType)
                detailRow(label: "Domain", value: service.domainName)
            }

            // Customer
            Section("Customer") {
                detailRow(label: "Customer", value: service.customerName)
                detailRow(label: "Company ID", value: "\(service.companyId)")
            }

            // Billing
            Section("Billing") {
                if let charge = service.serviceMonthlyCharge {
                    detailRow(label: "Monthly Charge", value: formattedCharge(charge))
                } else {
                    detailRow(label: "Monthly Charge", value: nil)
                }
                detailRow(label: "Payment Frequency", value: service.servicePaymentFrequency)
                detailRow(label: "Stripe Subscription", value: service.stripeSubscriptionId)
            }

            // Dates
            Section("Dates") {
                detailRow(label: "Start Date", value: service.startDate)
                detailRow(label: "End Date", value: service.endDate)
                detailRow(label: "Next Payment", value: service.nextPaymentDate)
            }
        }
        .refreshable {
            await loadService()
        }
    }

    // MARK: - Helper Views

    private func detailRow(label: String, value: String?, badge: Bool = false) -> some View {
        HStack {
            Text(label)
                .foregroundStyle(.secondary)
            Spacer()
            if badge, let value {
                statusBadge(value)
            } else {
                Text(value ?? "—")
                    .foregroundStyle(value != nil ? .primary : .tertiary)
                    .multilineTextAlignment(.trailing)
            }
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(label): \(value ?? "Not set")")
    }

    private func statusBadge(_ status: String) -> some View {
        Text(status)
            .font(.caption)
            .fontWeight(.medium)
            .padding(.horizontal, 8)
            .padding(.vertical, 2)
            .background(statusColor(status).opacity(0.15))
            .foregroundStyle(statusColor(status))
            .clipShape(Capsule())
    }

    private func statusColor(_ status: String) -> Color {
        switch status.lowercased() {
        case "active":
            return .green
        case "suspended":
            return .orange
        case "cancelled":
            return .red
        default:
            return .gray
        }
    }

    // MARK: - Formatting

    private func formattedCharge(_ charge: Double) -> String {
        let formatter = NumberFormatter()
        formatter.numberStyle = .currency
        formatter.currencyCode = "GBP"
        formatter.maximumFractionDigits = 2
        return formatter.string(from: NSNumber(value: charge)) ?? "£\(String(format: "%.2f", charge))"
    }

    // MARK: - Loading State

    private var loadingView: some View {
        VStack(spacing: 16) {
            ProgressView()
                .controlSize(.large)
            Text("Loading service...")
                .font(.subheadline)
                .foregroundStyle(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .accessibilityLabel("Loading service details")
    }

    // MARK: - Error State

    private func errorView(message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle")
                .font(.system(size: 48))
                .foregroundStyle(.orange)

            Text("Unable to Load Service")
                .font(.headline)

            Text(message)
                .font(.subheadline)
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal)

            Button {
                Task {
                    await loadService()
                }
            } label: {
                Label("Retry", systemImage: "arrow.clockwise")
                    .fontWeight(.medium)
            }
            .buttonStyle(.borderedProminent)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Error loading service: \(message)")
    }

    // MARK: - Network Operations

    @MainActor
    private func loadService() async {
        isLoading = true
        errorMessage = nil

        do {
            let endpoint = Endpoint(path: "/admin/services/\(serviceId)")
            let response: ServiceDetailResponse = try await apiClient.request(endpoint)
            service = response.data
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "An unexpected error occurred."
        }

        isLoading = false
    }
}

#Preview {
    NavigationStack {
        ServiceDetailView(serviceId: 1, apiClient: APIClient(authManager: AuthManager()))
    }
}
