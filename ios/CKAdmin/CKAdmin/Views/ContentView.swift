import SwiftUI

/// Main tab-based navigation view shown when the user is authenticated.
struct ContentView: View {
    @Environment(AuthManager.self) private var authManager
    @State private var showingLogoutConfirmation = false
    @State private var showingScanner = false
    @State private var selectedTab = 0
    @State private var scanNavigationTarget: ScanResult?

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

                NavigationStack {
                    AssetListView(apiClient: apiClient)
                }
                .tabItem { Label("CMDB", systemImage: "desktopcomputer") }
                .tag(3)

                NavigationStack {
                    InvoiceListView(apiClient: apiClient)
                }
                .tabItem { Label("Invoices", systemImage: "doc.text") }
                .tag(4)

                NavigationStack {
                    ShopHubView(apiClient: apiClient)
                }
                .tabItem { Label("Shop", systemImage: "bag") }
                .tag(5)
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
            .padding(.bottom, 90) // Above tab bar
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
        switch result.type {
        case "asset":
            selectedTab = 3 // CMDB tab
        case "order":
            selectedTab = 5 // Shop tab
        case "booking":
            selectedTab = 5 // Shop tab
        default:
            break
        }

        // Store for navigation (views will pick this up)
        scanNavigationTarget = result
    }
}

#Preview {
    ContentView(apiClient: APIClient(authManager: AuthManager()))
        .environment(AuthManager())
}
