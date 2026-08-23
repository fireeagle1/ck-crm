import SwiftUI

/// Invoice list screen with status filter and infinite-scroll pagination.
///
/// Displays invoice status, amount (GBP), invoice date, due date, paid date,
/// and customer name for each record. Supports status filtering via a Picker
/// and a swipe action to send payment reminders for overdue invoices.
struct InvoiceListView: View {
    @State private var viewModel: InvoiceListViewModel
    @State private var showingCreateSheet = false

    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
        _viewModel = State(initialValue: InvoiceListViewModel(apiClient: apiClient))
    }

    var body: some View {
        @Bindable var viewModel = viewModel

        Group {
            if viewModel.isLoading && viewModel.invoices.isEmpty {
                loadingView
            } else if let errorMessage = viewModel.errorMessage, viewModel.invoices.isEmpty {
                errorView(message: errorMessage)
            } else {
                invoiceList
            }
        }
        .navigationTitle("Invoices")
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                HStack(spacing: 12) {
                    Button {
                        showingCreateSheet = true
                    } label: {
                        Label("Create Invoice", systemImage: "plus")
                    }
                    .accessibilityLabel("Create new invoice")

                    Menu {
                        Picker("Status", selection: $viewModel.selectedStatus) {
                            ForEach(InvoiceStatusFilter.allCases) { status in
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
                }
            }
        }
        .sheet(isPresented: $showingCreateSheet) {
            InvoiceCreateView(apiClient: apiClient) {
                await viewModel.loadInitial()
            }
        }
        .alert(
            viewModel.reminderResult?.success == true ? "Reminder Sent" : "Reminder Failed",
            isPresented: Binding(
                get: { viewModel.reminderResult != nil },
                set: { if !$0 { viewModel.reminderResult = nil } }
            )
        ) {
            Button("OK", role: .cancel) {}
        } message: {
            if let result = viewModel.reminderResult {
                Text(result.message)
            }
        }
        .task {
            if viewModel.invoices.isEmpty {
                await viewModel.loadInitial()
            }
        }
    }

    // MARK: - Invoice List

    private var invoiceList: some View {
        List {
            ForEach(viewModel.invoices) { invoice in
                invoiceRow(invoice)
                    .swipeActions(edge: .trailing, allowsFullSwipe: false) {
                        if invoice.isOverdue {
                            Button {
                                Task {
                                    await viewModel.sendReminder(for: invoice)
                                }
                            } label: {
                                Label("Remind", systemImage: "bell")
                            }
                            .tint(.orange)
                        }
                    }
                    .onAppear {
                        if invoice.id == viewModel.invoices.last?.id {
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
            if viewModel.invoices.isEmpty && !viewModel.isLoading {
                ContentUnavailableView(
                    "No Invoices",
                    systemImage: "doc.text",
                    description: Text("No invoices match the current filter.")
                )
            }
        }
    }

    // MARK: - Invoice Row

    private func invoiceRow(_ invoice: InvoiceListItem) -> some View {
        CKRow {
            VStack(alignment: .leading, spacing: 4) {
                Text(invoice.customerName ?? "Unknown Customer")
                    .font(CKTypography.headline)
                    .foregroundStyle(CKTheme.textPrimary)
                    .lineLimit(1)

                Text(formattedAmount(invoice.invoiceAmount))
                    .font(CKTypography.callout)
                    .foregroundStyle(CKTheme.textPrimary)

                HStack(spacing: 8) {
                    Text("Issued: \(invoice.invoiceDate.map { formattedDate($0) } ?? "—")")
                        .font(CKTypography.caption)
                        .foregroundStyle(CKTheme.textSecondary)

                    Label("Due: \(invoice.dueDate.map { formattedDate($0) } ?? "—")", systemImage: "calendar")
                        .font(CKTypography.caption)
                        .foregroundStyle(invoice.isOverdue ? CKTheme.error : CKTheme.textSecondary)
                }

                if let paidDate = invoice.paidDate {
                    Label("Paid: \(formattedDate(paidDate))", systemImage: "checkmark.circle")
                        .font(CKTypography.caption)
                        .foregroundStyle(CKTheme.success)
                }
            }
        } trailing: {
            statusBadge(invoice.invoiceStatus)
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(invoice.customerName ?? "Unknown"), \(formattedAmount(invoice.invoiceAmount)), \(invoice.invoiceStatus)")
    }

    // MARK: - Status Badge

    private func statusBadge(_ status: String) -> some View {
        Text(status)
            .font(CKTypography.caption)
            .fontWeight(.medium)
            .padding(.horizontal, 8)
            .padding(.vertical, 2)
            .background(statusColor(status).opacity(0.15))
            .foregroundStyle(statusColor(status))
            .clipShape(Capsule())
    }

    private func statusColor(_ status: String) -> Color {
        switch status.lowercased() {
        case "paid":
            return CKTheme.success
        case "unpaid":
            return CKTheme.warning
        case "overdue":
            return CKTheme.error
        default:
            return CKTheme.textTertiary
        }
    }

    // MARK: - Formatting

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
        .accessibilityLabel("Loading more invoices")
    }

    // MARK: - Loading State

    private var loadingView: some View {
        VStack(spacing: 16) {
            ProgressView()
                .controlSize(.large)
            Text("Loading invoices...")
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(CKTheme.backgroundPrimary)
        .accessibilityLabel("Loading invoice list")
    }

    // MARK: - Error State

    private func errorView(message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle")
                .font(.system(size: 48))
                .foregroundStyle(CKTheme.warning)

            Text("Unable to Load Invoices")
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
        .accessibilityLabel("Error loading invoices: \(message)")
    }
}

#Preview {
    NavigationStack {
        InvoiceListView(apiClient: APIClient(authManager: AuthManager()))
    }
}
