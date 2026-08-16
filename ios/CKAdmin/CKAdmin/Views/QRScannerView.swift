import SwiftUI
import AVFoundation

/// Full-screen QR code scanner using AVFoundation.
/// Scans a QR code, calls the resolve API, and navigates to the entity.
struct QRScannerView: View {
    @Environment(\.dismiss) private var dismiss
    @State private var scannedCode: String?
    @State private var resolvedResult: ScanResult?
    @State private var isResolving = false
    @State private var errorMessage: String?
    @State private var navigateToEntity = false

    let apiClient: APIClient
    let onResolved: (ScanResult) -> Void

    var body: some View {
        ZStack {
            // Camera preview
            QRCameraPreview(onCodeScanned: handleScannedCode)
                .ignoresSafeArea()

            // Overlay UI
            VStack {
                // Top bar
                HStack {
                    Button { dismiss() } label: {
                        Image(systemName: "xmark.circle.fill")
                            .font(.title)
                            .foregroundStyle(.white)
                            .shadow(radius: 4)
                    }
                    .padding()

                    Spacer()
                }

                Spacer()

                // Scanning indicator / result
                VStack(spacing: 12) {
                    if isResolving {
                        ProgressView()
                            .controlSize(.large)
                            .tint(.white)
                        Text("Looking up code...")
                            .font(.subheadline)
                            .foregroundStyle(.white)
                    } else if let error = errorMessage {
                        Image(systemName: "exclamationmark.triangle.fill")
                            .font(.largeTitle)
                            .foregroundStyle(.orange)
                        Text(error)
                            .font(.subheadline)
                            .foregroundStyle(.white)
                            .multilineTextAlignment(.center)
                        Button("Scan Again") {
                            errorMessage = nil
                            scannedCode = nil
                        }
                        .buttonStyle(.bordered)
                        .tint(.white)
                    } else if let result = resolvedResult {
                        resultCard(result)
                    } else {
                        // Scanning state
                        Image(systemName: "qrcode.viewfinder")
                            .font(.system(size: 48))
                            .foregroundStyle(.white.opacity(0.8))
                        Text("Point at a QR code")
                            .font(.subheadline)
                            .foregroundStyle(.white.opacity(0.8))
                    }
                }
                .padding(24)
                .background(.ultraThinMaterial, in: RoundedRectangle(cornerRadius: 16))
                .padding(.horizontal, 32)
                .padding(.bottom, 60)
            }
        }
    }

    // MARK: - Result Card

    private func resultCard(_ result: ScanResult) -> some View {
        VStack(spacing: 8) {
            Image(systemName: iconForType(result.type))
                .font(.title)
                .foregroundStyle(.green)

            Text(result.summary.title)
                .font(.headline)
                .foregroundStyle(.primary)

            Text(result.summary.subtitle)
                .font(.subheadline)
                .foregroundStyle(.secondary)

            Text(result.summary.status.replacingOccurrences(of: "_", with: " ").capitalized)
                .font(.caption)
                .padding(.horizontal, 8)
                .padding(.vertical, 2)
                .background(Color.green.opacity(0.15))
                .clipShape(Capsule())

            Button("Open") {
                onResolved(result)
                dismiss()
            }
            .buttonStyle(.borderedProminent)
            .padding(.top, 4)
        }
    }

    // MARK: - Handle Scan

    private func handleScannedCode(_ code: String) {
        // Prevent duplicate processing
        guard scannedCode == nil, !isResolving else { return }
        scannedCode = code

        Task {
            await resolveCode(code)
        }
    }

    private func resolveCode(_ code: String) async {
        isResolving = true
        errorMessage = nil

        do {
            let endpoint = Endpoint(path: "/admin/scan/\(code)")
            let response: ScanResolveResponse = try await apiClient.request(endpoint)

            if response.resolved {
                resolvedResult = ScanResult(
                    type: response.type ?? "unknown",
                    id: response.id ?? 0,
                    orderId: response.orderId,
                    summary: response.summary ?? ScanSummary(title: "Unknown", subtitle: "", status: "")
                )
            } else {
                errorMessage = response.message ?? "Code not recognised."
            }
        } catch let error as APIError {
            errorMessage = error.errorDescription
        } catch {
            errorMessage = "Failed to look up code."
        }

        isResolving = false
    }

    private func iconForType(_ type: String) -> String {
        switch type {
        case "asset": return "desktopcomputer"
        case "order": return "bag"
        case "booking": return "calendar"
        default: return "questionmark.circle"
        }
    }
}

// MARK: - Data Models

struct ScanResolveResponse: Decodable {
    let resolved: Bool
    let type: String?
    let id: Int?
    let orderId: Int?
    let summary: ScanSummary?
    let message: String?
}

struct ScanSummary: Decodable {
    let title: String
    let subtitle: String
    let status: String
}

struct ScanResult {
    let type: String
    let id: Int
    let orderId: Int?
    let summary: ScanSummary
}

// MARK: - AVFoundation Camera Preview

struct QRCameraPreview: UIViewControllerRepresentable {
    let onCodeScanned: (String) -> Void

    func makeUIViewController(context: Context) -> QRCameraViewController {
        let vc = QRCameraViewController()
        vc.onCodeScanned = onCodeScanned
        return vc
    }

    func updateUIViewController(_ uiViewController: QRCameraViewController, context: Context) {}
}

class QRCameraViewController: UIViewController, AVCaptureMetadataOutputObjectsDelegate {
    var onCodeScanned: ((String) -> Void)?
    private var captureSession: AVCaptureSession?
    private var hasScanned = false

    override func viewDidLoad() {
        super.viewDidLoad()
        setupCamera()
    }

    private func setupCamera() {
        let session = AVCaptureSession()

        guard let device = AVCaptureDevice.default(for: .video),
              let input = try? AVCaptureDeviceInput(device: device) else {
            return
        }

        if session.canAddInput(input) {
            session.addInput(input)
        }

        let output = AVCaptureMetadataOutput()
        if session.canAddOutput(output) {
            session.addOutput(output)
            output.setMetadataObjectsDelegate(self, queue: .main)
            output.metadataObjectTypes = [.qr]
        }

        let previewLayer = AVCaptureVideoPreviewLayer(session: session)
        previewLayer.frame = view.bounds
        previewLayer.videoGravity = .resizeAspectFill
        view.layer.addSublayer(previewLayer)

        captureSession = session

        DispatchQueue.global(qos: .userInitiated).async {
            session.startRunning()
        }
    }

    func metadataOutput(_ output: AVCaptureMetadataOutput, didOutput metadataObjects: [AVMetadataObject], from connection: AVCaptureConnection) {
        guard !hasScanned,
              let object = metadataObjects.first as? AVMetadataMachineReadableCodeObject,
              let code = object.stringValue else { return }

        hasScanned = true

        // Haptic feedback
        let generator = UIImpactFeedbackGenerator(style: .medium)
        generator.impactOccurred()

        onCodeScanned?(code)
    }

    override func viewWillDisappear(_ animated: Bool) {
        super.viewWillDisappear(animated)
        captureSession?.stopRunning()
    }
}
