import Foundation

// MARK: - Paginated Response

/// Generic paginated response wrapper matching Laravel's pagination structure.
struct PaginatedResponse<T: Decodable>: Decodable {
    let data: [T]
    let meta: PaginationMeta
}

/// Pagination metadata returned by the API.
struct PaginationMeta: Decodable {
    let currentPage: Int
    let lastPage: Int
    let perPage: Int
    let total: Int
}

// MARK: - Customer List Item

/// A customer record as returned in the paginated list endpoint.
struct CustomerListItem: Decodable, Identifiable, Hashable {
    let companyId: Int
    let companyName: String
    let customerName: String
    let phoneNumber: String?

    var id: Int { companyId }
}
