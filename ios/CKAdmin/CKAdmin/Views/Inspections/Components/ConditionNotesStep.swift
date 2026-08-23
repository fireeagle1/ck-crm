import SwiftUI

struct ConditionNotesStep: View {
    @Binding var notes: String
    let maxCharacters: Int = 1000
    
    var remainingCharacters: Int {
        maxCharacters - notes.count
    }
    
    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            Text("Condition Notes")
                .font(.headline)
            
            Text("Describe the condition of the equipment (optional)")
                .font(.subheadline)
                .foregroundStyle(.secondary)
            
            TextEditor(text: $notes)
                .frame(minHeight: 150)
                .padding(8)
                .background(Color(.systemGray6))
                .clipShape(RoundedRectangle(cornerRadius: 8))
                .onChange(of: notes) { _, newValue in
                    if newValue.count > maxCharacters {
                        notes = String(newValue.prefix(maxCharacters))
                    }
                }
            
            HStack {
                Spacer()
                Text("\(remainingCharacters) characters remaining")
                    .font(.caption)
                    .foregroundStyle(remainingCharacters < 100 ? .red : .secondary)
            }
        }
        .padding()
    }
}
