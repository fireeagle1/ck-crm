import SwiftUI

/// Service list screen with status filter and infinite-scroll pagination.
///
/// Displays service name, type, domain, status, monthly charge, and customer
/// for each record. Supports status filtering via a Picker and loads additional
/// pages when the last item appears on screen.
/// Provides navigation to service detail and a toolbar button to create new services.
struct ServiceListView: View {
    @State private var viewModel: ServiceListViewModel
    @State private var showingCreateForm = false

    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
        _viewModel = State(initialValue: ServiceListViewModel(apiClient: apiClient))
    }

    var body: some View {
        @Bindable var viewModel = viewModel

        Group {
            if viewModel.isLoading && viewModel.services.isEmpty {
                loadingView
            } else if let errorMessage = viewModel.errorMessage, viewModel.services.isEmpty {
                errorView(message: errorMessage)
            } else {
                serviceList
            }
        }
        .navigationTitle("Services")
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                HStack(spacing: 12) {
                    Menu {
                        Picker("Status", selection: $viewModel.selectedStatus) {
                            ForEach(ServiceStatusFilter.allCases) { status in
                                Text(status.rawValue).tag(status)
                            }
                        }
                    } label: {
                        Label(
                            viewModel.selectedStatus == .all ? "Filter" : viewModel.selectedStatus.rawValue,
                            systemImage: "line.3.horizontal.decrease.circle"
                        )
                    }
                    .accessibilityLabel("Filter by status")

                    Button {
                        showingCreateForm = true
                    } label: {
                        Label("Add Service", systemImage: "plus")
                    }
                }
            }
        }
        .sheet(isPresented: $showingCreateForm) {
            NavigationStack {
                ServiceFormView(
                    mode: .create,
                    apiClient: apiClient
                ) { _ in
                    await viewModel.loadInitial()
                }
            }
        }
        .task {
            if viewModel.services.isEmpty {
                await viewModel.loadInitial()
            }
        }
    }

    // MARK: - Service List

    private var serviceList: some View {
        List {
            ForEach(viewModel.services) { service in
                NavigationLink(value: service) {
                    serviceRow(service)
                }
                .onAppear {
                    if service.id == viewModel.services.last?.id {
                        Task {
                            await viewModel.loadNextPage()
                        }
                    }
                }
            }

            if viewModel.isLoadingMore {
                loadingMoreRow
            }
        }
        .listStyle(.plain)
        .navigationDestination(for: ServiceListItem.self) { service in
            ServiceDetailView(serviceId: service.serviceId, apiClient: apiClient)
        }
        .refreshable {
            await viewModel.loadInitial()
        }
        .overlay {
            if viewModel.services.isEmpty && !viewModel.isLoading {
                ContentUnavailableView(
                    "No Services",
                    systemImage: "server.rack",
                    description: Text("No services match the current filter.")
                )
            }
        }
    }

    // MARK: - Service Row

    private func serviceRow(_ service: ServiceListItem) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            HStack {
                Text(service.serviceShort)
                    .font(.body)
                    .fontWeight(.medium)
                    .lineLimit(1)

                Spacer()

                statusBadge(service.status)
            }

            if let serviceType = service.serviceType, !serviceType.isEmpty {
                Text(serviceType)
                    .font(.subheadline)
                    .foregroundStyle(.secondary)
                    .lineLimit(1)
            }

            if let domainName = service.domainName, !domainName.isEmpty {
                Label(domainName, systemImage: "globe")
                    .font(.caption)
                    .foregroundStyle(.secondary)
                    .lineLimit(1)
            }

            HStack {
                if let customerName = service.customerName, !customerName.isEmpty {
                    Label(customerName, systemImage: "person")
                        .font(.caption)
                        .foregroundStyle(.secondary)
                        .lineLimit(1)
                }

                Spacer()

                if let charge = service.serviceMonthlyCharge, charge > 0 {
                    Text(formattedCharge(charge))
                        .font(.caption)
                        .fontWeight(.medium)
                        .foregroundStyle(.green)
                }
            }
        }
        .padding(.vertical, 2)
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(service.serviceShort), \(service.status)")
    }

    // MARK: - Status Badge

    private func statusBadge(_ status: String) -> some View {
        Text(status)
            .font(.caption2)
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

    // MARK: - Loading More Row

    private var loadingMoreRow: some View {
        HStack {
            Spacer()
            ProgressView()
                .controlSize(.small)
            Text("Loading more...")
                .font(.caption)
                .foregroundStyle(.secondary)
            Spacer()
        }
        .listRowSeparator(.hidden)
        .accessibilityLabel("Loading more services")
    }

    // MARK: - Loading State

    private var loadingView: some View {
        VStack(spacing: 16) {
            ProgressView()
                .controlSize(.large)
            Text("Loading services...")
                .font(.subheadline)
                .foregroundStyle(.secondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .accessibilityLabel("Loading service list")
    }

    // MARK: - Error State

    private func errorView(message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle")
                .font(.system(size: 48))
                .foregroundStyle(.orange)

            Text("Unable to Load Services")
                .font(.headline)

            Text(message)
                .font(.subheadline)
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal)

            Button {
                Task {
                    await viewModel.loadInitial()
                }
            } label: {
                Label("Retry", systemImage: "arrow.clockwise")
                    .fontWeight(.medium)
            }
            .buttonStyle(.borderedProminent)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Error loading services: \(message)")
    }
}

#Preview {
    NavigationStack {
        ServiceListView(apiClient: APIClient(authManager: AuthManager()))
    }
}
