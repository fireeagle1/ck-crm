import SwiftUI

/// Main tab-based navigation view shown when the user is authenticated.
struct ContentView: View {
    @Environment(AuthManager.self) private var authManager
    @State private var showingLogoutConfirmation = false
    @State private var showingScanner = false
    @State private var selectedTab = 0

    // Deep-link navigation paths (per tab)
    @State private var assetNavPath = NavigationPath()
    @State private var shopNavPath = NavigationPath()

    let apiClient: APIClient

    var body: some View {
        ZStack(alignment: .bottomTrailing) {
            TabView(selection: $selectedTab) {
                NavigationStack {
                    DashboardView(apiClient: apiClient, selectedTab: $selectedTab)
                        .toolbar {
                            ToolbarItem(placement: .topBarTrailing) {
                                Button(role: .destructive) { showingLogoutConfirmation = true } label: {
                                    Label("Logout", systemImage: "rectangle.portrait.and.arrow.right")
                                }
                            }
                        }
                }
                .tabItem { Label("Dashboard", systemImage: "chart.bar") }
                .tag(0)

                NavigationStack {
                    TicketListView(apiClient: apiClient)
                }
                .tabItem { Label("Tickets", systemImage: "ticket") }
                .tag(1)

                NavigationStack {
                    CustomerListView(apiClient: apiClient)
                }
                .tabItem { Label("Customers", systemImage: "person.2") }
                .tag(2)

                NavigationStack(path: $assetNavPath) {
                    AssetListView(apiClient: apiClient)
                        .navigationDestination(for: ScanDeepLink.self) { link in
                            AssetDetailView(deviceId: link.id, apiClient: apiClient)
                        }
                }
                .tabItem { Label("CMDB", systemImage: "desktopcomputer") }
                .tag(3)

                NavigationStack {
                    InvoiceListView(apiClient: apiClient)
                }
                .tabItem { Label("Invoices", systemImage: "doc.text") }
                .tag(4)

                NavigationStack(path: $shopNavPath) {
                    ShopHubView(apiClient: apiClient)
                        .navigationDestination(for: ScanDeepLink.self) { link in
                            OrderActionDetailView(apiClient: apiClient, orderId: link.id)
                        }
                }
                .tabItem { Label("Shop", systemImage: "bag") }
                .tag(5)

                NavigationStack {
                    RentalListView(apiClient: apiClient)
                }
                .tabItem { Label("Rentals", systemImage: "shippingbox") }
                .tag(6)
            }

            // Floating Scan Button
            Button {
                showingScanner = true
            } label: {
                Image(systemName: "qrcode.viewfinder")
                    .font(.title2)
                    .fontWeight(.semibold)
                    .foregroundStyle(.white)
                    .frame(width: 56, height: 56)
                    .background(Color.blue, in: Circle())
                    .shadow(color: .black.opacity(0.25), radius: 8, y: 4)
            }
            .padding(.trailing, 20)
            .padding(.bottom, 90)
            .accessibilityLabel("Scan QR Code")
        }
        .confirmationDialog(
            "Are you sure you want to log out?",
            isPresented: $showingLogoutConfirmation,
            titleVisibility: .visible
        ) {
            Button("Log Out", role: .destructive) { Task { await authManager.logout() } }
            Button("Cancel", role: .cancel) {}
        }
        .fullScreenCover(isPresented: $showingScanner) {
            QRScannerView(apiClient: apiClient) { result in
                handleScanResult(result)
            }
        }
    }

    // MARK: - Scan Result Navigation

    private func handleScanResult(_ result: ScanResult) {
        // Small delay to allow the scanner sheet to dismiss before navigating
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.3) {
            switch result.type {
            case "asset":
                selectedTab = 3
                assetNavPath = NavigationPath()
                assetNavPath.append(ScanDeepLink(type: "asset", id: result.id))

            case "order":
                selectedTab = 5
                shopNavPath = NavigationPath()
                shopNavPath.append(ScanDeepLink(type: "order", id: result.id))

            case "booking":
                // For bookings, navigate to the parent order page
                selectedTab = 5
                shopNavPath = NavigationPath()
                let navId = result.orderId ?? result.id
                shopNavPath.append(ScanDeepLink(type: "order", id: navId))

            default:
                break
            }
        }
    }
}

// MARK: - Deep Link Value

/// Hashable value type for programmatic navigation from QR scan results.
struct ScanDeepLink: Hashable {
    let type: String
    let id: Int
}

#Preview {
    ContentView(apiClient: APIClient(authManager: AuthManager()))
        .environment(AuthManager())
}
