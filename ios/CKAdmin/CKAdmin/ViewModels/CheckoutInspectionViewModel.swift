import Foundation
import UIKit
import Observation

/// Manages the multi-step checkout inspection (Handover Mode) flow.
///
/// Steps:
/// 1. Photos — capture equipment condition photos (at least 1 required)
/// 2. Notes — optional condition notes
/// 3. Signature — optional customer signature capture
@Observable
final class CheckoutInspectionViewModel {

    // MARK: - Step Definition

    enum Step: Int, CaseIterable {
        case photos = 0
        case notes = 1
        case signature = 2

        var title: String {
            switch self {
            case .photos: return "Photos"
            case .notes: return "Condition Notes"
            case .signature: return "Signature"
            }
        }
    }

    static let totalSteps = Step.allCases.count

    // MARK: - State

    var photos: [UIImage] = []
    var conditionNotes: String = ""
    var signatureImage: UIImage? = nil
    var currentStep: Int = 0

    private(set) var isSubmitting = false
    var error: String? = nil

    // MARK: - Private

    private let apiClient: APIClient

    // MARK: - Init

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    // MARK: - Computed Properties

    /// Whether the user can proceed from the current step.
    /// Step 0 (photos) requires at least one photo.
    var canProceed: Bool {
        switch Step(rawValue: currentStep) {
        case .photos:
            return !photos.isEmpty
        case .notes, .signature:
            return true
        case .none:
            return false
        }
    }

    /// Whether the inspection can be submitted (at least 1 photo, not already submitting).
    var canSubmit: Bool {
        !photos.isEmpty && !isSubmitting
    }

    // MARK: - Navigation

    func nextStep() {
        guard currentStep < Self.totalSteps - 1 else { return }
        currentStep += 1
    }

    func previousStep() {
        guard currentStep > 0 else { return }
        currentStep -= 1
    }

    // MARK: - Submission

    /// Submits the checkout inspection to the backend.
    ///
    /// Builds multipart form data with photos (JPEG), condition notes,
    /// and base64-encoded signature, then calls the inspect endpoint.
    ///
    /// - Parameters:
    ///   - orderId: The order ID for the booking.
    ///   - bookingId: The booking ID to inspect.
    @MainActor
    func submit(orderId: Int, bookingId: Int) async {
        guard canSubmit else { return }

        isSubmitting = true
        error = nil

        do {
            let formData = buildMultipartFormData()
            let _: MessageResponse = try await apiClient.uploadMultipart(
                path: "/admin/shop/orders/\(orderId)/bookings/\(bookingId)/inspect",
                formData: formData
            )
        } catch let apiError as APIError {
            error = apiError.errorDescription
        } catch {
            error = "An unexpected error occurred."
        }

        isSubmitting = false
    }

    // MARK: - Private Helpers

    private func buildMultipartFormData() -> MultipartFormData {
        var formData = MultipartFormData()

        // Add photos as JPEG data
        for (index, photo) in photos.enumerated() {
            guard let imageData = photo.jpegData(compressionQuality: 0.8) else { continue }
            formData.addFile(
                name: "photos[\(index)]",
                fileName: "photo_\(index).jpg",
                mimeType: "image/jpeg",
                data: imageData
            )
        }

        // Add condition notes if provided
        if !conditionNotes.isEmpty {
            formData.addField(name: "condition_notes", value: conditionNotes)
        }

        // Add base64-encoded signature if captured
        if let signature = signatureImage, let pngData = signature.pngData() {
            let base64String = pngData.base64EncodedString()
            formData.addField(name: "signature_data", value: base64String)
        }

        return formData
    }
}
