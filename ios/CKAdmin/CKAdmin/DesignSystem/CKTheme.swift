import SwiftUI

/// Centralised colour palette for the CKAdmin app, supporting both light and dark mode.
/// All colours are defined programmatically — no asset catalogue entries required.
enum CKTheme {
    // MARK: - Primary Colours

    /// Dark navy primary: #1B2838 (light) / #0D1B2A (dark)
    static let primary = Color(light: Color(hex: 0x1B2838), dark: Color(hex: 0x0D1B2A))

    /// Charcoal variant: #2D3748
    static let primaryVariant = Color(hex: 0x2D3748)

    // MARK: - Accent Colours

    /// Teal accent: #2DD4BF (light) / #14B8A6 (dark)
    static let accent = Color(light: Color(hex: 0x2DD4BF), dark: Color(hex: 0x14B8A6))

    /// Secondary blue accent: #3B82F6
    static let accentSecondary = Color(hex: 0x3B82F6)

    // MARK: - Backgrounds

    /// Primary background — warm off-white: #F8F7F4 (light) / #111827 (dark)
    static let backgroundPrimary = Color(light: Color(hex: 0xF8F7F4), dark: Color(hex: 0x111827))

    /// Secondary background — warm grey: #F1F0EB (light) / #1F2937 (dark)
    static let backgroundSecondary = Color(light: Color(hex: 0xF1F0EB), dark: Color(hex: 0x1F2937))

    /// Card background — white (light) / #1F2937 (dark)
    static let backgroundCard = Color(light: .white, dark: Color(hex: 0x1F2937))

    // MARK: - Text

    /// Primary text — near-black: #1A1A2E (light) / white (dark)
    static let textPrimary = Color(light: Color(hex: 0x1A1A2E), dark: .white)

    /// Secondary text — mid-grey: #6B7280
    static let textSecondary = Color(hex: 0x6B7280)

    /// Tertiary text — light-grey: #9CA3AF
    static let textTertiary = Color(hex: 0x9CA3AF)

    // MARK: - Semantic

    /// Success green: #10B981
    static let success = Color(hex: 0x10B981)

    /// Warning amber: #F59E0B
    static let warning = Color(hex: 0xF59E0B)

    /// Error red: #EF4444
    static let error = Color(hex: 0xEF4444)

    /// Info blue: #3B82F6
    static let info = Color(hex: 0x3B82F6)
}

// MARK: - Colour Helpers

extension Color {
    /// Creates a colour from a hex integer (e.g. 0x1B2838).
    init(hex: UInt) {
        self.init(
            red: Double((hex >> 16) & 0xFF) / 255.0,
            green: Double((hex >> 8) & 0xFF) / 255.0,
            blue: Double(hex & 0xFF) / 255.0
        )
    }

    /// Creates an adaptive colour that responds to the current colour scheme.
    init(light: Color, dark: Color) {
        self.init(uiColor: UIColor { traits in
            traits.userInterfaceStyle == .dark
                ? UIColor(dark)
                : UIColor(light)
        })
    }
}
