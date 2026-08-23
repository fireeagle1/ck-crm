import SwiftUI

/// Multi-step return inspection view presented as a sheet.
/// Flow: Photos → Condition Notes → Damage Flag
///
/// On successful submission, dismisses the sheet and triggers a booking detail refresh.
///
/// Requirements: 17.2, 17.3, 17.4, 17.5, 17.6, 17.7, 17.8
struct ReturnInspectionView: View {
    let orderId: Int
    let bookingId: Int
    var onComplete: (() -> Void)?

    @Environment(\.dismiss) private var dismiss
    @State private var viewModel: ReturnInspectionViewModel

    init(orderId: Int, bookingId: Int, apiClient: APIClient, onComplete: (() -> Void)? = nil) {
        self.orderId = orderId
        self.bookingId = bookingId
        self.onComplete = onComplete
        self._viewModel = State(initialValue: ReturnInspectionViewModel(apiClient: apiClient))
    }

    // MARK: - Step Titles

    private let stepTitles = ["Photos", "Notes", "Damage"]

    // MARK: - Body

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                // Step progress bar
                StepProgressBar(steps: stepTitles, currentStep: viewModel.currentStep)
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
            .navigationTitle("Return Inspection")
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
                Text(viewModel.error ?? "An unknown error occurred.")
            }
        }
    }

    // MARK: - Step Content

    @ViewBuilder
    private var stepContent: some View {
        switch viewModel.currentStep {
        case 0:
            PhotoCaptureStep(photos: $viewModel.photos)
        case 1:
            ConditionNotesStep(notes: $viewModel.conditionNotes)
        case 2:
            DamageFlagStep(isDamaged: $viewModel.isDamaged)
        default:
            EmptyView()
        }
    }

    // MARK: - Navigation Buttons

    @ViewBuilder
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
            if viewModel.currentStep < ReturnInspectionViewModel.stepCount - 1 {
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
                .tint(.green)
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

#Preview("Return Inspection") {
    ReturnInspectionView(
        orderId: 1,
        bookingId: 1,
        apiClient: APIClient(authManager: AuthManager())
    )
}
