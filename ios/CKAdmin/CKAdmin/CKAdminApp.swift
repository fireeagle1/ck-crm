import SwiftUI
import UserNotifications

// MARK: - App Delegate

/// UIApplicationDelegate adaptor that forwards APNs callbacks to PushManager.
class AppDelegate: NSObject, UIApplicationDelegate {
    /// Reference to the shared PushManager. Set by CKAdminApp on launch.
    var pushManager: PushManager?

    func application(
        _ application: UIApplication,
        didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data
    ) {
        pushManager?.didRegisterForRemoteNotifications(deviceToken: deviceToken)
    }

    func application(
        _ application: UIApplication,
        didFailToRegisterForRemoteNotificationsWithError error: Error
    ) {
        pushManager?.didFailToRegisterForRemoteNotifications(error: error)
    }
}

// MARK: - App Entry Point

@main
struct CKAdminApp: App {
    @UIApplicationDelegateAdaptor(AppDelegate.self) private var appDelegate
    @State private var authManager = AuthManager()
    @State private var pushManager = PushManager()

    var body: some Scene {
        WindowGroup {
            if authManager.isAuthenticated {
                ContentView(apiClient: APIClient(authManager: authManager))
                    .environment(authManager)
                    .environment(pushManager)
                    .onAppear {
                        configurePushManager()
                    }
            } else {
                LoginView()
                    .environment(authManager)
            }
        }
    }

    /// Configures the PushManager with the API client and requests notification permissions.
    private func configurePushManager() {
        let apiClient = APIClient(authManager: authManager)
        pushManager.configure(apiClient: apiClient)

        // Wire the delegate adaptor
        appDelegate.pushManager = pushManager
        UNUserNotificationCenter.current().delegate = pushManager

        // Wire logout hook to unregister device token
        authManager.onWillLogout = { [pushManager] in
            await pushManager.unregisterToken()
        }

        // Request authorization when authenticated
        Task {
            await pushManager.requestAuthorization()
        }
    }
}
