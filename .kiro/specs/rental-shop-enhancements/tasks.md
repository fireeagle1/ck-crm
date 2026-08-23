# Implementation Plan: Rental Shop Enhancements

## Overview

This plan implements four enhancement areas in sequential order: database schema and models first, then admin product question management, checkout question collection, admin quick actions and inspection PDF, portal order detail enhancements, and finally a mobile responsiveness pass. The stack is Laravel (PHP), Blade views with Alpine.js, Tailwind CSS, and DomPDF.

## Tasks

- [x] 1. Database migrations and new models
  - [x] 1.1 Create `product_questions` migration
    - Create migration file with columns: id, product_id (FK to products, cascadeOnDelete), label (string), input_type (string), options (json nullable), is_required (boolean default false), display_order (unsigned integer default 0), timestamps
    - Add composite index on [product_id, display_order]
    - _Requirements: 1.1, 1.2, 1.3, 1.5, 1.6_

  - [x] 1.2 Create `question_answers` migration
    - Create migration file with columns: id, order_item_id (FK to order_items, cascadeOnDelete), product_question_id (FK to product_questions, nullOnDelete), answer_value (text nullable), question_label (string), question_type (string), timestamps
    - Add indexes on order_item_id and product_question_id
    - _Requirements: 2.5, 1.7_

  - [x] 1.3 Create ProductQuestion model
    - Create `app/Models/ProductQuestion.php` with fillable, casts (options → array, is_required → boolean, display_order → integer), `product()` BelongsTo relationship, `answers()` HasMany relationship
    - _Requirements: 1.1, 1.2, 1.3_

  - [x] 1.4 Create QuestionAnswer model
    - Create `app/Models/QuestionAnswer.php` with fillable, `orderItem()` BelongsTo relationship, `productQuestion()` BelongsTo relationship
    - _Requirements: 2.5, 3.1_

  - [x] 1.5 Extend Product model with `questions()` relationship
    - Add `questions(): HasMany` relationship returning ProductQuestions ordered by display_order
    - _Requirements: 1.6, 2.1_

  - [x] 1.6 Extend OrderItem model with `questionAnswers()` relationship
    - Add `questionAnswers(): HasMany` relationship returning QuestionAnswers
    - _Requirements: 3.1, 11.1_

  - [x]* 1.7 Write property test for display order invariant
    - **Property 1: Product questions display order invariant**
    - **Validates: Requirements 1.6, 2.1**

  - [x]* 1.8 Write property test for answer preservation on question modification
    - **Property 2: Question answer preservation on question modification**
    - **Validates: Requirements 1.7**

- [x] 2. Checkpoint - Run migrations and verify models
  - Ensure migrations run without errors, models instantiate correctly, and relationships resolve. Ask the user if questions arise.

- [ ] 3. Admin product question management
  - [-] 3.1 Extend ShopProductController `edit()` to eager-load product questions
    - Add `$product->load('questions')` so questions are available to the edit view
    - _Requirements: 1.1_

  - [-] 3.2 Add `syncProductQuestions()` method to ShopProductController
    - Implement private method that accepts product and questions array data, deletes removed questions (answers preserved via nullOnDelete FK), upserts remaining questions with display_order set by array index
    - Call from the `update()` method when `$request->has('questions')`
    - _Requirements: 1.2, 1.3, 1.4, 1.5, 1.6, 1.7_

  - [~] 3.3 Add Alpine.js product questions UI section to product edit view
    - Add "Custom Questions" section in `admin/shop/products/edit.blade.php`
    - Alpine component managing an array of questions with: label input, input_type select dropdown (free_text, textarea, date, email, phone, select, number), options editor (shown only for select type), required toggle, reorder buttons (up/down), remove button
    - Hidden form inputs for submission to the controller
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6_

  - [ ]* 3.4 Write unit tests for syncProductQuestions logic
    - Test adding, editing, removing questions; verify display_order; verify answers preserved on deletion
    - _Requirements: 1.2, 1.3, 1.4, 1.5, 1.6, 1.7_

- [ ] 4. Checkout question collection
  - [~] 4.1 Extend checkout view to render product questions
    - Load product questions for each cart item in the checkout controller
    - Render each question using the appropriate HTML input element matching input_type (free_text → text, textarea → textarea, date → date, email → email, phone → tel, number → number, select → select with options)
    - Display required indicator, group questions under each product
    - _Requirements: 2.1, 2.2, 2.6_

  - [~] 4.2 Extend CheckoutService to validate and persist question answers
    - Add validation rules: required questions must have non-empty answers, optional questions accept nullable
    - After order item creation, call `storeQuestionAnswers()` to create QuestionAnswer records with snapshot fields (question_label, question_type)
    - Skip answer storage for products with no questions
    - _Requirements: 2.3, 2.4, 2.5, 2.6_

  - [ ]* 4.3 Write property test for checkout validation respects required flag
    - **Property 3: Checkout validation respects required flag**
    - **Validates: Requirements 2.3, 2.4**

  - [ ]* 4.4 Write property test for question answer round-trip persistence
    - **Property 4: Question answer round-trip persistence**
    - **Validates: Requirements 2.5**

  - [ ]* 4.5 Write property test for input type to HTML control mapping
    - **Property 5: Input type to HTML control mapping**
    - **Validates: Requirements 2.2**

- [~] 5. Checkpoint - Verify question flow end-to-end
  - Ensure admin can create questions, questions appear at checkout, answers persist correctly, and admin order detail shows answers. Ask the user if questions arise.

- [ ] 6. Admin order detail — answer display and fulfilment timeline
  - [~] 6.1 Extend ShopOrderController `show()` to eager-load question answers
    - Add `items.questionAnswers` to eager load chain
    - _Requirements: 3.1_

  - [~] 6.2 Add question answers display section to admin order detail view
    - In `admin/shop/orders/show.blade.php`, for each order item with questionAnswers, display a "Customer Responses" section with label/value pairs
    - Omit section when no answers exist
    - _Requirements: 3.1, 3.2, 3.3_

  - [~] 6.3 Create FulfilmentTimeline Blade component
    - Create `app/View/Components/FulfilmentTimeline.php` accepting currentStage, labels array, and layout (horizontal/responsive)
    - Create `resources/views/components/fulfilment-timeline.blade.php` rendering stages with completed/active/future styling, connector lines, and responsive horizontal/vertical layout
    - Define ADMIN_STAGE_LABELS and CUSTOMER_STAGE_LABELS constants
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 13.1, 13.2, 13.3, 13.4_

  - [~] 6.4 Add fulfilment timeline to admin order detail view
    - Render `<x-fulfilment-timeline>` for each rental booking on the admin order detail page using admin stage labels
    - _Requirements: 12.1, 12.2, 12.3, 12.4_

  - [ ]* 6.5 Write property test for fulfilment timeline stage styling
    - **Property 14: Fulfilment timeline stage styling correctness**
    - **Validates: Requirements 12.2, 13.2**

  - [ ]* 6.6 Write property test for question answers grouped under correct order item
    - **Property 6: Question answers grouped under correct order item**
    - **Validates: Requirements 3.1, 3.2, 11.1, 11.2**

- [ ] 7. Admin bookings list — quick action buttons
  - [~] 7.1 Add `resendConfirmation()` method to BookingController
    - Invoke NotificationService::notifyCustomerBookingConfirmed, handle success/error with flash messages, redirect back
    - _Requirements: 4.1, 4.2, 4.3, 4.4_

  - [~] 7.2 Add `advanceStage()` method to BookingController
    - Get next stage from FulfilmentStageService, invoke advance, handle success/error with flash messages, guard against final stage
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

  - [~] 7.3 Add `markReturnedFromList()` method to BookingController
    - Guard against non-checked_out bookings, invoke FulfilmentStageService::advance to "returned", set returned_at timestamp, handle success/error
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_

  - [~] 7.4 Register admin routes for quick actions
    - Add POST routes: `admin.bookings.resend-confirmation`, `admin.bookings.advance-stage`, `admin.bookings.mark-returned-list`
    - Add GET route: `admin.bookings.inspection-report`
    - _Requirements: 4.1, 5.1, 6.1, 7.1_

  - [~] 7.5 Add quick action buttons to bookings list view
    - Extend `admin/shop/bookings/index.blade.php` with: Resend Confirmation (always), Advance Stage (hidden at "inspected", shows next stage label), Mark Returned (only when "checked_out"), Download Inspection Report (only when inspections exist)
    - Extend index query with `withCount('inspections')` for conditional button display
    - _Requirements: 4.1, 5.1, 5.5, 6.1, 6.5, 7.1, 7.5_

  - [ ]* 7.6 Write property tests for button visibility conditions
    - **Property 7: Inspection report button visibility**
    - **Property 9: Advance Stage button visibility and label correctness**
    - **Property 10: Mark Returned button conditional visibility**
    - **Validates: Requirements 5.1, 5.5, 6.1, 6.5, 7.1, 7.5**

- [ ] 8. Inspection Report PDF
  - [~] 8.1 Create InspectionReportPdfService
    - Create `app/Services/InspectionReportPdfService.php` with `generate(Booking $booking)` method
    - Eager-load product, customer, inspections.inspector
    - Return DomPDF instance with A4 portrait paper
    - _Requirements: 5.2, 5.3, 5.4_

  - [~] 8.2 Create inspection report PDF Blade view
    - Create `resources/views/pdf/inspection-report.blade.php` with: company header, booking reference (BKG-{id}), product name, customer name, per-inspection sections with type badge, photos as base64 inline images, condition notes, inspector name, timestamp
    - _Requirements: 5.3_

  - [~] 8.3 Create BookingInspectionReportController
    - Create `app/Http/Controllers/Admin/BookingInspectionReportController.php` with `download()` method
    - Abort 404 if no inspections exist, stream PDF download without permanent storage
    - _Requirements: 5.2, 5.4, 5.5_

  - [ ]* 8.4 Write property test for inspection report PDF content completeness
    - **Property 8: Inspection report PDF content completeness**
    - **Validates: Requirements 5.3**

- [~] 9. Checkpoint - Verify admin features
  - Ensure quick actions work from bookings list, timeline renders correctly on admin order detail, and inspection report PDF downloads properly. Ask the user if questions arise.

- [ ] 10. Portal order detail enhancements
  - [~] 10.1 Extend Portal OrderController `show()` with eager loading
    - Add eager loads: `items.booking.checkoutInspection`, `items.booking.returnInspection`, `items.product`, `items.questionAnswers`
    - _Requirements: 8.1, 10.1, 10.2, 11.1_

  - [~] 10.2 Create portal booking details partial
    - Create `resources/views/portal/orders/partials/booking-details.blade.php` displaying start/end dates, customer-facing status label, and the fulfilment timeline component with customer labels and responsive layout
    - Include "Download Confirmation" button with 44px minimum touch target
    - _Requirements: 8.1, 8.2, 8.3, 9.1, 9.2, 13.1, 13.2, 13.3, 13.4_

  - [~] 10.3 Create portal inspections partial with photo gallery
    - Create `resources/views/portal/orders/partials/inspections.blade.php` and `inspection-card.blade.php`
    - Display checkout and return inspections with photos (thumbnail gallery with lightbox), condition notes, timestamp
    - Exclude inspector identity and damage flags from output
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6_

  - [~] 10.4 Create portal question answers partial
    - Create `resources/views/portal/orders/partials/question-answers.blade.php` displaying label/value pairs grouped under order item
    - Omit section when no answers exist
    - _Requirements: 11.1, 11.2, 11.3_

  - [~] 10.5 Integrate partials into portal order detail view
    - Update `resources/views/portal/orders/show.blade.php` to include booking-details, inspections, and question-answers partials for each order item
    - Conditionally show sections based on product type and data presence
    - _Requirements: 8.1, 8.4, 10.6, 11.3_

  - [ ]* 10.6 Write property test for portal rental booking detail display
    - **Property 11: Portal rental booking detail display**
    - **Validates: Requirements 8.1, 8.2, 8.3**

  - [ ]* 10.7 Write property test for portal inspection privacy filter
    - **Property 12: Portal inspection privacy filter**
    - **Validates: Requirements 10.5**

  - [ ]* 10.8 Write property test for portal inspection display completeness
    - **Property 13: Portal inspection display completeness**
    - **Validates: Requirements 10.1, 10.2, 10.4**

  - [ ]* 10.9 Write property test for customer-facing stage label mapping
    - **Property 15: Customer-facing stage label mapping**
    - **Validates: Requirements 8.3, 13.3**

- [ ] 11. Portal inspection photo access route
  - [~] 11.1 Create Portal InspectionPhotoController
    - Create `app/Http/Controllers/Portal/InspectionPhotoController.php` with `show()` method
    - Serve inspection photos to authenticated portal users, return 404 if file missing
    - Register route: `portal.inspection-photo`
    - _Requirements: 10.3_

- [ ] 12. Mobile responsiveness pass
  - [~] 12.1 Apply responsive Tailwind utilities to portal order detail and partials
    - Ensure `flex-col sm:flex-row` stacking on booking detail fields
    - Ensure inspection photo gallery uses scrollable single-row on mobile, multi-column grid on desktop
    - Ensure timeline switches between vertical (mobile) and horizontal (desktop)
    - Ensure all action buttons meet 44x44px minimum touch target (min-w-[44px] min-h-[44px])
    - Verify readability at 320px minimum viewport width
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5_

- [~] 13. Final checkpoint - Full integration verification
  - Ensure all tests pass, all views render correctly, PDF generation works, and responsive layouts are correct at mobile viewport widths. Ask the user if questions arise.

- [ ] 14. Backend: Extend inspect endpoint to accept signature data
  - [~] 14.1 Add signature_data validation rule to inspect endpoint
    - Add `signature_data` as a nullable string field with max length 500000 to the inspect endpoint validation rules
    - _Requirements: 19.4, 19.5, 19.8_

  - [~] 14.2 Store signature on booking after checkout inspection
    - After a successful checkout inspection, persist `signature_data` from the request onto the Booking model's existing `signature_data` field
    - Store null when no signature is provided
    - _Requirements: 19.5, 19.8_

  - [~] 14.3 Display signature on admin order detail
    - In the admin order detail view, render the stored `signature_data` as a base64 PNG `<img>` tag within the booking inspection section
    - Omit the signature section when `signature_data` is null
    - _Requirements: 19.6, 19.8_

- [ ] 15. iOS: MultipartFormData helper + APIClient extension
  - [~] 15.1 Create MultipartFormData struct
    - Create `ios/CKAdmin/CKAdmin/Networking/MultipartFormData.swift`
    - Implement struct with methods: `addField(name:value:)`, `addFile(name:fileName:mimeType:data:)`, `buildBody() -> Data`, `contentType` computed property with boundary
    - _Requirements: 16.6, 17.7_

  - [~] 15.2 Add uploadMultipart method to APIClient
    - Add `uploadMultipart<T: Decodable>(endpoint:method:body:contentType:) async throws -> T` method to the existing APIClient
    - Reuse existing Bearer token auth header logic from the `request()` method
    - _Requirements: 16.6, 17.7_

- [ ] 16. iOS: Shared inspection components
  - [~] 16.1 Create PhotoCaptureStep view
    - Create `ios/CKAdmin/CKAdmin/Views/Inspections/Components/PhotoCaptureStep.swift`
    - Implement SwiftUI view with camera capture (UIImagePickerController wrapper) and PhotosPicker for library selection
    - Display captured photos in a LazyVGrid, enforce maximum of 10 photos, allow deletion of individual photos
    - _Requirements: 16.3, 17.3_

  - [~] 16.2 Create ConditionNotesStep view
    - Create `ios/CKAdmin/CKAdmin/Views/Inspections/Components/ConditionNotesStep.swift`
    - Implement TextEditor with character limit (1000 chars) and remaining character count display
    - _Requirements: 16.4, 17.4_

  - [~] 16.3 Create StepProgressBar view
    - Create `ios/CKAdmin/CKAdmin/Views/Inspections/Components/StepProgressBar.swift`
    - Implement horizontal step indicator showing completed/active/future steps with labels
    - Accept step titles array and current step index as parameters
    - _Requirements: 16.2, 17.2_

  - [~] 16.4 Create DamageFlagStep view
    - Create `ios/CKAdmin/CKAdmin/Views/Inspections/Components/DamageFlagStep.swift`
    - Implement toggle switch with prominent warning label ("Flagged assets will be marked as In Repair") shown when toggle is on
    - _Requirements: 17.5, 17.6_

- [ ] 17. iOS: SignatureView + SignatureStep
  - [~] 17.1 Create SignatureView canvas
    - Create `ios/CKAdmin/CKAdmin/Views/Inspections/Components/SignatureView.swift`
    - Implement finger-drawing canvas using Canvas/Path with UIGraphicsImageRenderer export to UIImage
    - Include clear button to reset the drawing
    - _Requirements: 19.1, 19.3_

  - [~] 17.2 Create SignatureStep wrapper
    - Create `ios/CKAdmin/CKAdmin/Views/Inspections/Components/SignatureStep.swift`
    - Wrap SignatureView with optional agreement text display above the canvas
    - Expose binding for the captured signature image (optional UIImage)
    - _Requirements: 19.1, 19.2_

- [ ] 18. iOS: CheckoutInspectionView + ViewModel
  - [~] 18.1 Create CheckoutInspectionViewModel
    - Create `ios/CKAdmin/CKAdmin/ViewModels/CheckoutInspectionViewModel.swift`
    - Implement @Observable class managing multi-step state: photos array, condition notes, signature image, current step index, isSubmitting, error state
    - Implement `submit(orderId:bookingId:)` async method that builds MultipartFormData with photos, notes, and base64-encoded signature, calls inspect endpoint via APIClient.uploadMultipart
    - _Requirements: 16.6, 16.7, 16.8, 19.3, 19.4_

  - [~] 18.2 Create CheckoutInspectionView
    - Create `ios/CKAdmin/CKAdmin/Views/Inspections/CheckoutInspectionView.swift`
    - Implement multi-step flow: StepProgressBar at top, step content (PhotoCaptureStep → ConditionNotesStep → SignatureStep → ReviewStep), navigation buttons (Back/Next/Submit)
    - On successful submission, dismiss sheet and trigger booking detail refresh
    - _Requirements: 16.2, 16.3, 16.4, 16.5, 16.6, 16.7_

- [ ] 19. iOS: ReturnInspectionView + ViewModel
  - [~] 19.1 Create ReturnInspectionViewModel
    - Create `ios/CKAdmin/CKAdmin/ViewModels/ReturnInspectionViewModel.swift`
    - Implement @Observable class managing: photos array, condition notes, damage flag boolean, current step index, isSubmitting, error state
    - Implement `submit(orderId:bookingId:)` async method that builds MultipartFormData with photos, notes, and damage_flag, calls inspect endpoint via APIClient.uploadMultipart
    - _Requirements: 17.7, 17.8, 17.9_

  - [~] 19.2 Create ReturnInspectionView
    - Create `ios/CKAdmin/CKAdmin/Views/Inspections/ReturnInspectionView.swift`
    - Implement multi-step flow: StepProgressBar at top, step content (PhotoCaptureStep → ConditionNotesStep → DamageFlagStep → ReviewStep), navigation buttons (Back/Next/Submit)
    - On successful submission, dismiss sheet and trigger booking detail refresh
    - _Requirements: 17.2, 17.3, 17.4, 17.5, 17.6, 17.7, 17.8_

- [ ] 20. iOS: FulfilmentStageIndicator component
  - [~] 20.1 Create StageConfig and FulfilmentStageIndicator view
    - Create `ios/CKAdmin/CKAdmin/Views/Components/FulfilmentStageIndicator.swift`
    - Define StageConfig struct with: stage name, colour (Color), SF Symbol icon name
    - Configure stages: ordered (grey, clock), packing (blue, shippingbox), ready (green, checkmark.circle), checked_out (teal, person.fill.checkmark), returned (amber/orange, arrow.uturn.backward), inspected (green, checkmark.seal.fill)
    - Render horizontal row of stage icons with completed (reduced opacity or checkmark overlay), active (full colour), and future (grey) states
    - _Requirements: 20.1, 20.2, 20.3, 20.4, 20.5_

- [ ] 21. iOS: Stage action buttons on BookingDetailView
  - [~] 21.1 Create StageAction enum and action mapping
    - Create `ios/CKAdmin/CKAdmin/Models/StageAction.swift`
    - Define enum cases: advanceToPacking, markReady, checkOut, markReturned, returnInspection, completeInspection
    - Implement static method `actions(for stage: String) -> [StageAction]` mapping each fulfilment_stage to its valid actions per Requirement 18
    - _Requirements: 18.1, 18.2, 18.3, 18.4, 18.5, 18.6, 18.9_

  - [~] 21.2 Integrate FulfilmentStageIndicator into BookingDetailView
    - Add FulfilmentStageIndicator component to the existing BookingDetailView, displaying the current booking's fulfilment_stage
    - _Requirements: 20.1, 20.6_

  - [~] 21.3 Add stage action buttons to BookingDetailView
    - Render StageAction buttons below the FulfilmentStageIndicator based on current fulfilment_stage
    - Primary action uses prominent button styling, secondary actions use standard styling
    - _Requirements: 18.1, 18.2, 18.3, 18.4, 18.5, 18.6, 18.9_

  - [~] 21.4 Implement sheet presentation for inspection flows
    - Present CheckoutInspectionView as a sheet when "Check Out" is tapped (stage = ready)
    - Present ReturnInspectionView as a sheet when "Return Inspection" or "Complete Inspection" is tapped
    - On sheet dismiss with success, refresh booking detail data
    - _Requirements: 16.2, 16.7, 17.2, 17.8, 18.3, 18.4, 18.5_

  - [~] 21.5 Implement simple API call for advance actions
    - For non-inspection stage actions (advanceToPacking, markReady, markReturned), call the advanceStage endpoint via APIClient.request()
    - Show error alert on failure, refresh booking detail on success
    - _Requirements: 18.7, 18.8_

- [~] 22. Checkpoint - iOS inspection flows complete
  - Verify checkout and return inspection flows submit correctly to the inspect endpoint.
  - Verify stage action buttons appear/hide based on current fulfilment_stage.
  - Verify signature data persists through the round-trip (capture → submit → view on admin detail).
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- Unit tests validate specific examples and edge cases
- The FulfilmentTimeline component (task 6.3) is shared between admin and portal views
- Existing services (NotificationService, FulfilmentStageService, CheckoutService) are extended rather than replaced
- Photos in the inspection report PDF are embedded as base64 data URIs for portability
- iOS app source lives at `ios/CKAdmin/CKAdmin/`
- APIClient already exists with `request()` method using Bearer token auth — task 15.2 extends it with multipart support
- BookingDetailView already exists and shows booking info + assigned assets — tasks 21.2–21.5 extend it
- The inspect endpoint already handles both checkout and return inspections based on current stage
- The `signature_data` field already exists in the database schema — task 14 adds backend handling

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["1.3", "1.4"] },
    { "id": 2, "tasks": ["1.5", "1.6"] },
    { "id": 3, "tasks": ["1.7", "1.8"] },
    { "id": 4, "tasks": ["3.1", "3.2"] },
    { "id": 5, "tasks": ["3.3", "3.4", "6.3"] },
    { "id": 6, "tasks": ["4.1", "6.1", "7.1", "7.2", "7.3"] },
    { "id": 7, "tasks": ["4.2", "6.2", "6.4", "7.4"] },
    { "id": 8, "tasks": ["4.3", "4.4", "4.5", "6.5", "6.6", "7.5"] },
    { "id": 9, "tasks": ["7.6", "8.1"] },
    { "id": 10, "tasks": ["8.2", "8.3"] },
    { "id": 11, "tasks": ["8.4", "10.1"] },
    { "id": 12, "tasks": ["10.2", "10.3", "10.4"] },
    { "id": 13, "tasks": ["10.5"] },
    { "id": 14, "tasks": ["10.6", "10.7", "10.8", "10.9", "11.1"] },
    { "id": 15, "tasks": ["12.1", "14.1", "15.1", "20.1"] },
    { "id": 16, "tasks": ["14.2", "14.3", "15.2"] },
    { "id": 17, "tasks": ["16.1", "16.2", "16.3", "16.4"] },
    { "id": 18, "tasks": ["17.1", "17.2"] },
    { "id": 19, "tasks": ["18.1", "19.1"] },
    { "id": 20, "tasks": ["18.2", "19.2"] },
    { "id": 21, "tasks": ["21.1"] },
    { "id": 22, "tasks": ["21.2", "21.3"] },
    { "id": 23, "tasks": ["21.4", "21.5"] }
  ]
}
```
