import Foundation

// MARK: - HTTP Method

/// Supported HTTP methods for API requests.
enum HTTPMethod: String {
    case get = "GET"
    case post = "POST"
    case put = "PUT"
    case delete = "DELETE"
}

// MARK: - Endpoint

/// Describes an API endpoint with its method, path, query parameters, and optional body.
struct Endpoint {
    let method: HTTPMethod
    let path: String
    let queryItems: [String: String]?
    let body: (any Encodable)?

    init(
        method: HTTPMethod = .get,
        path: String,
        queryItems: [String: String]? = nil,
        body: (any Encodable)? = nil
    ) {
        self.method = method
        self.path = path
        self.queryItems = queryItems
        self.body = body
    }
}

// MARK: - API Error

/// Typed errors returned by the API client, mapped from HTTP status codes.
enum APIError: LocalizedError {
    /// 401 — Token is invalid or expired. Triggers automatic logout.
    case unauthenticated

    /// 403 — User lacks permission. Includes the server message.
    case forbidden(String)

    /// 404 — Requested resource does not exist.
    case notFound

    /// 422 — Validation failed. Contains field-level error messages.
    case validationFailed([String: [String]])

    /// Connectivity or transport-layer failure.
    case networkError(Error)

    /// 5xx — Server-side failure.
    case serverError

    /// Response could not be decoded or was not a valid HTTP response.
    case invalidResponse

    var errorDescription: String? {
        switch self {
        case .unauthenticated:
            return "Session expired. Please log in again."
        case .forbidden(let message):
            return message
        case .notFound:
            return "The requested resource was not found."
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

// MARK: - Error Response

/// JSON structure for API error responses.
private struct APIErrorResponse: Decodable {
    let message: String?
    let errors: [String: [String]]?
}

// MARK: - Empty Response

/// Used when the API returns no body (e.g., 204 No Content).
struct EmptyResponse: Decodable {}

// MARK: - API Client

/// HTTP client for communicating with the CRM admin API.
///
/// Automatically attaches the Bearer token from `AuthManager` to every request
/// and handles 401 responses by triggering logout.
final class APIClient {

    // MARK: - Properties

    private let baseURL: URL
    private let authManager: AuthManager
    private let session: URLSession
    private let encoder: JSONEncoder
    private let decoder: JSONDecoder

    // MARK: - Initialization

    /// Creates an API client.
    ///
    /// - Parameters:
    ///   - authManager: The auth manager providing the Bearer token.
    ///   - session: The URL session to use for requests. Defaults to `.shared`.
    init(authManager: AuthManager, session: URLSession = .shared) {
        self.baseURL = APIConfig.baseURL
        self.authManager = authManager
        self.session = session
        self.encoder = JSONEncoder()
        self.decoder = JSONDecoder()
        self.decoder.keyDecodingStrategy = .convertFromSnakeCase
        self.decoder.dateDecodingStrategy = .custom { decoder in
            let container = try decoder.singleValueContainer()
            let dateString = try container.decode(String.self)

            // Try ISO 8601 with fractional seconds (Laravel format: 2024-01-15T10:30:00.000000Z)
            let isoWithFraction = ISO8601DateFormatter()
            isoWithFraction.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
            if let date = isoWithFraction.date(from: dateString) {
                return date
            }

            // Try standard ISO 8601 without fractional seconds
            let isoStandard = ISO8601DateFormatter()
            isoStandard.formatOptions = [.withInternetDateTime]
            if let date = isoStandard.date(from: dateString) {
                return date
            }

            // Try MySQL/Laravel format without T separator (e.g., "2024-01-15 10:30:00")
            let mysqlFormatter = DateFormatter()
            mysqlFormatter.locale = Locale(identifier: "en_US_POSIX")
            mysqlFormatter.timeZone = TimeZone(identifier: "UTC")
            mysqlFormatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
            if let date = mysqlFormatter.date(from: dateString) {
                return date
            }

            throw DecodingError.dataCorruptedError(in: container, debugDescription: "Cannot decode date: \(dateString)")
        }
    }

    // MARK: - Public Methods

    /// Executes an API request and decodes the response.
    ///
    /// - Parameter endpoint: The endpoint configuration describing method, path, and payload.
    /// - Returns: The decoded response of type `T`.
    /// - Throws: `APIError` if the request fails or the response indicates an error.
    @MainActor
    func request<T: Decodable>(_ endpoint: Endpoint) async throws -> T {
        let urlRequest = try buildRequest(for: endpoint)

        let data: Data
        let response: URLResponse

        do {
            (data, response) = try await session.data(for: urlRequest)
        } catch {
            throw APIError.networkError(error)
        }

        guard let httpResponse = response as? HTTPURLResponse else {
            throw APIError.invalidResponse
        }

        try await handleStatusCode(httpResponse.statusCode, data: data)

        // Handle 204 No Content — return EmptyResponse if that's the expected type
        if httpResponse.statusCode == 204 {
            if let empty = EmptyResponse() as? T {
                return empty
            }
            throw APIError.invalidResponse
        }

        do {
            return try decoder.decode(T.self, from: data)
        } catch {
            throw APIError.invalidResponse
        }
    }

    /// Executes an API request that returns no meaningful body (e.g., DELETE → 204).
    ///
    /// - Parameter endpoint: The endpoint configuration.
    /// - Throws: `APIError` if the request fails.
    @MainActor
    func requestVoid(_ endpoint: Endpoint) async throws {
        let _: EmptyResponse = try await request(endpoint)
    }

    // MARK: - Private Methods

    /// Builds a URLRequest from an Endpoint definition.
    private func buildRequest(for endpoint: Endpoint) throws -> URLRequest {
        // Construct URL: baseURL + /api + endpoint.path
        var components = URLComponents(url: baseURL.appendingPathComponent("/api\(endpoint.path)"), resolvingAgainstBaseURL: false)

        // Add query parameters
        if let queryItems = endpoint.queryItems, !queryItems.isEmpty {
            components?.queryItems = queryItems.map { URLQueryItem(name: $0.key, value: $0.value) }
        }

        guard let url = components?.url else {
            throw APIError.invalidResponse
        }

        var request = URLRequest(url: url)
        request.httpMethod = endpoint.method.rawValue
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        // Attach Bearer token if available
        if let token = authManager.token {
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }

        // Encode body if present
        if let body = endpoint.body {
            request.httpBody = try encoder.encode(AnyEncodable(body))
        }

        return request
    }

    /// Maps HTTP status codes to typed errors.
    ///
    /// For 401 responses, triggers `authManager.handleUnauthorized()` before throwing.
    @MainActor
    private func handleStatusCode(_ statusCode: Int, data: Data) async throws {
        switch statusCode {
        case 200...299:
            return // Success — no error

        case 401:
            authManager.handleUnauthorized()
            throw APIError.unauthenticated

        case 403:
            let errorResponse = try? decoder.decode(APIErrorResponse.self, from: data)
            let message = errorResponse?.message ?? "Access denied."
            throw APIError.forbidden(message)

        case 404:
            throw APIError.notFound

        case 422:
            let errorResponse = try? decoder.decode(APIErrorResponse.self, from: data)
            throw APIError.validationFailed(errorResponse?.errors ?? [:])

        case 500...599:
            throw APIError.serverError

        default:
            throw APIError.serverError
        }
    }
}

// MARK: - AnyEncodable

/// Type-erased Encodable wrapper to allow encoding `any Encodable` values.
private struct AnyEncodable: Encodable {
    private let encodeFunc: (Encoder) throws -> Void

    init(_ wrapped: any Encodable) {
        self.encodeFunc = { encoder in
            try wrapped.encode(to: encoder)
        }
    }

    func encode(to encoder: Encoder) throws {
        try encodeFunc(encoder)
    }
}
