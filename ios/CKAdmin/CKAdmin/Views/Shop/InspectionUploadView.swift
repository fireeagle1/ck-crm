import SwiftUI
import PhotosUI

/// View for capturing inspection photos (checkout or return) and uploading them.
/// Supports both camera capture and photo library selection.
struct InspectionUploadView: View {
    @State private var selectedPhotos: [UIImage] = []
    @State private var conditionNotes = ""
    @State private var damageFlagged = false
    @State private var isUploading = false
    @State private var errorMessage: String?
    @State private var showCamera = false
    @State private var showPhotoPicker = false
    @State private var photoPickerItems: [PhotosPickerItem] = []

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
            Form {
                // Photos section
                Section("Photos (required)") {
                    // Photo grid
                    if !selectedPhotos.isEmpty {
                        LazyVGrid(columns: [GridItem(.adaptive(minimum: 80))], spacing: 8) {
                            ForEach(selectedPhotos.indices, id: \.self) { index in
                                ZStack(alignment: .topTrailing) {
                                    Image(uiImage: selectedPhotos[index])
                                        .resizable()
                                        .scaledToFill()
                                        .frame(width: 80, height: 80)
                                        .clipShape(RoundedRectangle(cornerRadius: 8))

                                    Button {
                                        selectedPhotos.remove(at: index)
                                    } label: {
                                        Image(systemName: "xmark.circle.fill")
                                            .font(.system(size: 18))
                                            .foregroundStyle(.white, .red)
                                    }
                                    .offset(x: 4, y: -4)
                                }
                            }
                        }
                        .padding(.vertical, 4)
                    }

                    // Add photo buttons
                    HStack(spacing: 12) {
                        Button {
                            showCamera = true
                        } label: {
                            Label("Camera", systemImage: "camera")
                                .font(.subheadline)
                                .frame(maxWidth: .infinity)
                        }
                        .buttonStyle(.bordered)
                        .disabled(selectedPhotos.count >= 10)

                        PhotosPicker(
                            selection: $photoPickerItems,
                            maxSelectionCount: 10 - selectedPhotos.count,
                            matching: .images
                        ) {
                            Label("Library", systemImage: "photo.on.rectangle")
                                .font(.subheadline)
                                .frame(maxWidth: .infinity)
                        }
                        .buttonStyle(.bordered)
                        .disabled(selectedPhotos.count >= 10)
                    }

                    if selectedPhotos.count >= 10 {
                        Text("Maximum 10 photos reached")
                            .font(.caption)
                            .foregroundStyle(.secondary)
                    } else {
                        Text("\(selectedPhotos.count)/10 photos selected")
                            .font(.caption)
                            .foregroundStyle(.secondary)
                    }
                }

                // Notes
                Section("Condition Notes") {
                    TextField("Describe the condition...", text: $conditionNotes, axis: .vertical)
                        .lineLimit(3...6)
                }

                // Damage flag
                Section {
                    Toggle(isOn: $damageFlagged) {
                        Label("Damage Detected", systemImage: "exclamationmark.triangle")
                    }
                    .tint(.red)
                } footer: {
                    Text("If flagged, assets will be marked as 'In Repair' upon completion.")
                }

                // Error
                if let errorMessage {
                    Section {
                        Text(errorMessage)
                            .font(.subheadline)
                            .foregroundStyle(.red)
                    }
                }
            }
            .navigationTitle("Inspection")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { onComplete() }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Submit") {
                        Task { await uploadInspection() }
                    }
                    .disabled(selectedPhotos.isEmpty || isUploading)
                    .fontWeight(.semibold)
                }
            }
            .overlay {
                if isUploading {
                    ZStack {
                        Color.black.opacity(0.3).ignoresSafeArea()
                        VStack(spacing: 12) {
                            ProgressView()
                                .controlSize(.large)
                            Text("Uploading...")
                                .font(.subheadline)
                                .foregroundStyle(.white)
                        }
                        .padding(24)
                        .background(.ultraThinMaterial, in: RoundedRectangle(cornerRadius: 12))
                    }
                }
            }
            .fullScreenCover(isPresented: $showCamera) {
                CameraView { image in
                    if let image, selectedPhotos.count < 10 {
                        selectedPhotos.append(image)
                    }
                }
            }
            .onChange(of: photoPickerItems) { _, newItems in
                Task {
                    for item in newItems {
                        if let data = try? await item.loadTransferable(type: Data.self),
                           let image = UIImage(data: data),
                           selectedPhotos.count < 10 {
                            selectedPhotos.append(image)
                        }
                    }
                    photoPickerItems = []
                }
            }
        }
    }

    // MARK: - Upload

    private func uploadInspection() async {
        isUploading = true
        errorMessage = nil

        do {
            let url = APIConfig.baseURL
                .appendingPathComponent("/api/admin/shop/orders/\(orderId)/bookings/\(bookingId)/inspect")

            var request = URLRequest(url: url)
            request.httpMethod = "POST"
            request.setValue("application/json", forHTTPHeaderField: "Accept")

            if let token = await getToken() {
                request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
            }

            let boundary = "Boundary-\(UUID().uuidString)"
            request.setValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")

            var body = Data()

            // Add photos
            for (index, image) in selectedPhotos.enumerated() {
                guard let imageData = image.jpegData(compressionQuality: 0.8) else { continue }
                body.append("--\(boundary)\r\n".data(using: .utf8)!)
                body.append("Content-Disposition: form-data; name=\"photos[\(index)]\"; filename=\"photo_\(index).jpg\"\r\n".data(using: .utf8)!)
                body.append("Content-Type: image/jpeg\r\n\r\n".data(using: .utf8)!)
                body.append(imageData)
                body.append("\r\n".data(using: .utf8)!)
            }

            // Add condition notes
            if !conditionNotes.isEmpty {
                body.append("--\(boundary)\r\n".data(using: .utf8)!)
                body.append("Content-Disposition: form-data; name=\"condition_notes\"\r\n\r\n".data(using: .utf8)!)
                body.append(conditionNotes.data(using: .utf8)!)
                body.append("\r\n".data(using: .utf8)!)
            }

            // Add damage flag
            if damageFlagged {
                body.append("--\(boundary)\r\n".data(using: .utf8)!)
                body.append("Content-Disposition: form-data; name=\"damage_flagged\"\r\n\r\n".data(using: .utf8)!)
                body.append("1".data(using: .utf8)!)
                body.append("\r\n".data(using: .utf8)!)
            }

            body.append("--\(boundary)--\r\n".data(using: .utf8)!)
            request.httpBody = body

            let (data, response) = try await URLSession.shared.data(for: request)

            guard let httpResponse = response as? HTTPURLResponse else {
                throw NSError(domain: "", code: -1, userInfo: [NSLocalizedDescriptionKey: "Invalid response"])
            }

            if httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                onComplete()
            } else if httpResponse.statusCode == 422 {
                if let json = try? JSONDecoder().decode(MessageResponse.self, from: data) {
                    errorMessage = json.message
                } else {
                    errorMessage = "Validation failed."
                }
            } else {
                errorMessage = "Upload failed (HTTP \(httpResponse.statusCode))."
            }
        } catch {
            errorMessage = "Upload failed: \(error.localizedDescription)"
        }

        isUploading = false
    }

    @MainActor
    private func getToken() -> String? {
        KeychainHelper.getToken()
    }
}

// MARK: - Camera View (UIKit wrapper)

struct CameraView: UIViewControllerRepresentable {
    let completion: (UIImage?) -> Void

    func makeUIViewController(context: Context) -> UIImagePickerController {
        let picker = UIImagePickerController()
        picker.sourceType = .camera
        picker.delegate = context.coordinator
        return picker
    }

    func updateUIViewController(_ uiViewController: UIImagePickerController, context: Context) {}

    func makeCoordinator() -> Coordinator {
        Coordinator(completion: completion)
    }

    class Coordinator: NSObject, UIImagePickerControllerDelegate, UINavigationControllerDelegate {
        let completion: (UIImage?) -> Void

        init(completion: @escaping (UIImage?) -> Void) {
            self.completion = completion
        }

        func imagePickerController(_ picker: UIImagePickerController, didFinishPickingMediaWithInfo info: [UIImagePickerController.InfoKey: Any]) {
            let image = info[.originalImage] as? UIImage
            picker.dismiss(animated: true)
            completion(image)
        }

        func imagePickerControllerDidCancel(_ picker: UIImagePickerController) {
            picker.dismiss(animated: true)
            completion(nil)
        }
    }
}

// MARK: - Keychain Helper (reads token from the same Keychain entry as AuthManager)

enum KeychainHelper {
    private static let keychainKey = "com.ckenterprises.admin.token"

    static func getToken() -> String? {
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrAccount as String: keychainKey,
            kSecReturnData as String: true,
            kSecMatchLimit as String: kSecMatchLimitOne,
        ]
        var result: AnyObject?
        let status = SecItemCopyMatching(query as CFDictionary, &result)
        guard status == errSecSuccess, let data = result as? Data else { return nil }
        return String(data: data, encoding: .utf8)
    }
}
