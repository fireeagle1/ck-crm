import SwiftUI

/// A reusable card component for displaying a single metric value with an icon and title.
/// Uses CKTheme colours and CKTypography fonts for consistent styling.
///
/// Requirement 7.3: Reusable card-based layout components with consistent padding, corner radius, and shadow.
struct CKMetricCard: View {
    let title: String
    let value: String
    let icon: String
    let color: Color

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                Image(systemName: icon)
                    .font(.body)
                    .foregroundStyle(color)
                Spacer()
            }
            Text(value)
                .font(CKTypography.metric)
                .foregroundStyle(CKTheme.textPrimary)
            Text(title)
                .font(CKTypography.caption)
                .foregroundStyle(CKTheme.textSecondary)
        }
        .padding(16)
        .background(CKTheme.backgroundCard)
        .clipShape(RoundedRectangle(cornerRadius: 12))
        .shadow(color: .black.opacity(0.06), radius: 8, y: 2)
    }
}

#Preview {
    VStack(spacing: 16) {
        CKMetricCard(
            title: "Active Rentals",
            value: "12",
            icon: "shippingbox.fill",
            color: CKTheme.accent
        )
        CKMetricCard(
            title: "Upcoming Returns",
            value: "5",
            icon: "clock.arrow.circlepath",
            color: CKTheme.warning
        )
        CKMetricCard(
            title: "Recently Returned",
            value: "8",
            icon: "checkmark.circle.fill",
            color: CKTheme.success
        )
    }
    .padding()
    .background(CKTheme.backgroundPrimary)
}
