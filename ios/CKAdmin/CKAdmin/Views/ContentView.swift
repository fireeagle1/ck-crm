import SwiftUI

/// Main tab-based navigation view shown when the user is authenticated.
/// Provides five tabs (Dashboard, Customers, Services, Tickets, Invoices)
/// each wrapped in a NavigationStack.
struct ContentView: View {
    @Environment(AuthManager.self) private var authManager
    @State private var showingLogoutConfirmation = false

    let apiClient: APIClient

    var body: some View {
        TabView {
            NavigationStack {
                DashboardView(apiClient: apiClient)
                    .toolbar {
                        ToolbarItem(placement: .topBarTrailing) {
                            Button(role: .destructive) {
                                showingLogoutConfirmation = true
                            } label: {
                                Label("Logout", systemImage: "rectangle.portrait.and.arrow.right")
                            }
                        }
                    }
            }
            .tabItem {
                Label("Dashboard", systemImage: "chart.bar")
            }

            NavigationStack {
                CustomerListView(apiClient: apiClient)
            }
            .tabItem {
                Label("Customers", systemImage: "person.2")
            }

            NavigationStack {
                ServiceListView(apiClient: apiClient)
            }
            .tabItem {
                Label("Services", systemImage: "server.rack")
            }

            NavigationStack {
                TicketListView(apiClient: apiClient)
            }
            .tabItem {
                Label("Tickets", systemImage: "ticket")
            }

            NavigationStack {
                InvoiceListView(apiClient: apiClient)
            }
            .tabItem {
                Label("Invoices", systemImage: "doc.text")
            }
        }
        .confirmationDialog(
            "Are you sure you want to log out?",
            isPresented: $showingLogoutConfirmation,
            titleVisibility: .visible
        ) {
            Button("Log Out", role: .destructive) {
                Task {
                    await authManager.logout()
                }
            }
            Button("Cancel", role: .cancel) {}
        }
    }
}

#Preview {
    ContentView(apiClient: APIClient(authManager: AuthManager()))
        .environment(AuthManager())
}
