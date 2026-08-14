import Foundation

// MARK: - Customer Detail

/// Full customer record as returned by the show endpoint.
///
/// Includes all customer fields plus relationship counts (services, tickets, invoices, domains).
struct CustomerDetail: Decodable, Identifiable {
    let companyId: Int
    let companyName: String
    let customerName: String?
    let phoneNumber: String?
    let addressLine1: String?
    let addressLine2: String?
    let city: String?
    let state: String?
    let postalCode: String?
    let country: String?
    let stripeCustomerId: String?
    let bannerImage: String?
    let createdAt: String?
    let updatedAt: String?

    // Relationship counts
    let servicesCount: Int
    let ticketsCount: Int
    let invoicesCount: Int
    let domainsCount: Int

    var id: Int { companyId }
}

// MARK: - Customer Detail Response

/// Wrapper for the single-customer detail API response.
struct CustomerDetailResponse: Decodable {
    let data: CustomerDetail
}

// MARK: - Customer Mutate Response

/// Wrapper for create/update API responses that return the customer data.
struct CustomerMutateResponse: Decodable {
    let data: CustomerDetail
}
