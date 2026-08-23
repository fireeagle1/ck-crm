import SwiftUI

/// Shows invoices belonging to a specific customer.
struct CustomerInvoicesView: View {
    @State private var invoices: [InvoiceListItem] = []
    @State private var isLoading = true
    @State private var errorMessage: String?

    private let companyId: Int
    private let apiClient: APIClient

    init(companyId: Int, apiClient: APIClient) {
        self.companyId = companyId
        self.apiClient = apiClient
    }

    var body: some View {
        Group {
            if isLoading {
                ProgressView("Loading invoices...")
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
            } else if let errorMessage {
                errorView(errorMessage)
            } else if invoices.isEmpty {
                ContentUnavailableView("No Invoices", systemImage: "doc.text", description: Text("No invoices found for this customer."))
            } else {
                invoiceList
            }
        }
        .navigationTitle("Invoices")
        .navigationBarTitleDisplayMode(.inline)
        .background(CKTheme.backgroundPrimary)
        .task { await loadInvoices() }
    }

    private var invoiceList: some View {
        List(invoices) { invoice in
            VStack(alignment: .leading, spacing: 4) {
                HStack {
                    Text(formattedAmount(invoice.invoiceAmount))
                        .font(CKTypography.headline)
                        .foregroundStyle(CKTheme.textPrimary)
                    Spacer()
                    Text(invoice.invoiceStatus)
                        .font(CKTypography.caption)
                        .fontWeight(.medium)
                        .padding(.horizontal, 8)
                        .padding(.vertical, 2)
                        .background(statusColor(invoice.invoiceStatus).opacity(0.15))
                        .foregroundStyle(statusColor(invoice.invoiceStatus))
                        .clipShape(Capsule())
                }
                HStack {
                    Text("Issued: \(invoice.invoiceDate ?? "—")")
                        .font(CKTypography.caption)
                        .foregroundStyle(CKTheme.textSecondary)
                    Spacer()
                    Text("Due: \(invoice.dueDate ?? "—")")
                        .font(CKTypography.caption)
                        .foregroundStyle(invoice.isOverdue ? CKTheme.error : CKTheme.textSecondary)
                }
            }
            .padding(.vertical, 2)
        }
        .listStyle(.plain)
        .scrollContentBackground(.hidden)
        .background(CKTheme.backgroundPrimary)
    }

    private func formattedAmount(_ amount: Double) -> String {
        let formatter = NumberFormatter()
        formatter.numberStyle = .currency
        formatter.currencyCode = "GBP"
        return formatter.string(from: NSNumber(value: amount)) ?? "£\(String(format: "%.2f", amount))"
    }

    private func statusColor(_ status: String) -> Color {
        switch status.lowercased() {
        case "paid": return CKTheme.success
        case "unpaid": return CKTheme.warning
        case "overdue": return CKTheme.error
        default: return CKTheme.textTertiary
        }
    }

    @MainActor
    private func loadInvoices() async {
        isLoading = true
        errorMessage = nil
        do {
            let endpoint = Endpoint(path: "/admin/invoices", queryItems: ["customer_id": String(companyId)])
            let response: PaginatedResponse<InvoiceListItem> = try await apiClient.request(endpoint)
            invoices = response.data
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
            Button { Task { await loadInvoices() } } label: { Label("Retry", systemImage: "arrow.clockwise") }.buttonStyle(.borderedProminent).tint(CKTheme.accent)
        }.frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}
