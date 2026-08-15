import SwiftUI

/// Main tab-based navigation view shown when the user is authenticated.
struct ContentView: View {
    @Environment(AuthManager.self) private var authManager
    @State private var showingLogoutConfirmation = false
    @State private var selectedTab = 0

    let apiClient: APIClient

    var body: some View {
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
        }
        .confirmationDialog(
            "Are you sure you want to log out?",
            isPresented: $showingLogoutConfirmation,
            titleVisibility: .visible
        ) {
            Button("Log Out", role: .destructive) { Task { await authManager.logout() } }
            Button("Cancel", role: .cancel) {}
        }
    }
}

#Preview {
    ContentView(apiClient: APIClient(authManager: AuthManager()))
        .environment(AuthManager())
}
