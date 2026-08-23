import SwiftUI

/// Multi-step checkout inspection view (Handover Mode).
///
/// Presents a sheet with a StepProgressBar, step content
/// (PhotoCaptureStep → ConditionNotesStep → SignatureStep),
/// and navigation buttons (Back/Next/Submit).
/// On successful submission, dismisses the sheet and triggers
/// the provided completion callback so the parent can refresh.
///
/// Requirements: 16.2, 16.3, 16.4, 16.5, 16.6, 16.7
struct CheckoutInspectionView: View {
    let orderId: Int
    let bookingId: Int
    let agreementText: String?
    var onComplete: (() -> Void)?

    @State private var viewModel: CheckoutInspectionViewModel
    @Environment(\.dismiss) private var dismiss

    init(orderId: Int, bookingId: Int, agreementText: String? = nil, apiClient: APIClient, onComplete: (() -> Void)? = nil) {
        self.orderId = orderId
        self.bookingId = bookingId
        self.agreementText = agreementText
        self.onComplete = onComplete
        self._viewModel = State(initialValue: CheckoutInspectionViewModel(apiClient: apiClient))
    }

    // MARK: - Step Titles

    private let stepTitles = ["Photos", "Notes", "Signature"]

    // MARK: - Body

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                // Step progress indicator
                StepProgressBar(
                    steps: stepTitles,
                    currentStep: viewModel.currentStep
                )
                .padding(.vertical, 12)

                Divider()

                // Step content
                stepContent
                    .frame(maxWidth: .infinity, maxHeight: .infinity)

                Divider()

                // Navigation buttons
                navigationButtons
                    .padding()
            }
            .navigationTitle("Checkout Inspection")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") {
                        dismiss()
                    }
                }
            }
            .alert("Error", isPresented: showErrorBinding) {
                Button("OK") {
                    viewModel.error = nil
                }
            } message: {
                Text(viewModel.error ?? "")
            }
        }
    }

    // MARK: - Step Content

    @ViewBuilder
    private var stepContent: some View {
        switch CheckoutInspectionViewModel.Step(rawValue: viewModel.currentStep) {
        case .photos:
            PhotoCaptureStep(photos: $viewModel.photos)
        case .notes:
            ConditionNotesStep(notes: $viewModel.conditionNotes)
        case .signature:
            SignatureStep(
                signatureImage: $viewModel.signatureImage,
                agreementText: agreementText
            )
        case .none:
            EmptyView()
        }
    }

    // MARK: - Navigation Buttons

    private var navigationButtons: some View {
        HStack(spacing: 16) {
            // Back button (hidden on first step)
            if viewModel.currentStep > 0 {
                Button {
                    viewModel.previousStep()
                } label: {
                    HStack(spacing: 4) {
                        Image(systemName: "chevron.left")
                        Text("Back")
                    }
                    .frame(maxWidth: .infinity)
                }
                .buttonStyle(.bordered)
            }

            // Next or Submit button
            if viewModel.currentStep < CheckoutInspectionViewModel.totalSteps - 1 {
                Button {
                    viewModel.nextStep()
                } label: {
                    HStack(spacing: 4) {
                        Text("Next")
                        Image(systemName: "chevron.right")
                    }
                    .frame(maxWidth: .infinity)
                }
                .buttonStyle(.borderedProminent)
                .disabled(!viewModel.canProceed)
            } else {
                Button {
                    Task {
                        await viewModel.submit(orderId: orderId, bookingId: bookingId)
                        if viewModel.error == nil {
                            onComplete?()
                            dismiss()
                        }
                    }
                } label: {
                    if viewModel.isSubmitting {
                        ProgressView()
                            .frame(maxWidth: .infinity)
                    } else {
                        Text("Submit")
                            .frame(maxWidth: .infinity)
                    }
                }
                .buttonStyle(.borderedProminent)
                .tint(CKTheme.accent)
                .disabled(!viewModel.canSubmit)
            }
        }
    }

    // MARK: - Helpers

    private var showErrorBinding: Binding<Bool> {
        Binding(
            get: { viewModel.error != nil },
            set: { if !$0 { viewModel.error = nil } }
        )
    }
}

// MARK: - Preview

#Preview("Checkout Inspection") {
    CheckoutInspectionView(
        orderId: 1,
        bookingId: 1,
        agreementText: "By signing below, you acknowledge receipt of the rental equipment in good condition.",
        apiClient: APIClient(baseURL: URL(string: "https://example.com")!, tokenProvider: { nil })
    )
}
