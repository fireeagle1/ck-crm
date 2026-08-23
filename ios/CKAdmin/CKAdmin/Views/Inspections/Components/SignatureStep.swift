import SwiftUI

/// A step view that wraps SignatureView with optional agreement text display above the canvas.
/// Exposes a binding for the captured signature image (optional UIImage).
struct SignatureStep: View {
    @Binding var signatureImage: UIImage?
    let agreementText: String?

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            Text("Customer Signature")
                .font(.headline)

            Text("Ask the customer to sign below (optional)")
                .font(.subheadline)
                .foregroundStyle(.secondary)

            // Agreement text (shown when provided)
            if let agreementText, !agreementText.isEmpty {
                ScrollView {
                    Text(agreementText)
                        .font(.caption)
                        .foregroundStyle(.secondary)
                        .padding()
                }
                .frame(maxHeight: 120)
                .background(Color(.systemGray6))
                .clipShape(RoundedRectangle(cornerRadius: 8))
            }

            // Signature canvas
            SignatureView(signatureImage: $signatureImage)

            Spacer()
        }
        .padding()
    }
}

// MARK: - Preview

#Preview("Without Agreement") {
    SignatureStep(signatureImage: .constant(nil), agreementText: nil)
}

#Preview("With Agreement") {
    SignatureStep(
        signatureImage: .constant(nil),
        agreementText: "By signing below, you acknowledge receipt of the rental equipment in good condition and agree to return it in the same state. Any damage beyond normal wear and tear will be charged to your account."
    )
}
