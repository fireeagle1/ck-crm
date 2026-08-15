import SwiftUI

/// Hub view for the Shop tab providing segmented navigation between Orders, Products, and Rentals.
struct ShopHubView: View {
    @State private var selectedSection: ShopSection = .orders

    let apiClient: APIClient

    var body: some View {
        VStack(spacing: 0) {
            Picker("Section", selection: $selectedSection) {
                ForEach(ShopSection.allCases) { section in
                    Text(section.title).tag(section)
                }
            }
            .pickerStyle(.segmented)
            .padding(.horizontal)
            .padding(.vertical, 8)

            switch selectedSection {
            case .orders:
                OrderListView(apiClient: apiClient)
            case .products:
                ProductListView(apiClient: apiClient)
            case .rentals:
                RentalListView(apiClient: apiClient)
            }
        }
        .navigationTitle("Shop")
    }
}

/// Sections available within the Shop tab.
enum ShopSection: String, CaseIterable, Identifiable {
    case orders
    case products
    case rentals

    var id: String { rawValue }

    var title: String {
        switch self {
        case .orders: return "Orders"
        case .products: return "Products"
        case .rentals: return "Rentals"
        }
    }
}

#Preview {
    NavigationStack {
        ShopHubView(apiClient: APIClient(authManager: AuthManager()))
    }
}
