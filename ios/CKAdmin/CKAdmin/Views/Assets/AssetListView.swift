import SwiftUI

@Observable
final class AssetListViewModel {
    private(set) var assets: [AssetListItem] = []
    private(set) var isLoading = false
    private(set) var isLoadingMore = false
    private(set) var errorMessage: String?
    private(set) var currentPage = 0
    private(set) var lastPage = 1
    var hasMorePages: Bool { currentPage < lastPage }

    var searchText: String = "" {
        didSet {
            guard searchText != oldValue else { return }
            searchTask?.cancel()
            searchTask = Task { @MainActor in
                try? await Task.sleep(for: .milliseconds(300))
                guard !Task.isCancelled else { return }
                await resetAndLoad()
            }
        }
    }

    var selectedStatus: AssetStatusFilter = .all {
        didSet {
            guard selectedStatus != oldValue else { return }
            Task { @MainActor in await resetAndLoad() }
        }
    }

    private let apiClient: APIClient
    private var searchTask: Task<Void, Never>?

    init(apiClient: APIClient) { self.apiClient = apiClient }

    @MainActor func loadInitial() async {
        guard !isLoading else { return }
        await resetAndLoad()
    }

    @MainActor func loadNextPage() async {
        guard !isLoadingMore, hasMorePages else { return }
        isLoadingMore = true
        do {
            let response = try await fetchAssets(page: currentPage + 1)
            assets.append(contentsOf: response.data)
            currentPage = response.meta.currentPage
            lastPage = response.meta.lastPage
        } catch let error as APIError { errorMessage = error.errorDescription }
        catch { errorMessage = "An unexpected error occurred." }
        isLoadingMore = false
    }

    @MainActor private func resetAndLoad() async {
        assets = []; currentPage = 0; lastPage = 1; errorMessage = nil; isLoading = true
        do {
            let response = try await fetchAssets(page: 1)
            assets = response.data
            currentPage = response.meta.currentPage
            lastPage = response.meta.lastPage
        } catch let error as APIError { errorMessage = error.errorDescription }
        catch { errorMessage = "An unexpected error occurred." }
        isLoading = false
    }

    private func fetchAssets(page: Int) async throws -> PaginatedResponse<AssetListItem> {
        var q: [String: String] = ["page": String(page)]
        let trimmed = searchText.trimmingCharacters(in: .whitespacesAndNewlines)
        if !trimmed.isEmpty { q["search"] = trimmed }
        if let sv = selectedStatus.queryValue { q["status"] = sv }
        return try await apiClient.request(Endpoint(path: "/admin/assets", queryItems: q))
    }
}

struct AssetListView: View {
    @State private var viewModel: AssetListViewModel
    @State private var showingCreateForm = false
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
        _viewModel = State(initialValue: AssetListViewModel(apiClient: apiClient))
    }

    var body: some View {
        @Bindable var viewModel = viewModel

        Group {
            if viewModel.isLoading && viewModel.assets.isEmpty { loadingView }
            else if let err = viewModel.errorMessage, viewModel.assets.isEmpty { errorView(err) }
            else { assetList }
        }
        .navigationTitle("CMDB")
        .searchable(text: $viewModel.searchText, prompt: "Search assets")
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                HStack(spacing: 12) {
                    Menu {
                        Picker("Status", selection: $viewModel.selectedStatus) {
                            ForEach(AssetStatusFilter.allCases) { Text($0.rawValue).tag($0) }
                        }
                    } label: {
                        Label(viewModel.selectedStatus == .all ? "Filter" : viewModel.selectedStatus.rawValue, systemImage: "line.3.horizontal.decrease.circle")
                    }
                    Button { showingCreateForm = true } label: { Label("Add Asset", systemImage: "plus") }
                }
            }
        }
        .sheet(isPresented: $showingCreateForm) {
            AssetFormView(mode: .create, apiClient: apiClient) { _ in await viewModel.loadInitial() }
        }
        .task { if viewModel.assets.isEmpty { await viewModel.loadInitial() } }
    }

    private var assetList: some View {
        List {
            ForEach(viewModel.assets) { asset in
                NavigationLink(value: asset) { assetRow(asset) }
                    .onAppear {
                        if asset.id == viewModel.assets.last?.id { Task { await viewModel.loadNextPage() } }
                    }
            }
            if viewModel.isLoadingMore {
                HStack { Spacer(); ProgressView().controlSize(.small); Text("Loading...").font(CKTypography.caption).foregroundStyle(CKTheme.textSecondary); Spacer() }
            }
        }
        .listStyle(.plain)
        .scrollContentBackground(.hidden)
        .background(CKTheme.backgroundPrimary)
        .navigationDestination(for: AssetListItem.self) { asset in
            AssetDetailView(deviceId: asset.deviceId, apiClient: apiClient)
        }
        .refreshable { await viewModel.loadInitial() }
        .overlay {
            if viewModel.assets.isEmpty && !viewModel.isLoading {
                ContentUnavailableView("No Assets", systemImage: "desktopcomputer", description: Text("No assets match the current filter."))
            }
        }
    }

    private func assetRow(_ asset: AssetListItem) -> some View {
        CKRow {
            VStack(alignment: .leading, spacing: 4) {
                Text(asset.deviceName)
                    .font(CKTypography.headline)
                    .foregroundStyle(CKTheme.textPrimary)
                    .lineLimit(1)
                if let type = asset.deviceType, !type.isEmpty {
                    Text(type)
                        .font(CKTypography.body)
                        .foregroundStyle(CKTheme.textSecondary)
                }
                HStack {
                    if let customer = asset.customerName {
                        Label(customer, systemImage: "person")
                            .font(CKTypography.caption)
                            .foregroundStyle(CKTheme.textSecondary)
                    }
                    Spacer()
                    if let loc = asset.location, !loc.isEmpty {
                        Label(loc, systemImage: "mappin")
                            .font(CKTypography.caption)
                            .foregroundStyle(CKTheme.textSecondary)
                    }
                }
            }
        } trailing: {
            Text(asset.assetStatus)
                .font(CKTypography.caption)
                .fontWeight(.medium)
                .padding(.horizontal, 8)
                .padding(.vertical, 2)
                .background(statusColor(asset.assetStatus).opacity(0.15))
                .foregroundStyle(statusColor(asset.assetStatus))
                .clipShape(Capsule())
        }
    }

    private func statusColor(_ s: String) -> Color {
        switch s.lowercased() {
        case "active": return CKTheme.success
        case "decommissioned": return CKTheme.textTertiary
        case "in repair": return CKTheme.warning
        default: return CKTheme.textTertiary
        }
    }

    private var loadingView: some View {
        VStack(spacing: 16) { ProgressView().controlSize(.large); Text("Loading assets...").font(CKTypography.body).foregroundStyle(CKTheme.textSecondary) }
            .frame(maxWidth: .infinity, maxHeight: .infinity)
            .background(CKTheme.backgroundPrimary)
    }

    private func errorView(_ message: String) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "exclamationmark.triangle").font(.system(size: 48)).foregroundStyle(CKTheme.warning)
            Text("Unable to Load Assets").font(CKTypography.headline).foregroundStyle(CKTheme.textPrimary)
            Text(message).font(CKTypography.body).foregroundStyle(CKTheme.textSecondary).multilineTextAlignment(.center).padding(.horizontal)
            Button { Task { await viewModel.loadInitial() } } label: { Label("Retry", systemImage: "arrow.clockwise").fontWeight(.medium) }.buttonStyle(.borderedProminent).tint(CKTheme.accent)
        }.frame(maxWidth: .infinity, maxHeight: .infinity).background(CKTheme.backgroundPrimary)
    }
}
