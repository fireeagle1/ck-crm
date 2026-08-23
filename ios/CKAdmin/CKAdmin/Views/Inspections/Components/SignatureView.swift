import SwiftUI

// MARK: - SignatureView

/// A finger-drawing canvas for capturing customer signatures during checkout.
/// Renders strokes using Canvas/Path and exports the drawing to a UIImage
/// via UIGraphicsImageRenderer. Includes a clear button to reset.
///
/// Requirements: 19.1, 19.3
struct SignatureView: View {
    @Binding var signatureImage: UIImage?

    @State private var lines: [[CGPoint]] = []
    @State private var currentLine: [CGPoint] = []

    var body: some View {
        VStack(spacing: 12) {
            Text("Sign below")
                .font(.subheadline)
                .foregroundStyle(.secondary)

            Canvas { context, size in
                for line in lines {
                    drawLine(context: &context, points: line)
                }
                if !currentLine.isEmpty {
                    drawLine(context: &context, points: currentLine)
                }
            }
            .frame(height: 200)
            .background(Color.white)
            .clipShape(RoundedRectangle(cornerRadius: 12))
            .overlay(
                RoundedRectangle(cornerRadius: 12)
                    .stroke(Color(.systemGray4), lineWidth: 1)
            )
            .gesture(
                DragGesture(minimumDistance: 0)
                    .onChanged { value in
                        currentLine.append(value.location)
                    }
                    .onEnded { _ in
                        lines.append(currentLine)
                        currentLine = []
                        exportSignature()
                    }
            )

            HStack {
                Button("Clear") {
                    clear()
                }
                .font(.subheadline)
                .foregroundStyle(.red)

                Spacer()

                if signatureImage != nil {
                    Label("Captured", systemImage: "checkmark.circle.fill")
                        .font(.caption)
                        .foregroundStyle(.green)
                }
            }
        }
    }

    // MARK: - Public Methods

    /// Resets the drawing canvas and clears the exported signature image.
    func clear() {
        lines = []
        currentLine = []
        signatureImage = nil
    }

    // MARK: - Private Methods

    private func drawLine(context: inout GraphicsContext, points: [CGPoint]) {
        guard points.count > 1 else { return }
        var path = Path()
        path.move(to: points[0])
        for point in points.dropFirst() {
            path.addLine(to: point)
        }
        context.stroke(path, with: .color(.black), lineWidth: 2.5)
    }

    private func exportSignature() {
        let width: CGFloat = 400
        let height: CGFloat = 200
        let scale: CGFloat = UIScreen.main.scale

        let renderer = UIGraphicsImageRenderer(
            size: CGSize(width: width, height: height),
            format: {
                let format = UIGraphicsImageRendererFormat()
                format.scale = scale
                return format
            }()
        )

        let image = renderer.image { ctx in
            // White background
            UIColor.white.setFill()
            ctx.fill(CGRect(x: 0, y: 0, width: width, height: height))

            // Draw all lines
            let bezierPath = UIBezierPath()
            bezierPath.lineWidth = 2.5
            bezierPath.lineCapStyle = .round
            bezierPath.lineJoinStyle = .round

            for line in lines {
                guard line.count > 1 else { continue }
                bezierPath.move(to: line[0])
                for point in line.dropFirst() {
                    bezierPath.addLine(to: point)
                }
            }

            UIColor.black.setStroke()
            bezierPath.stroke()
        }

        signatureImage = image
    }
}

// MARK: - Preview

#Preview("Empty Signature") {
    SignatureView(signatureImage: .constant(nil))
        .padding()
}

#Preview("With Signature") {
    SignatureView(signatureImage: .constant(UIImage()))
        .padding()
}
