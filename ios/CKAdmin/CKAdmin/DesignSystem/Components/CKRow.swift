import SwiftUI

/// A reusable row component with leading and trailing ViewBuilder slots,
/// providing consistent spacing and padding for list items across the app.
struct CKRow<Leading: View, Trailing: View>: View {
    let leading: Leading
    let trailing: Trailing

    init(
        @ViewBuilder leading: () -> Leading,
        @ViewBuilder trailing: () -> Trailing
    ) {
        self.leading = leading()
        self.trailing = trailing()
    }

    var body: some View {
        HStack(spacing: 12) {
            leading
            Spacer()
            trailing
        }
        .padding(.vertical, 12)
        .padding(.horizontal, 16)
    }
}

#Preview {
    VStack(spacing: 0) {
        CKRow {
            VStack(alignment: .leading, spacing: 4) {
                Text("Customer Name")
                    .font(CKTypography.headline)
                    .foregroundStyle(CKTheme.textPrimary)
                Text("customer@example.com")
                    .font(CKTypography.caption)
                    .foregroundStyle(CKTheme.textSecondary)
            }
        } trailing: {
            Text("Active")
                .font(CKTypography.caption)
                .foregroundStyle(CKTheme.accent)
        }

        Divider()

        CKRow {
            Text("Simple Row")
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textPrimary)
        } trailing: {
            Image(systemName: "chevron.right")
                .foregroundStyle(CKTheme.textSecondary)
        }
    }
    .background(CKTheme.backgroundCard)
    .clipShape(RoundedRectangle(cornerRadius: 12))
    .padding()
    .background(CKTheme.backgroundPrimary)
}
