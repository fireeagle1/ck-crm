import SwiftUI

/// Typography scale for the CK Enterprises UK design system.
/// Uses SF Pro system font family with explicit sizes and weights.
enum CKTypography {
    static let largeTitle = Font.system(size: 28, weight: .bold)
    static let title = Font.system(size: 22, weight: .bold)
    static let title2 = Font.system(size: 18, weight: .semibold)
    static let headline = Font.system(size: 16, weight: .semibold)
    static let body = Font.system(size: 15, weight: .regular)
    static let callout = Font.system(size: 14, weight: .medium)
    static let caption = Font.system(size: 12, weight: .regular)
    static let metric = Font.system(size: 24, weight: .bold).monospacedDigit()
    static let metricSmall = Font.system(size: 18, weight: .semibold).monospacedDigit()
}
