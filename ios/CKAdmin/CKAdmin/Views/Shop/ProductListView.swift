import SwiftUI

/// Shop products list with type filter and archived toggle.
struct ProductListView: View {
    @State private var viewModel: ProductListViewModel

    init(apiClient: APIClient) {
        _viewModel = State(initialValue: ProductListViewModel(apiClient: apiClient))
    }

    var body: some View {
        @Bindable var viewModel = viewModel

        Group {
            if viewModel.isLoading && viewModel.products.isEmpty {
                loadingView
            } else if let errorMessage = viewModel.errorMessage, viewModel.products.isEmpty {
                errorView(message: errorMessage)
            } else {
                productList
            }
        }
        .navigationTitle("Products")
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                Menu {
                    Section("Product Type") {
                        Picker("Type", selection: $viewModel.selectedTypeFilter) {
                            ForEach(ProductTypeFilter.allCases) { filter in
                                Text(filter.displayName).tag(filter)
                            }
                        }
                    }
                    Section {
                        Toggle("Show Archived", isOn: $viewModel.showArchived)
                    }
                } label: {
                    Label("Filter", systemImage: "line.3.horizontal.decrease.circle")
                }
                .accessibilityLabel("Filter products")
            }
        }
        .task {
            if viewModel.products.isEmpty {
                await viewModel.loadInitial()
            }
        }
    }

    // MARK: - Product List

    private var productList: some View {
        List {
            ForEach(viewModel.products) { product in
                productRow(product)
                    .onAppear {
                        if product.id == viewModel.products.last?.id {
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
            if viewModel.products.isEmpty && !viewModel.isLoading {
                ContentUnavailableView(
                    "No Products",
                    systemImage: "shippingbox",
                    description: Text("No products match the current filters.")
                )
            }
        }
    }

    // MARK: - Product Row

    private func productRow(_ product: ProductListItem) -> some View {
        CKRow {
            VStack(alignment: .leading, spacing: 4) {
                Text(product.name)
                    .font(CKTypography.headline)
                    .foregroundStyle(CKTheme.textPrimary)
                    .lineLimit(1)

                Text(formattedPrice(product))
                    .font(CKTypography.callout)
                    .foregroundStyle(CKTheme.textPrimary)

                HStack {
                    if let stock = product.stockQuantity {
                        Label("\(stock) in stock", systemImage: "archivebox")
                            .font(CKTypography.caption)
                            .foregroundStyle(stock > 0 ? CKTheme.textSecondary : CKTheme.error)
                    } else {
                        Label("Unlimited stock", systemImage: "infinity")
                            .font(CKTypography.caption)
                            .foregroundStyle(CKTheme.textSecondary)
                    }

                    Spacer()

                    Circle()
                        .fill(product.isAvailable ? CKTheme.success : CKTheme.error)
                        .frame(width: 8, height: 8)
                    Text(product.isAvailable ? "Available" : "Unavailable")
                        .font(CKTypography.caption)
                        .foregroundStyle(CKTheme.textSecondary)
                }
            }
        } trailing: {
            VStack(alignment: .trailing, spacing: 6) {
                if product.isArchived {
                    Text("Archived")
                        .font(CKTypography.caption)
                        .fontWeight(.medium)
                        .padding(.horizontal, 8)
                        .padding(.vertical, 2)
                        .background(CKTheme.textTertiary.opacity(0.15))
                        .foregroundStyle(CKTheme.textTertiary)
                        .clipShape(Capsule())
                }

                Text(product.productTypeLabel)
                    .font(CKTypography.caption)
                    .padding(.horizontal, 6)
                    .padding(.vertical, 1)
                    .background(typeColor(product.productType).opacity(0.12))
                    .foregroundStyle(typeColor(product.productType))
                    .clipShape(Capsule())
            }
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(product.name), \(product.productTypeLabel), \(formattedPrice(product))")
    }

    // MARK: - Helpers

    private func formattedPrice(_ product: ProductListItem) -> String {
        let formatter = NumberFormatter()
        formatter.numberStyle = .currency
        formatter.currencyCode = "GBP"
        formatter.maximumFractionDigits = 2
        let base = formatter.string(from: NSNumber(value: product.price)) ?? "£\(String(format: "%.2f", product.price))"

        if let freq = product.billingFrequency {
            return "\(base)/\(freq)"
        }
        return base
    }

    private func typeColor(_ type: String) -> Color {
        switch type {
        case "equipment_rental": return CKTheme.info
        case "hosting": return CKTheme.accent
        case "one_off": return CKTheme.warning
        default: return CKTheme.textTertiary
        }
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
            Text("Loading products...")
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
            Text("Unable to Load Products")
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
        ProductListView(apiClient: APIClient(authManager: AuthManager()))
    }
}
