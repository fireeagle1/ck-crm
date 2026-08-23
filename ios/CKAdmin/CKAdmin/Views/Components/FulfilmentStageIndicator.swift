import SwiftUI

// MARK: - Stage Configuration

/// Defines the visual configuration for a single fulfilment stage.
struct StageConfig {
    let stage: String
    let label: String
    let color: Color
    let iconName: String

    /// All fulfilment stages in sequential order with their assigned colours and SF Symbols.
    static let allStages: [StageConfig] = [
        StageConfig(stage: "ordered", label: "Ordered", color: .gray, iconName: "clock"),
        StageConfig(stage: "packing", label: "Packing", color: .blue, iconName: "shippingbox"),
        StageConfig(stage: "ready", label: "Ready", color: .green, iconName: "checkmark.circle"),
        StageConfig(stage: "checked_out", label: "Checked Out", color: .teal, iconName: "person.fill.checkmark"),
        StageConfig(stage: "returned", label: "Returned", color: .orange, iconName: "arrow.uturn.backward"),
        StageConfig(stage: "inspected", label: "Inspected", color: .green, iconName: "checkmark.seal.fill"),
    ]

    /// Returns the index of the given stage in the sequence, or 0 if not found.
    static func indexOf(_ stage: String) -> Int {
        allStages.firstIndex(where: { $0.stage == stage }) ?? 0
    }
}

// MARK: - FulfilmentStageIndicator View

/// A horizontal progress indicator showing all fulfilment stages with visual state:
/// - Completed stages: checkmark icon with reduced opacity colour
/// - Active stage: full colour with assigned icon, slightly emphasised
/// - Future stages: grey/muted styling
struct FulfilmentStageIndicator: View {
    let currentStage: String

    private var currentIndex: Int {
        StageConfig.indexOf(currentStage)
    }

    var body: some View {
        HStack(spacing: 4) {
            ForEach(Array(StageConfig.allStages.enumerated()), id: \.element.stage) { index, config in
                stageItem(config: config, index: index)

                if index < StageConfig.allStages.count - 1 {
                    connector(completed: index < currentIndex)
                }
            }
        }
        .padding(.vertical, 8)
    }

    // MARK: - Stage Item

    @ViewBuilder
    private func stageItem(config: StageConfig, index: Int) -> some View {
        VStack(spacing: 4) {
            ZStack {
                Circle()
                    .fill(backgroundColor(for: index))
                    .frame(width: 32, height: 32)

                Image(systemName: iconName(config: config, index: index))
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundStyle(iconColor(for: index))
            }

            Text(config.label)
                .font(.system(size: 9, weight: index == currentIndex ? .semibold : .regular))
                .foregroundStyle(labelColor(for: index))
                .lineLimit(1)
                .minimumScaleFactor(0.8)
        }
        .frame(maxWidth: .infinity)
    }

    // MARK: - Connector

    private func connector(completed: Bool) -> some View {
        Rectangle()
            .fill(completed ? Color.green.opacity(0.6) : Color.gray.opacity(0.2))
            .frame(height: 2)
            .frame(maxWidth: 12)
            .offset(y: -8) // Align with circle centre
    }

    // MARK: - Styling Helpers

    private func backgroundColor(for index: Int) -> Color {
        let config = StageConfig.allStages[index]
        if index == currentIndex {
            return config.color.opacity(0.2)
        } else if index < currentIndex {
            return config.color.opacity(0.1)
        } else {
            return Color.gray.opacity(0.08)
        }
    }

    private func iconName(config: StageConfig, index: Int) -> String {
        if index < currentIndex {
            return "checkmark" // Completed stages show checkmark overlay
        }
        return config.iconName
    }

    private func iconColor(for index: Int) -> Color {
        let config = StageConfig.allStages[index]
        if index == currentIndex {
            return config.color // Full opacity for active stage
        } else if index < currentIndex {
            return config.color.opacity(0.7) // Reduced opacity for completed
        } else {
            return Color.gray.opacity(0.4) // Muted grey for future
        }
    }

    private func labelColor(for index: Int) -> Color {
        if index == currentIndex {
            return CKTheme.textPrimary
        } else if index < currentIndex {
            return CKTheme.textSecondary
        } else {
            return CKTheme.textTertiary
        }
    }
}

// MARK: - Preview

#Preview("Ordered") {
    FulfilmentStageIndicator(currentStage: "ordered")
        .padding()
}

#Preview("Packing") {
    FulfilmentStageIndicator(currentStage: "packing")
        .padding()
}

#Preview("Ready") {
    FulfilmentStageIndicator(currentStage: "ready")
        .padding()
}

#Preview("Checked Out") {
    FulfilmentStageIndicator(currentStage: "checked_out")
        .padding()
}

#Preview("Returned") {
    FulfilmentStageIndicator(currentStage: "returned")
        .padding()
}

#Preview("Inspected") {
    FulfilmentStageIndicator(currentStage: "inspected")
        .padding()
}
