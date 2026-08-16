import SwiftUI
import AVFoundation

/// Packing verification screen: scan QR codes on assets to confirm they're being packed.
/// Each scanned CMDB-{id} code is validated against available assets for the product
/// and assigned to the booking via the API.
struct PackingScanView: View {
    @State private var scannedAssets: [ScannedAssetItem] = []
    @State private var isScanning = true
    @State private var lastError: String?
    @State private var isAssigning = false
    @State private var showCamera = true

    private let apiClient: APIClient
    private let orderId: Int
    private let bookingId: Int
    private let onComplete: () -> Void

    init(apiClient: APIClient, orderId: Int, bookingId: Int, onComplete: @escaping () -> Void) {
        self.apiClient = apiClient
        self.orderId = orderId
        self.bookingId = bookingId
        self.onComplete = onComplete
    }

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                // Scanner area
                if showCamera {
                    ZStack {
                        PackingCameraPreview(onCodeScanned: handleScan)
                            .frame(height: 250)
                            .clipShape(RoundedRectangle(cornerRadius: 12))
                            .padding(.horizontal)

                        VStack {
                            Spacer()
                            Text("Scan asset QR codes")
                                .font(.caption)
                                .foregroundStyle(.white)
                                .padding(6)
                                .background(.black.opacity(0.5), in: Capsule())
                                .padding(.bottom, 8)
                        }
                    }
                    .padding(.top)
                }

                // Error banner
                if let error = lastError {
                    HStack {
                        Image(systemName: "exclamationmark.triangle.fill")
                            .foregroundStyle(.orange)
                        Text(error)
                            .font(.caption)
                    }
                    .padding(8)
                    .background(Color.orange.opacity(0.1), in: RoundedRectangle(cornerRadius: 8))
                    .padding(.horizontal)
                    .padding(.top, 8)
                }

                // Scanned items list
                List {
                    Section("Scanned Items (\(scannedAssets.count))") {
                        if scannedAssets.isEmpty {
                            Text("No items scanned yet. Point camera at asset QR codes.")
                                .font(.subheadline)
                                .foregroundStyle(.secondary)
                        } else {
                            ForEach(scannedAssets) { item in
                                HStack {
                                    Image(systemName: "checkmark.circle.fill")
                                        .foregroundStyle(.green)
                                    VStack(alignment: .leading) {
                                        Text(item.deviceName)
                                            .font(.subheadline)
                                            .fontWeight(.medium)
                                        Text("CMDB-\(item.assetId)")
                                            .font(.caption)
                                            .foregroundStyle(.secondary)
                                    }
                                    Spacer()
                                    if let serial = item.serialNumber {
                                        Text(serial)
                                            .font(.caption2)
                                            .foregroundStyle(.secondary)
                                    }
                                }
                            }
                        }
                    }
                }
                .listStyle(.insetGrouped)
            }
            .navigationTitle("Pack & Confirm")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { onComplete() }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Confirm & Assign") {
                        Task { await assignScannedAssets() }
                    }
                    .disabled(scannedAssets.isEmpty || isAssigning)
                    .fontWeight(.semibold)
                }
            }
            .overlay {
                if isAssigning {
                    ZStack {
                        Color.black.opacity(0.3).ignoresSafeArea()
                        VStack(spacing: 12) {
                            ProgressView().controlSize(.large)
                            Text("Assigning assets...").font(.subheadline).foregroundStyle(.white)
                        }
                        .padding(24)
                        .background(.ultraThinMaterial, in: RoundedRectangle(cornerRadius: 12))
                    }
                }
            }
        }
    }

    // MARK: - Handle Scan

    private func handleScan(_ code: String) {
        lastError = nil

        // Parse CMDB-{id} format
        guard let match = code.range(of: #"^CMDB-(\d+)$"#, options: .regularExpression) else {
            lastError = "Not an asset code: \(code)"
            return
        }

        let idString = code.replacingOccurrences(of: "CMDB-", with: "")
        guard let assetId = Int(idString) else {
            lastError = "Invalid asset ID"
            return
        }

        // Check not already scanned
        if scannedAssets.contains(where: { $0.assetId == assetId }) {
            lastError = "CMDB-\(assetId) already scanned"
            return
        }

        // Look up the asset via the scan API
        Task {
            do {
                let endpoint = Endpoint(path: "/admin/scan/CMDB-\(assetId)")
                let response: ScanResolveResponse = try await apiClient.request(endpoint)

                if response.resolved, response.type == "asset" {
                    let item = ScannedAssetItem(
                        assetId: assetId,
                        deviceName: response.summary?.title ?? "Unknown",
                        serialNumber: response.summary?.subtitle
                    )
                    scannedAssets.append(item)

                    // Haptic
                    let generator = UINotificationFeedbackGenerator()
                    generator.notificationOccurred(.success)
                } else {
                    lastError = response.message ?? "Asset not found"
                }
            } catch {
                lastError = "Lookup failed for CMDB-\(assetId)"
            }
        }
    }

    // MARK: - Assign

    private func assignScannedAssets() async {
        isAssigning = true
        lastError = nil

        let assetIds = scannedAssets.map { $0.assetId }

        do {
            struct AssignBody: Encodable {
                let asset_ids: [Int]
            }
            let endpoint = Endpoint(
                method: .post,
                path: "/admin/shop/orders/\(orderId)/bookings/\(bookingId)/assign-assets",
                body: AssignBody(asset_ids: assetIds)
            )
            let _: MessageResponse = try await apiClient.request(endpoint)
            onComplete()
        } catch let error as APIError {
            lastError = error.errorDescription
        } catch {
            lastError = "Failed to assign assets."
        }

        isAssigning = false
    }
}

// MARK: - Scanned Asset Item

struct ScannedAssetItem: Identifiable {
    let id = UUID()
    let assetId: Int
    let deviceName: String
    let serialNumber: String?
}

// MARK: - Packing Camera Preview (reuses same AVFoundation pattern as QR scanner)

struct PackingCameraPreview: UIViewControllerRepresentable {
    let onCodeScanned: (String) -> Void

    func makeUIViewController(context: Context) -> PackingCameraVC {
        let vc = PackingCameraVC()
        vc.onCodeScanned = onCodeScanned
        return vc
    }

    func updateUIViewController(_ uiViewController: PackingCameraVC, context: Context) {}
}

class PackingCameraVC: UIViewController, AVCaptureMetadataOutputObjectsDelegate {
    var onCodeScanned: ((String) -> Void)?
    private var captureSession: AVCaptureSession?
    private var previewLayer: AVCaptureVideoPreviewLayer?
    private var lastScannedCode: String?
    private var cooldownTimer: Timer?

    override func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = .black

        switch AVCaptureDevice.authorizationStatus(for: .video) {
        case .authorized:
            setupCamera()
        case .notDetermined:
            AVCaptureDevice.requestAccess(for: .video) { [weak self] granted in
                if granted { DispatchQueue.main.async { self?.setupCamera() } }
            }
        default:
            break
        }
    }

    override func viewDidLayoutSubviews() {
        super.viewDidLayoutSubviews()
        previewLayer?.frame = view.bounds
    }

    private func setupCamera() {
        let session = AVCaptureSession()
        session.sessionPreset = .high

        guard let device = AVCaptureDevice.default(.builtInWideAngleCamera, for: .video, position: .back),
              let input = try? AVCaptureDeviceInput(device: device) else { return }

        if session.canAddInput(input) { session.addInput(input) }

        let output = AVCaptureMetadataOutput()
        if session.canAddOutput(output) {
            session.addOutput(output)
            output.setMetadataObjectsDelegate(self, queue: .main)
            output.metadataObjectTypes = [.qr]
        }

        let layer = AVCaptureVideoPreviewLayer(session: session)
        layer.frame = view.bounds
        layer.videoGravity = .resizeAspectFill
        view.layer.addSublayer(layer)
        previewLayer = layer
        captureSession = session

        DispatchQueue.global(qos: .userInitiated).async { session.startRunning() }
    }

    func metadataOutput(_ output: AVCaptureMetadataOutput, didOutput metadataObjects: [AVMetadataObject], from connection: AVCaptureConnection) {
        guard let object = metadataObjects.first as? AVMetadataMachineReadableCodeObject,
              let code = object.stringValue else { return }

        // Cooldown: don't re-scan the same code within 2 seconds
        guard code != lastScannedCode else { return }
        lastScannedCode = code

        cooldownTimer?.invalidate()
        cooldownTimer = Timer.scheduledTimer(withTimeInterval: 2.0, repeats: false) { [weak self] _ in
            self?.lastScannedCode = nil
        }

        let generator = UIImpactFeedbackGenerator(style: .light)
        generator.impactOccurred()

        onCodeScanned?(code)
    }

    override func viewWillDisappear(_ animated: Bool) {
        super.viewWillDisappear(animated)
        captureSession?.stopRunning()
    }
}
