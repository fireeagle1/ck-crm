import SwiftUI

/// Rental list screen with fulfilment stage filter and infinite-scroll pagination.
///
/// Displays each booking's product name, customer name, date range, quantity,
/// and fulfilment stage. Supports stage filtering via a toolbar picker,
/// pull-to-refresh, and navigation to BookingDetailView on tap.
struct RentalListView: View {
    @State private var viewModel: RentalListViewModel

    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
        _viewModel = State(initialValue: RentalListViewModel(apiClient: apiClient))
    }

    var body: some View {
        @Bindable var viewModel = viewModel

        Group {
            if viewModel.isLoading && viewModel.bookings.isEmpty {
                loadingView
            } else if let errorMessage = viewModel.errorMessage, viewModel.bookings.isEmpty {
                errorView(message: errorMessage)
            } else {
                rentalList
            }
        }
        .navigationTitle("Rentals")
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                Menu {
                    Picker("Stage", selection: $viewModel.selectedStage) {
                        ForEach(FulfilmentStageFilter.allCases) { stage in
                            Text(stage.displayName).tag(stage)
                        }
                    }
                } label: {
                    Label(
                        viewModel.selectedStage == .all ? "Filter" : viewModel.selectedStage.displayName,
                        systemImage: "line.3.horizontal.decrease.circle"
                    )
                }
                .accessibilityLabel("Filter by fulfilment stage")
            }
        }
        .task {
            if viewModel.bookings.isEmpty {
                await viewModel.loadInitial()
            }
        }
    }

    // MARK: - Rental List

    private var rentalList: some View {
        List {
            ForEach(viewModel.bookings) { booking in
                NavigationLink(destination: BookingDetailView(bookingId: booking.id, apiClient: apiClient)) {
                    rentalRow(booking)
                }
                .listRowBackground(CKTheme.backgroundCard)
                .onAppear {
                    if booking.id == viewModel.bookings.last?.id {
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
        .scrollContentBackground(.hidden)
        .background(CKTheme.backgroundPrimary)
        .refreshable {
            await viewModel.loadInitial()
        }
        .overlay {
            if viewModel.bookings.isEmpty && !viewModel.isLoading {
                ContentUnavailableView(
                    "No Rentals",
                    systemImage: "shippingbox",
                    description: Text("No rentals match the current filter.")
                )
            }
        }
    }

    // MARK: - Rental Row

    private func rentalRow(_ booking: BookingListItem) -> some View {
        CKRow {
            VStack(alignment: .leading, spacing: 6) {
                HStack {
                    Text(booking.productName ?? "Unknown Product")
                        .font(CKTypography.headline)
                        .foregroundStyle(CKTheme.textPrimary)
                        .lineLimit(1)

                    Spacer()

                    stageCapsule(booking.fulfilmentStage)
                }

                HStack {
                    Text(booking.customerName ?? "Unknown Customer")
                        .font(CKTypography.body)
                        .foregroundStyle(CKTheme.textSecondary)
                        .lineLimit(1)

                    Spacer()

                    quantityBadge(booking.quantity)
                }

                HStack {
                    Label(dateRangeText(start: booking.startDate, end: booking.endDate), systemImage: "calendar")
                        .font(CKTypography.caption)
                        .foregroundStyle(CKTheme.textTertiary)

                    Spacer()
                }
            }
        } trailing: {
            EmptyView()
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(booking.productName ?? "Unknown"), \(booking.customerName ?? "Unknown"), \(booking.fulfilmentStage)")
    }

    // MARK: - Stage Capsule

    private func stageCapsule(_ stage: String) -> some View {
        Text(stageDisplayName(stage))
            .font(.caption2)
            .fontWeight(.medium)
            .padding(.horizontal, 8)
            .padding(.vertical, 2)
            .background(stageColor(stage).opacity(0.15))
            .foregroundStyle(stageColor(stage))
            .clipShape(Capsule())
    }

    private func stageDisplayName(_ stage: String) -> String {
        switch stage {
        case "ordered": return "Ordered"
        case "packing": return "Packing"
        case "ready": return "Ready"
        case "checked_out": return "Checked Out"
        case "returned": return "Returned"
        case "inspected": return "Inspected"
        default: return stage.capitalized
        }
    }

    private func stageColor(_ stage: String) -> Color {
        switch stage {
        case "ordered": return CKTheme.info
        case "packing": return CKTheme.warning
        case "ready": return CKTheme.accent
        case "checked_out": return CKTheme.accent
        case "returned": return CKTheme.success
        case "inspected": return CKTheme.textSecondary
        default: return CKTheme.textTertiary
        }
    }

    // MARK: - Quantity Badge

    private func quantityBadge(_ quantity: Int) -> some View {
        Text("×\(quantity)")
            .font(CKTypography.caption)
            .fontWeight(.semibold)
            .padding(.horizontal, 6)
            .padding(.vertical, 2)
            .background(CKTheme.textSecondary.opacity(0.12))
            .clipShape(Capsule())
    }

    // MARK: - Date Range

    private func dateRangeText(start: String?, end: String?) -> String {
        let formattedStart = start.map { formattedDate($0) } ?? "—"
        let formattedEnd = end.map { formattedDate($0) } ?? "—"
        return "\(formattedStart) → \(formattedEnd)"
    }

    private func formattedDate(_ dateString: String) -> String {
        let inputFormatter = DateFormatter()
        inputFormatter.dateFormat = "yyyy-MM-dd"
        inputFormatter.locale = Locale(identifier: "en_US_POSIX")

        guard let date = inputFormatter.date(from: dateString) else {
            return dateString
        }

        let outputFormatter = DateFormatter()
        outputFormatter.dateStyle = .medium
        outputFormatter.timeStyle = .none
        return outputFormatter.string(from: date)
    }

    // MARK: - Loading More Row

    private var loadingMoreRow: some View {
        HStack {
            Spacer()
            ProgressView()
                .controlSize(.small)
            Text("Loading more...")
                .font(CKTypography.caption)
                .foregroundStyle(CKTheme.textSecondary)
            Spacer()
        }
        .listRowSeparator(.hidden)
        .accessibilityLabel("Loading more rentals")
    }

    // MARK: - Loading State

    private var loadingView: some View {
        VStack(spacing: 16) {
            ProgressView()
                .controlSize(.large)
            Text("Loading rentals...")
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(CKTheme.backgroundPrimary)
        .accessibilityLabel("Loading rental list")
    }

    // MARK: - Error State

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

            Button {
                Task {
                    await viewModel.loadInitial()
                }
            } label: {
                Label("Retry", systemImage: "arrow.clockwise")
                    .fontWeight(.medium)
            }
            .buttonStyle(.borderedProminent)
            .tint(CKTheme.accent)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(CKTheme.backgroundPrimary)
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Error loading rentals: \(message)")
    }
}

#Preview {
    NavigationStack {
        RentalListView(apiClient: APIClient(authManager: AuthManager()))
    }
}
