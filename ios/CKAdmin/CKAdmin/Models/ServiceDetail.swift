import Foundation

// MARK: - Service Detail

/// Full service record as returned by the show endpoint.
///
/// Includes all service fields plus the associated customer name.
struct ServiceDetail: Decodable, Identifiable {
    let serviceId: Int
    let serviceShort: String
    let serviceType: String?
    let domainName: String?
    let status: String
    let serviceMonthlyCharge: Double?
    let servicePaymentFrequency: String?
    let companyId: Int
    let customerName: String?
    let startDate: String?
    let endDate: String?
    let nextPaymentDate: String?
    let stripeSubscriptionId: String?
    let createdAt: String?
    let updatedAt: String?

    var id: Int { serviceId }
}

// MARK: - Service Detail Response

/// Wrapper for the single-service detail API response.
struct ServiceDetailResponse: Decodable {
    let data: ServiceDetail
}

// MARK: - Service Mutate Response

/// Wrapper for create/update API responses that return the service data.
struct ServiceMutateResponse: Decodable {
    let data: ServiceDetail
}
