import SwiftUI
import Observation

/// ViewModel managing the return inspection multi-step flow.
/// Steps: Photos → Notes → Damage Flag
@Observable
final class ReturnInspectionViewModel {

    // MARK: - State

    var photos: [UIImage] = []
    var conditionNotes: String = ""
    var isDamaged: Bool = false
    var currentStep: Int = 0
    var isSubmitting: Bool = false
    var error: String?

    // MARK: - Constants

    /// Total number of steps in the return inspection flow.
    static let stepCount = 3

    /// Step titles for display.
    static let stepTitles = ["Photos", "Condition Notes", "Damage Check"]

    // MARK: - Dependencies

    private let apiClient: APIClient

    // MARK: - Init

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    // MARK: - Computed Properties

    /// Whether the user can proceed from the current step.
    /// Step 0 (Photos): at least 1 photo required.
    /// Steps 1 and 2: always allowed.
    var canProceed: Bool {
        switch currentStep {
        case 0:
            return !photos.isEmpty
        default:
            return true
        }
    }

    /// Whether the inspection can be submitted (photos required and not already submitting).
    var canSubmit: Bool {
        !photos.isEmpty && !isSubmitting
    }

    // MARK: - Navigation

    func nextStep() {
        guard currentStep < Self.stepCount - 1 else { return }
        currentStep += 1
    }

    func previousStep() {
        guard currentStep > 0 else { return }
        currentStep -= 1
    }

    // MARK: - Submission

    /// Submit the return inspection to the backend.
    /// Builds MultipartFormData with photos (JPEG), condition notes, and damage flag,
    /// then calls the inspect endpoint via APIClient.uploadMultipart.
    @MainActor
    func submit(orderId: Int, bookingId: Int) async {
        isSubmitting = true
        error = nil

        do {
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

            // Add damage flag
            formData.addField(name: "damage_flagged", value: isDamaged ? "1" : "0")

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
}
