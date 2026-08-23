import SwiftUI

/// Shows services belonging to a specific customer.
struct CustomerServicesView: View {
    @State private var services: [ServiceListItem] = []
    @State private var isLoading = true
    @State private var errorMessage: String?

    private let companyId: Int
    private let customerName: String
    private let apiClient: APIClient

    init(companyId: Int, customerName: String, apiClient: APIClient) {
        self.companyId = companyId
        self.customerName = customerName
        self.apiClient = apiClient
    }

    var body: some View {
        Group {
            if isLoading {
                ProgressView("Loading services...")
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
            } else if let errorMessage {
                errorView(errorMessage)
            } else if services.isEmpty {
                ContentUnavailableView("No Services", systemImage: "server.rack", description: Text("No services found for this customer."))
            } else {
                serviceList
            }
        }
        .navigationTitle("Services")
        .navigationBarTitleDisplayMode(.inline)
        .background(CKTheme.backgroundPrimary)
        .task { await loadServices() }
    }

    private var serviceList: some View {
        List(services) { service in
            NavigationLink(destination: ServiceDetailView(serviceId: service.serviceId, apiClient: apiClient)) {
                VStack(alignment: .leading, spacing: 4) {
                    Text(service.serviceShort)
                        .font(CKTypography.headline)
                        .foregroundStyle(CKTheme.textPrimary)
                    HStack {
                        if let type = service.serviceType {
                            Text(type)
                                .font(CKTypography.caption)
                                .foregroundStyle(CKTheme.textSecondary)
                        }
                        Spacer()
                        if let charge = service.serviceMonthlyCharge {
                            Text(formatCharge(charge))
                                .font(CKTypography.callout)
                                .foregroundStyle(CKTheme.textPrimary)
                        }
                    }
                    if let domain = service.domainName, !domain.isEmpty {
                        Label(domain, systemImage: "globe")
                            .font(CKTypography.caption)
                            .foregroundStyle(CKTheme.info)
                    }
                    Text(service.status)
                        .font(CKTypography.caption)
                        .fontWeight(.medium)
                        .padding(.horizontal, 6)
                        .padding(.vertical, 2)
                        .background(statusColor(service.status).opacity(0.15))
                        .foregroundStyle(statusColor(service.status))
                        .clipShape(Capsule())
                }
                .padding(.vertical, 2)
            }
        }
        .listStyle(.plain)
        .scrollContentBackground(.hidden)
        .background(CKTheme.backgroundPrimary)
    }

    private func formatCharge(_ charge: Double) -> String {
        let formatter = NumberFormatter()
        formatter.numberStyle = .currency
        formatter.currencyCode = "GBP"
        return formatter.string(from: NSNumber(value: charge)) ?? "£\(String(format: "%.2f", charge))"
    }

    private func statusColor(_ status: String) -> Color {
        switch status.lowercased() {
        case "active": return CKTheme.success
        case "suspended": return CKTheme.warning
        case "cancelled": return CKTheme.error
        default: return CKTheme.textTertiary
        }
    }

    @MainActor
    private func loadServices() async {
        isLoading = true
        errorMessage = nil
        do {
            let endpoint = Endpoint(path: "/admin/services", queryItems: ["customer_id": String(companyId)])
            let response: PaginatedResponse<ServiceListItem> = try await apiClient.request(endpoint)
            services = response.data
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
            Button { Task { await loadServices() } } label: { Label("Retry", systemImage: "arrow.clockwise") }.buttonStyle(.borderedProminent).tint(CKTheme.accent)
        }.frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}
