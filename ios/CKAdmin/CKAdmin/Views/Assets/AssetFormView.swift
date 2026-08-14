import SwiftUI

struct AssetFormView: View {
    @Environment(\.dismiss) private var dismiss

    enum Mode {
        case create
        case edit(AssetDetail)
        var title: String { switch self { case .create: return "New Asset"; case .edit: return "Edit Asset" } }
        var submitLabel: String { switch self { case .create: return "Create"; case .edit: return "Save" } }
    }

    @State private var deviceName = ""
    @State private var deviceType = ""
    @State private var location = ""
    @State private var assetStatus = "Active"
    @State private var serialNumber = ""
    @State private var notes = ""
    @State private var customerId = ""
    @State private var isSaving = false
    @State private var generalError: String?
    @State private var fieldErrors: [String: [String]] = [:]

    private let mode: Mode
    private let apiClient: APIClient
    private let onSave: ((AssetDetail) async -> Void)?
    private let statusOptions = ["Active", "Decommissioned", "In Repair"]

    init(mode: Mode, apiClient: APIClient, onSave: ((AssetDetail) async -> Void)? = nil) {
        self.mode = mode; self.apiClient = apiClient; self.onSave = onSave
        if case .edit(let a) = mode {
            _deviceName = State(initialValue: a.deviceName)
            _deviceType = State(initialValue: a.deviceType ?? "")
            _location = State(initialValue: a.location ?? "")
            _assetStatus = State(initialValue: a.assetStatus)
            _serialNumber = State(initialValue: a.serialNumber ?? "")
            _notes = State(initialValue: a.notes ?? "")
            _customerId = State(initialValue: a.customerId.map { String($0) } ?? "")
        }
    }

    var body: some View {
        NavigationStack {
            Form {
                if let generalError { Section { Label(generalError, systemImage: "exclamationmark.triangle").foregroundStyle(.red).font(.subheadline) } }
                Section("Device") {
                    field("Device Name", $deviceName, "device_name", required: true)
                    field("Device Type", $deviceType, "device_type")
                    field("Serial Number", $serialNumber, "serial_number")
                    field("Location", $location, "location")
                }
                Section("Status") {
                    Picker("Status *", selection: $assetStatus) { ForEach(statusOptions, id: \.self) { Text($0) } }
                }
                Section("Customer") {
                    field("Company ID", $customerId, "customer_id", required: true, keyboard: .numberPad)
                }
                Section("Notes") {
                    TextEditor(text: $notes).frame(minHeight: 80)
                }
            }
            .navigationTitle(mode.title)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Cancel") { dismiss() }.disabled(isSaving) }
                ToolbarItem(placement: .confirmationAction) {
                    Button(mode.submitLabel) { Task { await save() } }
                        .disabled(isSaving || deviceName.trimmingCharacters(in: .whitespaces).isEmpty || customerId.isEmpty)
                        .fontWeight(.semibold)
                }
            }
            .overlay { if isSaving { Color.black.opacity(0.1).ignoresSafeArea().overlay { ProgressView("Saving...").padding().background(.regularMaterial, in: RoundedRectangle(cornerRadius: 12)) } } }
        }
    }

    private func field(_ label: String, _ text: Binding<String>, _ key: String, required: Bool = false, keyboard: UIKeyboardType = .default) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            TextField(required ? "\(label) *" : label, text: text).keyboardType(keyboard).onChange(of: text.wrappedValue) { _, _ in fieldErrors[key] = nil }
            if let errors = fieldErrors[key], !errors.isEmpty { ForEach(errors, id: \.self) { Text($0).font(.caption).foregroundStyle(.red) } }
        }
    }

    @MainActor private func save() async {
        isSaving = true; generalError = nil; fieldErrors = [:]
        let payload = AssetPayload(customerId: Int(customerId) ?? 0, deviceName: deviceName.trimmed, deviceType: deviceType.emptyNil, location: location.emptyNil, assetStatus: assetStatus, serialNumber: serialNumber.emptyNil, notes: notes.emptyNil)
        do {
            let response: AssetDetailResponse
            switch mode {
            case .create: response = try await apiClient.request(Endpoint(method: .post, path: "/admin/assets", body: payload))
            case .edit(let a): response = try await apiClient.request(Endpoint(method: .put, path: "/admin/assets/\(a.deviceId)", body: payload))
            }
            await onSave?(response.data)
            dismiss()
        } catch let e as APIError {
            switch e { case .validationFailed(let errs): fieldErrors = errs; if fieldErrors.isEmpty { generalError = "Validation failed." }; default: generalError = e.errorDescription }
        } catch { generalError = "An unexpected error occurred." }
        isSaving = false
    }
}

struct AssetPayload: Encodable {
    let customerId: Int; let deviceName: String; let deviceType: String?; let location: String?; let assetStatus: String; let serialNumber: String?; let notes: String?
    enum CodingKeys: String, CodingKey { case customerId = "customer_id"; case deviceName = "device_name"; case deviceType = "device_type"; case location; case assetStatus = "asset_status"; case serialNumber = "serial_number"; case notes }
}

private extension String {
    var trimmed: String { trimmingCharacters(in: .whitespaces) }
    var emptyNil: String? { let t = trimmed; return t.isEmpty ? nil : t }
}
