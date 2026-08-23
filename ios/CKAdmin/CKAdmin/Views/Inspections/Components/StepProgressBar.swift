import SwiftUI

struct StepProgressBar: View {
    let steps: [String]
    let currentStep: Int
    
    var body: some View {
        HStack(spacing: 0) {
            ForEach(Array(steps.enumerated()), id: \.offset) { index, title in
                VStack(spacing: 4) {
                    ZStack {
                        Circle()
                            .fill(circleColor(for: index))
                            .frame(width: 28, height: 28)
                        
                        if index < currentStep {
                            Image(systemName: "checkmark")
                                .font(.system(size: 12, weight: .bold))
                                .foregroundStyle(.white)
                        } else {
                            Text("\(index + 1)")
                                .font(.system(size: 12, weight: .semibold))
                                .foregroundStyle(index == currentStep ? .white : .secondary)
                        }
                    }
                    
                    Text(title)
                        .font(.system(size: 10, weight: index == currentStep ? .semibold : .regular))
                        .foregroundStyle(index <= currentStep ? .primary : .secondary)
                        .lineLimit(1)
                        .minimumScaleFactor(0.8)
                }
                .frame(maxWidth: .infinity)
                
                if index < steps.count - 1 {
                    Rectangle()
                        .fill(index < currentStep ? Color.blue : Color.gray.opacity(0.3))
                        .frame(height: 2)
                        .frame(maxWidth: 20)
                        .offset(y: -8)
                }
            }
        }
        .padding(.horizontal)
    }
    
    private func circleColor(for index: Int) -> Color {
        if index < currentStep {
            return .green
        } else if index == currentStep {
            return .blue
        } else {
            return Color(.systemGray4)
        }
    }
}
