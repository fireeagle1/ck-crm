import SwiftUI

/// Rental bookings list with status filter.
struct RentalListView: View {
    @State private var viewModel: RentalListViewModel

    init(apiClient: APIClient) {
        _viewModel = State(initialValue: RentalListViewModel(apiClient: apiClient))
    }

    var body: some View {
        @Bindable var viewModel = viewModel

        Group {
            if viewModel.isLoading && viewModel.rentals.isEmpty {
                loadingView
            } else if let errorMessage = viewModel.errorMessage, viewModel.rentals.isEmpty {
                errorView(message: errorMessage)
            } else {
                rentalList
            }
        }
        .navigationTitle("Rentals")
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                Menu {
                    Picker("Status", selection: $viewModel.selectedStatusFilter) {
                        ForEach(RentalStatusFilter.allCases) { filter in
                            Text(filter.displayName).tag(filter)
                        }
                    }
                } label: {
                    Label(
                        viewModel.selectedStatusFilter == .all ? "Filter" : viewModel.selectedStatusFilter.displayName,
                        systemImage: "line.3.horizontal.decrease.circle"
                    )
                }
                .accessibilityLabel("Filter by status")
            }
        }
        .task {
            if viewModel.rentals.isEmpty {
                await viewModel.loadInitial()
            }
        }
    }

    // MARK: - Rental List

    private var rentalList: some View {
        List {
            ForEach(viewModel.rentals) { rental in
                rentalRow(rental)
                    .onAppear {
                        if rental.id == viewModel.rentals.last?.id {
                            Task { await viewModel.loadNextPage() }
                        }
                    }
            }

            if viewModel.isLoadingMore {
                loadingMoreRow
            }
        }
        .listStyle(.plain)
        .scrollContentBackground(.hidden)
        .background(CKTheme.backgroundPrimary)
        .refreshable {
            await viewModel.loadInitial()
        }
        .overlay {
            if viewModel.rentals.isEmpty && !viewModel.isLoading {
                ContentUnavailableView(
                    "No Rentals",
                    systemImage: "calendar.badge.clock",
                    description: Text("No rental bookings match the current filter.")
                )
            }
        }
    }

    // MARK: - Rental Row

    private func rentalRow(_ rental: RentalListItem) -> some View {
        CKRow {
            VStack(alignment: .leading, spacing: 4) {
                Text(rental.productName ?? "Unknown Product")
                    .font(CKTypography.headline)
                    .foregroundStyle(CKTheme.textPrimary)
                    .lineLimit(1)

                Text(rental.customerName ?? "Unknown Customer")
                    .font(CKTypography.body)
                    .foregroundStyle(CKTheme.textSecondary)
                    .lineLimit(1)

                HStack {
                    if let start = rental.startDate, let end = rental.endDate {
                        Label("\(formattedDate(start)) – \(formattedDate(end))", systemImage: "calendar")
                            .font(CKTypography.caption)
                            .foregroundStyle(CKTheme.textSecondary)
                    }

                    Spacer()

                    if rental.quantity > 1 {
                        Label("Qty: \(rental.quantity)", systemImage: "number")
                            .font(CKTypography.caption)
                            .foregroundStyle(CKTheme.textSecondary)
                    }
                }

                if rental.status == "returned", let returnedAt = rental.returnedAt {
                    Label("Returned: \(returnedAt, style: .date)", systemImage: "checkmark.circle")
                        .font(CKTypography.caption)
                        .foregroundStyle(CKTheme.success)
                }
            }
        } trailing: {
            VStack(alignment: .trailing, spacing: 6) {
                statusBadge(rental.status)
                Text(formattedAmount(rental.totalPrice))
                    .font(CKTypography.callout)
                    .foregroundStyle(CKTheme.textPrimary)
            }
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(rental.productName ?? "Unknown"), \(rental.customerName ?? "Unknown"), \(rental.status)")
    }

    // MARK: - Helpers

    private func statusBadge(_ status: String) -> some View {
        Text(status.capitalized)
            .font(CKTypography.caption)
            .fontWeight(.medium)
            .padding(.horizontal, 8)
            .padding(.vertical, 2)
            .background(statusColor(status).opacity(0.15))
            .foregroundStyle(statusColor(status))
            .clipShape(Capsule())
    }

    private func statusColor(_ status: String) -> Color {
        switch status {
        case "active": return CKTheme.info
        case "confirmed": return CKTheme.success
        case "returned": return CKTheme.textTertiary
        case "cancelled": return CKTheme.error
        default: return CKTheme.textTertiary
        }
    }

    private func formattedAmount(_ amount: Double) -> String {
        let formatter = NumberFormatter()
        formatter.numberStyle = .currency
        formatter.currencyCode = "GBP"
        formatter.maximumFractionDigits = 2
        return formatter.string(from: NSNumber(value: amount)) ?? "£\(String(format: "%.2f", amount))"
    }

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

    // MARK: - Loading States

    private var loadingMoreRow: some View {
        HStack {
            Spacer()
            ProgressView().controlSize(.small)
            Text("Loading more...")
                .font(CKTypography.caption)
                .foregroundStyle(CKTheme.textSecondary)
            Spacer()
        }
        .listRowSeparator(.hidden)
    }

    private var loadingView: some View {
        VStack(spacing: 16) {
            ProgressView().controlSize(.large)
            Text("Loading rentals...")
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(CKTheme.backgroundPrimary)
    }

    private func errorView(message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle")
                .font(.system(size: 48))
                .foregroundStyle(CKTheme.warning)
            Text("Unable to Load Rentals")
                .font(CKTypography.headline)
                .foregroundStyle(CKTheme.textPrimary)
            Text(message)
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
                .multilineTextAlignment(.center)
                .padding(.horizontal)
            Button { Task { await viewModel.loadInitial() } } label: {
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
        RentalListView(apiClient: APIClient(authManager: AuthManager()))
    }
}
