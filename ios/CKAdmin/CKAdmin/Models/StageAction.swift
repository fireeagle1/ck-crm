import Foundation

// MARK: - Stage Action

/// Contextual actions available for a Booking based on its current Fulfilment_Stage.
/// Maps each stage to its valid set of quick-action buttons per Requirement 18.
enum StageAction: Identifiable {
    case advanceToPacking
    case markReady
    case checkOut
    case markReturned
    case returnInspection
    case completeInspection

    var id: String {
        switch self {
        case .advanceToPacking: return "advance_packing"
        case .markReady: return "mark_ready"
        case .checkOut: return "check_out"
        case .markReturned: return "mark_returned"
        case .returnInspection: return "return_inspection"
        case .completeInspection: return "complete_inspection"
        }
    }

    /// Display title for the action button.
    var title: String {
        switch self {
        case .advanceToPacking: return "Advance to Packing"
        case .markReady: return "Mark Ready"
        case .checkOut: return "Check Out"
        case .markReturned: return "Mark Returned"
        case .returnInspection: return "Return Inspection"
        case .completeInspection: return "Complete Inspection"
        }
    }

    /// SF Symbol icon name for the action button.
    var icon: String {
        switch self {
        case .advanceToPacking: return "shippingbox"
        case .markReady: return "checkmark.circle"
        case .checkOut: return "person.fill.checkmark"
        case .markReturned: return "arrow.uturn.backward"
        case .returnInspection: return "camera.fill"
        case .completeInspection: return "camera.fill"
        }
    }

    /// Whether this action is the primary (visually emphasised) action for its stage (Req 18.9).
    var isPrimary: Bool {
        switch self {
        case .advanceToPacking, .markReady, .checkOut, .returnInspection, .completeInspection:
            return true
        case .markReturned:
            return false
        }
    }

    /// Whether this action launches an inspection flow rather than a simple API call.
    var launchesInspection: Bool {
        switch self {
        case .checkOut, .returnInspection, .completeInspection:
            return true
        case .advanceToPacking, .markReady, .markReturned:
            return false
        }
    }

    /// Returns the valid actions for a given fulfilment_stage string.
    ///
    /// Mapping per Requirement 18:
    /// - ordered → [advanceToPacking] (Req 18.1)
    /// - packing → [markReady] (Req 18.2)
    /// - ready → [checkOut] (Req 18.3)
    /// - checked_out → [markReturned, returnInspection] (Req 18.4)
    /// - returned → [completeInspection] (Req 18.5)
    /// - inspected → [] (Req 18.6)
    static func actions(for stage: String) -> [StageAction] {
        switch stage {
        case "ordered": return [.advanceToPacking]
        case "packing": return [.markReady]
        case "ready": return [.checkOut]
        case "checked_out": return [.markReturned, .returnInspection]
        case "returned": return [.completeInspection]
        case "inspected": return []
        default: return []
        }
    }
}
