import Foundation

/// Response from GET /api/admin/ticket-context?customer_id=X
/// Provides assets, services, and users for a customer to populate the ticket form.
struct TicketContextResponse: Decodable {
    let assets: [ContextAsset]
    let services: [ContextService]
    let users: [ContextUser]
}

struct ContextAsset: Decodable, Identifiable, Hashable {
    let deviceId: Int
    let deviceName: String
    let deviceType: String?
    let location: String?

    var id: Int { deviceId }
}

struct ContextService: Decodable, Identifiable, Hashable {
    let serviceId: Int
    let serviceShort: String
    let serviceType: String?

    var id: Int { serviceId }
}

struct ContextUser: Decodable, Identifiable, Hashable {
    let id: Int
    let name: String
    let email: String
}
