import SwiftUI

/// A reusable card container that wraps content with consistent padding,
/// corner radius, shadow, and background colour from the design system.
struct CKCard<Content: View>: View {
    let content: Content

    init(@ViewBuilder content: () -> Content) {
        self.content = content()
    }

    var body: some View {
        content
            .padding(16)
            .background(CKTheme.backgroundCard)
            .clipShape(RoundedRectangle(cornerRadius: 12))
            .shadow(color: .black.opacity(0.06), radius: 8, y: 2)
    }
}

#Preview {
    CKCard {
        VStack(alignment: .leading, spacing: 8) {
            Text("Card Title")
                .font(CKTypography.headline)
            Text("This is a sample card using the CK design system.")
                .font(CKTypography.body)
                .foregroundStyle(CKTheme.textSecondary)
        }
    }
    .padding()
    .background(CKTheme.backgroundPrimary)
}
