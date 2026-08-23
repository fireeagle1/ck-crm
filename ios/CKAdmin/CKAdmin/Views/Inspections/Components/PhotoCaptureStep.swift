import SwiftUI
import PhotosUI

/// A step view for capturing inspection photos via camera or photo library selection.
/// Displays captured photos in a LazyVGrid with 3 columns, supports deletion,
/// and enforces a maximum of 10 photos.
struct PhotoCaptureStep: View {
    @Binding var photos: [UIImage]

    @State private var showCamera = false
    @State private var selectedItems: [PhotosPickerItem] = []

    private let maxPhotos = 10

    private let columns = [
        GridItem(.flexible()),
        GridItem(.flexible()),
        GridItem(.flexible()),
    ]

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            // Header
            Text("Capture Photos")
                .font(.headline)

            Text("Take at least one photo of the equipment condition.")
                .font(.subheadline)
                .foregroundStyle(.secondary)

            // Photo grid
            if !photos.isEmpty {
                LazyVGrid(columns: columns, spacing: 8) {
                    ForEach(photos.indices, id: \.self) { index in
                        photoCell(at: index)
                    }
                }
            }

            // Capture buttons
            HStack(spacing: 12) {
                Button {
                    showCamera = true
                } label: {
                    Label("Camera", systemImage: "camera.fill")
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(.bordered)
                .disabled(photos.count >= maxPhotos)

                PhotosPicker(
                    selection: $selectedItems,
                    maxSelectionCount: max(1, maxPhotos - photos.count),
                    matching: .images
                ) {
                    Label("Library", systemImage: "photo.on.rectangle")
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(.bordered)
                .disabled(photos.count >= maxPhotos)
            }

            // Photo count indicator
            Text("\(photos.count)/\(maxPhotos) photos")
                .font(.caption)
                .foregroundStyle(photos.count >= maxPhotos ? .red : .secondary)

            Spacer()
        }
        .padding()
        .fullScreenCover(isPresented: $showCamera) {
            CameraView { image in
                if let image, photos.count < maxPhotos {
                    photos.append(image)
                }
                showCamera = false
            }
            .ignoresSafeArea()
        }
        .onChange(of: selectedItems) { _, newItems in
            Task {
                for item in newItems {
                    guard photos.count < maxPhotos else { break }
                    if let data = try? await item.loadTransferable(type: Data.self),
                       let image = UIImage(data: data) {
                        photos.append(image)
                    }
                }
                selectedItems = []
            }
        }
    }

    // MARK: - Photo Cell

    @ViewBuilder
    private func photoCell(at index: Int) -> some View {
        ZStack(alignment: .topTrailing) {
            Image(uiImage: photos[index])
                .resizable()
                .scaledToFill()
                .frame(minWidth: 0, maxWidth: .infinity)
                .aspectRatio(1, contentMode: .fill)
                .clipped()
                .clipShape(RoundedRectangle(cornerRadius: 8))

            Button {
                photos.remove(at: index)
            } label: {
                Image(systemName: "xmark.circle.fill")
                    .font(.system(size: 20))
                    .foregroundStyle(.white, .red)
                    .shadow(radius: 2)
            }
            .offset(x: 4, y: -4)
        }
    }
}

// MARK: - Preview

#Preview("Empty") {
    PhotoCaptureStep(photos: .constant([]))
}

#Preview("With Photos") {
    PhotoCaptureStep(photos: .constant([
        UIImage(systemName: "photo")!,
        UIImage(systemName: "photo.fill")!,
        UIImage(systemName: "camera")!,
    ]))
}
