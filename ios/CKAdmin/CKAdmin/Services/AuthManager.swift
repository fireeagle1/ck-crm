import Foundation
import Observation
import Security

// MARK: - Configuration

/// Base URL for the CRM API. Update this for production deployment.
enum APIConfig {
    #if DEBUG
    static let baseURL = URL(string: "https://app.ckenterprises.co.uk")!
    #else
    static let baseURL = URL(string: "https://app.ckenterprises.co.uk")!
    #endif
}

// MARK: - Auth Errors

/// Errors that can occur during authentication operations.
enum AuthError: LocalizedError {
    case invalidCredentials
    case accountLocked(String)
    case insufficientPermissions
    case validationFailed([String: [String]])
    case networkError(Error)
    case serverError
    case invalidResponse

    var errorDescription: String? {
        switch self {
        case .invalidCredentials:
            return "Invalid email or password."
        case .accountLocked(let message):
            return message
        case .insufficientPermissions:
            return "Insufficient permissions. Admin access required."
        case .validationFailed(let errors):
            let messages = errors.values.flatMap { $0 }.joined(separator: "\n")
            return messages.isEmpty ? "Validation failed." : messages
        case .networkError(let error):
            return "Network error: \(error.localizedDescription)"
        case .serverError:
            return "Server error. Please try again later."
        case .invalidResponse:
            return "Unexpected server response."
        }
    }
}

// MARK: - Login Response

/// The JSON structure returned by POST /api/admin/auth/login.
private struct LoginResponse: Decodable {
    let token: String
    let user: LoginUser

    struct LoginUser: Decodable {
        let id: Int
        let name: String
        let email: String
    }
}

/// The JSON structure returned for error responses.
private struct ErrorResponse: Decodable {
    let message: String?
    let errors: [String: [String]]?
}

// MARK: - AuthManager

/// Manages authentication state, Keychain token storage, and login/logout API calls.
///
/// Uses the `@Observable` macro (iOS 17+) so SwiftUI views automatically
/// react to changes in `isAuthenticated`.
@Observable
final class AuthManager {

    // MARK: - Public Properties

    /// Whether the user currently holds a valid token.
    var isAuthenticated: Bool { token != nil }

    /// The current user's name (populated on login).
    private(set) var currentUserName: String?

    /// The current user's email (populated on login).
    private(set) var currentUserEmail: String?

    // MARK: - Private Properties

    /// The stored Sanctum token. Changes to this trigger `isAuthenticated` updates.
    private(set) var token: String?

    /// Keychain service identifier for token storage.
    private let keychainKey = "com.ckenterprises.admin.token"

    /// URLSession used for network requests.
    private let session: URLSession

    /// Callback invoked before logout completes. Used by PushManager to unregister the device token.
    var onWillLogout: (() async -> Void)?

    // MARK: - Initialization

    init(session: URLSession = .shared) {
        self.session = session
        self.token = loadTokenFromKeychain()
    }

    // MARK: - Public Methods

    /// Authenticates the user with email and password.
    ///
    /// On success, stores the token in Keychain and sets `isAuthenticated` to true.
    ///
    /// - Parameters:
    ///   - email: The admin user's email address.
    ///   - password: The admin user's password.
    /// - Throws: `AuthError` if authentication fails.
    @MainActor
    func login(email: String, password: String) async throws {
        let url = APIConfig.baseURL.appendingPathComponent("/api/admin/auth/login")

        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        let body: [String: String] = ["email": email, "password": password]
        request.httpBody = try JSONEncoder().encode(body)

        let data: Data
        let response: URLResponse

        do {
            (data, response) = try await session.data(for: request)
        } catch {
            throw AuthError.networkError(error)
        }

        guard let httpResponse = response as? HTTPURLResponse else {
            throw AuthError.invalidResponse
        }

        switch httpResponse.statusCode {
        case 200:
            let loginResponse = try JSONDecoder().decode(LoginResponse.self, from: data)
            self.token = loginResponse.token
            self.currentUserName = loginResponse.user.name
            self.currentUserEmail = loginResponse.user.email
            saveTokenToKeychain(loginResponse.token)

        case 401:
            throw AuthError.invalidCredentials

        case 403:
            let errorResponse = try? JSONDecoder().decode(ErrorResponse.self, from: data)
            let message = errorResponse?.message ?? "Access denied."
            if message.lowercased().contains("locked") {
                throw AuthError.accountLocked(message)
            }
            throw AuthError.insufficientPermissions

        case 422:
            let errorResponse = try? JSONDecoder().decode(ErrorResponse.self, from: data)
            throw AuthError.validationFailed(errorResponse?.errors ?? [:])

        default:
            throw AuthError.serverError
        }
    }

    /// Logs out the current user.
    ///
    /// Unregisters the device token, calls the API logout endpoint (best-effort),
    /// then clears the local token.
    @MainActor
    func logout() async {
        // Unregister device token before revoking the auth token
        await onWillLogout?()

        // Attempt server-side logout (best-effort — clear locally regardless)
        if let token = self.token {
            let url = APIConfig.baseURL.appendingPathComponent("/api/admin/auth/logout")

            var request = URLRequest(url: url)
            request.httpMethod = "POST"
            request.setValue("application/json", forHTTPHeaderField: "Accept")
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")

            _ = try? await session.data(for: request)
        }

        clearAuthState()
    }

    /// Handles a 401 Unauthenticated response from any API call.
    ///
    /// Clears the stored token and resets auth state, causing the app to
    /// navigate back to the login screen.
    @MainActor
    func handleUnauthorized() {
        clearAuthState()
    }

    // MARK: - Private Methods

    /// Clears all authentication state (token + user info) and removes Keychain entry.
    private func clearAuthState() {
        token = nil
        currentUserName = nil
        currentUserEmail = nil
        deleteTokenFromKeychain()
    }

    // MARK: - Keychain Operations

    /// Loads the stored token from the iOS Keychain.
    /// - Returns: The stored token string, or nil if not found.
    private func loadTokenFromKeychain() -> String? {
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrAccount as String: keychainKey,
            kSecReturnData as String: true,
            kSecMatchLimit as String: kSecMatchLimitOne
        ]

        var result: AnyObject?
        let status = SecItemCopyMatching(query as CFDictionary, &result)

        guard status == errSecSuccess,
              let data = result as? Data,
              let token = String(data: data, encoding: .utf8) else {
            return nil
        }

        return token
    }

    /// Saves the token to the iOS Keychain. Updates existing entry if one exists.
    /// - Parameter token: The Sanctum token to store.
    private func saveTokenToKeychain(_ token: String) {
        guard let data = token.data(using: .utf8) else { return }

        // First try to update an existing entry
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrAccount as String: keychainKey
        ]

        let attributes: [String: Any] = [
            kSecValueData as String: data
        ]

        let updateStatus = SecItemUpdate(query as CFDictionary, attributes as CFDictionary)

        if updateStatus == errSecItemNotFound {
            // No existing entry — add a new one
            var addQuery = query
            addQuery[kSecValueData as String] = data
            addQuery[kSecAttrAccessible as String] = kSecAttrAccessibleAfterFirstUnlock

            SecItemAdd(addQuery as CFDictionary, nil)
        }
    }

    /// Deletes the token from the iOS Keychain.
    private func deleteTokenFromKeychain() {
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrAccount as String: keychainKey
        ]

        SecItemDelete(query as CFDictionary)
    }
}
