import Foundation
import UIKit
import UserNotifications
import Observation

// MARK: - Notification Destination

/// Represents a deep-link destination triggered by tapping a push notification.
enum NotificationDestination: Equatable {
    case ticket(Int)
    case invoice(Int)
    case dashboard
}

// MARK: - PushManager

/// Manages APNs registration, device token forwarding to the backend, and notification handling.
///
/// Uses the `@Observable` macro (iOS 17+) so SwiftUI views can react to
/// `pendingDestination` changes for deep-link navigation.
@Observable
final class PushManager: NSObject {

    // MARK: - Public Properties

    /// The destination to navigate to when a notification is tapped.
    /// Views observe this to trigger navigation, then set it back to nil.
    var pendingDestination: NotificationDestination?

    /// Whether push notification permissions have been granted.
    private(set) var isAuthorized: Bool = false

    // MARK: - Private Properties

    /// The current APNs device token as a hex string.
    private(set) var deviceToken: String?

    /// Reference to the API client for registering/unregistering tokens.
    private var apiClient: APIClient?

    // MARK: - Initialization

    override init() {
        super.init()
    }

    // MARK: - Configuration

    /// Sets the API client reference. Called after the APIClient is created.
    func configure(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    // MARK: - Authorization

    /// Requests notification authorization from the user and registers for remote notifications on grant.
    ///
    /// Call this when the user becomes authenticated and the app is ready to receive notifications.
    @MainActor
    func requestAuthorization() async {
        let center = UNUserNotificationCenter.current()

        do {
            let granted = try await center.requestAuthorization(options: [.alert, .badge, .sound])
            isAuthorized = granted

            if granted {
                UIApplication.shared.registerForRemoteNotifications()
            }
        } catch {
            isAuthorized = false
        }
    }

    // MARK: - Device Token Handling

    /// Called when APNs successfully provides a device token.
    ///
    /// Converts the token data to a hex string and registers it with the backend.
    ///
    /// - Parameter deviceTokenData: The raw token data from APNs.
    func didRegisterForRemoteNotifications(deviceToken data: Data) {
        let hexToken = data.map { String(format: "%02x", $0) }.joined()
        self.deviceToken = hexToken

        Task { @MainActor in
            await registerToken(hexToken)
        }
    }

    /// Called when APNs registration fails.
    ///
    /// - Parameter error: The error that prevented registration.
    func didFailToRegisterForRemoteNotifications(error: Error) {
        // Registration failed — clear any stale token
        self.deviceToken = nil
    }

    // MARK: - Token Registration

    /// Registers the device token with the backend API.
    ///
    /// - Parameter token: The hex-encoded device token string.
    @MainActor
    private func registerToken(_ token: String) async {
        guard let apiClient else { return }

        let endpoint = Endpoint(
            method: .post,
            path: "/admin/device-tokens",
            body: DeviceTokenBody(token: token)
        )

        do {
            try await apiClient.requestVoid(endpoint)
        } catch {
            // Best-effort registration — will retry on next app launch
        }
    }

    /// Unregisters the current device token from the backend.
    ///
    /// Call this on logout to stop receiving push notifications for this device.
    @MainActor
    func unregisterToken() async {
        guard let apiClient, let token = deviceToken else { return }

        let endpoint = Endpoint(
            method: .delete,
            path: "/admin/device-tokens",
            body: DeviceTokenBody(token: token)
        )

        do {
            try await apiClient.requestVoid(endpoint)
        } catch {
            // Best-effort unregistration
        }

        deviceToken = nil
    }
}

// MARK: - UNUserNotificationCenterDelegate

extension PushManager: UNUserNotificationCenterDelegate {

    /// Called when a notification is delivered while the app is in the foreground.
    /// Displays the notification as a banner even when the app is active.
    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        willPresent notification: UNNotification,
        withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void
    ) {
        completionHandler([.banner, .badge, .sound])
    }

    /// Called when the user taps a notification.
    /// Parses the payload to determine navigation destination.
    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        didReceive response: UNNotificationResponse,
        withCompletionHandler completionHandler: @escaping () -> Void
    ) {
        let userInfo = response.notification.request.content.userInfo

        if let ticketId = userInfo["ticket_id"] as? Int {
            pendingDestination = .ticket(ticketId)
        } else if let ticketIdString = userInfo["ticket_id"] as? String,
                  let ticketId = Int(ticketIdString) {
            pendingDestination = .ticket(ticketId)
        } else if let invoiceId = userInfo["invoice_id"] as? Int {
            pendingDestination = .invoice(invoiceId)
        } else if let invoiceIdString = userInfo["invoice_id"] as? String,
                  let invoiceId = Int(invoiceIdString) {
            pendingDestination = .invoice(invoiceId)
        } else {
            pendingDestination = .dashboard
        }

        completionHandler()
    }
}

// MARK: - Request Body

/// JSON body for device token registration/unregistration requests.
private struct DeviceTokenBody: Encodable {
    let token: String
}
