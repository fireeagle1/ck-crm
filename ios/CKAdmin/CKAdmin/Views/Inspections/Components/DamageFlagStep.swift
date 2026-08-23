import SwiftUI

// MARK: - DamageFlagStep View

/// A step in the return inspection flow that allows the admin to flag equipment as damaged.
/// When the toggle is on, a prominent warning label informs the admin that flagged assets
/// will be marked as "In Repair".
///
/// Requirements: 17.5, 17.6
struct DamageFlagStep: View {
    @Binding var isDamaged: Bool

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            Text("Damage Assessment")
                .font(.headline)

            Text("Has the equipment been damaged?")
                .font(.subheadline)
                .foregroundStyle(.secondary)

            Toggle("Flag as Damaged", isOn: $isDamaged)
                .toggleStyle(SwitchToggleStyle(tint: .red))
                .padding()
                .background(Color(.systemGray6))
                .clipShape(RoundedRectangle(cornerRadius: 8))

            if isDamaged {
                HStack(spacing: 8) {
                    Image(systemName: "exclamationmark.triangle.fill")
                        .foregroundStyle(.orange)
                    Text("Flagged assets will be marked as In Repair")
                        .font(.subheadline)
                        .fontWeight(.medium)
                        .foregroundStyle(.orange)
                }
                .padding()
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(Color.orange.opacity(0.1))
                .clipShape(RoundedRectangle(cornerRadius: 8))
            }

            Spacer()
        }
        .padding()
    }
}

// MARK: - Preview

#Preview("Damage Flag Off") {
    DamageFlagStep(isDamaged: .constant(false))
}

#Preview("Damage Flag On") {
    DamageFlagStep(isDamaged: .constant(true))
}
