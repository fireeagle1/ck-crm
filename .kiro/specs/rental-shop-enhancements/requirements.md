# Requirements Document

## Introduction

This spec covers seven enhancement areas for the CK CRM rental shop platform: (1) configurable custom question sets that admins can attach to any product for data collection during checkout, (2) admin quick-action buttons on the bookings list for common operations without navigating away, (3) enhanced customer portal order detail pages showing rental-specific information and inspection records, (4) UI polish improvements including fulfilment stage timelines and mobile-friendly portal layouts, (5) formal documentation of the booking lifecycle state machine with dual-track status and fulfilment_stage transitions, (6) iOS CKAdmin app checkout and return inspection workflows as the primary handover tool, and (7) customer signature capture at checkout with rental agreement display.

## Glossary

- **Admin_Panel**: The administrative interface at `/admin` for administrators.
- **Portal**: The customer-facing interface at `/portal` for authenticated Customers.
- **Product**: A purchasable or rentable item in the catalog with product_type of `hosting`, `one_off`, or `equipment_rental`.
- **Product_Question**: A configurable question attached to a Product, defining a label, input type, required flag, display order, and optional selection choices.
- **Question_Answer**: A Customer-provided response to a Product_Question, stored against the OrderItem at checkout.
- **Booking**: A date-bounded reservation of an equipment_rental Product for a specific period, linked to an OrderItem.
- **Fulfilment_Stage**: The current lifecycle state of a rental booking: Ordered → Packing → Ready → Checked Out → Returned → Inspected.
- **Booking_Inspection**: A record capturing equipment condition at checkout or return, including photos and condition notes.
- **Inspection_Report_PDF**: A PDF document generated on-the-fly from a Booking_Inspection record containing photos, condition notes, timestamps, and inspector identity.
- **NotificationService**: The application service responsible for dispatching emails, including `notifyCustomerBookingConfirmed`.
- **FulfilmentStageService**: The application service that manages sequential stage transitions for bookings with validation and side effects.
- **Booking_Confirmation_PDF**: A DomPDF-generated document containing booking details, dates, product information, and customer information.
- **Order**: A record of a Customer's completed purchase, linking Customer to one or more OrderItems with payment and fulfilment status.
- **OrderItem**: A line item within an Order referencing a Product, price, billing frequency, and associated Service or Booking.
- **Booking_Status**: The high-level lifecycle state of a Booking: confirmed, active, returned, or cancelled.
- **CKAdmin_App**: The iOS administration application used by admins for on-site rental operations including checkout, return inspections, and stage management.
- **Checkout_Inspection**: A Booking_Inspection of type "checkout" captured during the equipment handover to the Customer, including photos, optional condition notes, and optional customer signature.
- **Return_Inspection**: A Booking_Inspection of type "return" captured when the Customer returns equipment, including photos, condition notes, and an optional damage flag.
- **Signature_Data**: A base64-encoded PNG image of the Customer's handwritten signature captured on the CKAdmin_App during checkout.
- **Handover_Mode**: The streamlined workflow in CKAdmin_App for checking out equipment to a Customer: select booking → capture inspection photos → capture optional signature → advance to checked_out.
- **Stage_Action_Button**: A contextual UI button displayed on the CKAdmin_App BookingDetailView that offers the next valid action for the Booking's current Fulfilment_Stage.
- **Stage_Indicator**: A visual component on the CKAdmin_App BookingDetailView that displays progress through all Fulfilment_Stages with colour-coded icons.

## Requirements

### Requirement 1: Product Question Configuration

**User Story:** As an admin, I want to define custom questions on any product, so that I can collect additional information from customers during checkout relevant to that product.

#### Acceptance Criteria

1. THE Admin_Panel SHALL provide a question management interface on the Product edit form for all product types (hosting, one_off, equipment_rental).
2. WHEN an admin adds a Product_Question, THE Admin_Panel SHALL require a label (display text) and an input type selection.
3. THE Admin_Panel SHALL support the following input types for Product_Questions: free text, textarea, date, email, phone, select, and number.
4. WHEN an admin selects the "select" input type, THE Admin_Panel SHALL display an options editor allowing the admin to define one or more selectable choices.
5. THE Admin_Panel SHALL allow admins to mark each Product_Question as required or optional.
6. THE Admin_Panel SHALL display Product_Questions in a defined display order and allow admins to reorder questions via drag-and-drop or positional controls.
7. THE Admin_Panel SHALL allow admins to add, edit, and remove Product_Questions at any time without affecting previously collected Question_Answers.

### Requirement 2: Question Answer Collection at Checkout

**User Story:** As a customer, I want to answer product-specific questions during checkout, so that the business receives the information they need for my order.

#### Acceptance Criteria

1. WHEN a Customer proceeds to checkout with a Product that has one or more Product_Questions configured, THE Portal SHALL display the questions in their defined display order within the checkout form.
2. THE Portal SHALL render each Product_Question using the appropriate HTML input control matching the configured input type.
3. WHEN a Product_Question is marked as required, THE Portal SHALL prevent checkout submission until the Customer provides a non-empty answer for that question.
4. WHEN a Product_Question is marked as optional, THE Portal SHALL allow checkout submission with that answer left blank.
5. WHEN checkout completes successfully, THE Portal SHALL store each Question_Answer linked to the corresponding OrderItem and Product_Question.
6. WHEN a Product has no configured Product_Questions, THE Portal SHALL proceed with checkout without displaying any custom question fields.

### Requirement 3: Question Answer Display in Admin

**User Story:** As an admin, I want to view customer-provided answers on the order detail page, so that I can reference the collected information when processing orders.

#### Acceptance Criteria

1. THE Admin_Panel order detail page SHALL display all Question_Answers for each OrderItem grouped under the relevant product line item.
2. THE Admin_Panel SHALL display each Question_Answer with the Product_Question label and the Customer-provided response value.
3. WHEN an OrderItem has no associated Question_Answers, THE Admin_Panel SHALL omit the answers section for that line item.

### Requirement 4: Admin Quick Action — Resend Confirmation Email

**User Story:** As an admin, I want to resend a booking confirmation email directly from the bookings list, so that I can quickly re-notify customers without navigating to the booking detail page.

#### Acceptance Criteria

1. THE Admin_Panel bookings list SHALL display a "Resend Confirmation" action button for each Booking row.
2. WHEN an admin clicks the "Resend Confirmation" button, THE Admin_Panel SHALL invoke NotificationService::notifyCustomerBookingConfirmed for the selected Booking.
3. WHEN the confirmation email is sent successfully, THE Admin_Panel SHALL display a success notification on the bookings list page.
4. IF the confirmation email dispatch fails, THEN THE Admin_Panel SHALL display an error notification with the failure reason.

### Requirement 5: Admin Quick Action — Download Inspection Report PDF

**User Story:** As an admin, I want to download an inspection report PDF directly from the bookings list, so that I can quickly access inspection records for printing or sharing.

#### Acceptance Criteria

1. WHILE a Booking has at least one Booking_Inspection record, THE Admin_Panel bookings list SHALL display a "Download Inspection Report" action button for that Booking row.
2. WHEN an admin clicks the "Download Inspection Report" button, THE Admin_Panel SHALL generate an Inspection_Report_PDF on-the-fly containing all inspection records (checkout and return) for that Booking.
3. THE Inspection_Report_PDF SHALL include: booking reference, product name, customer name, inspection type (checkout/return), photos rendered inline, condition notes, inspecting admin name, and inspection timestamp.
4. THE Admin_Panel SHALL stream the generated Inspection_Report_PDF directly to the browser as a download without storing the file permanently.
5. WHEN a Booking has no Booking_Inspection records, THE Admin_Panel bookings list SHALL hide the "Download Inspection Report" button for that Booking row.

### Requirement 6: Admin Quick Action — Advance Fulfilment Stage

**User Story:** As an admin, I want to advance a booking's fulfilment stage directly from the bookings list, so that I can progress bookings through the workflow without opening the detail page.

#### Acceptance Criteria

1. WHILE a Booking is not at the final Fulfilment_Stage ("inspected"), THE Admin_Panel bookings list SHALL display an "Advance Stage" action button showing the next target stage label.
2. WHEN an admin clicks the "Advance Stage" button, THE Admin_Panel SHALL invoke FulfilmentStageService::advance to progress the Booking to the next sequential stage.
3. WHEN the stage advance succeeds, THE Admin_Panel SHALL update the displayed Fulfilment_Stage for that Booking row and display a success notification.
4. IF the stage advance fails due to unmet pre-conditions, THEN THE Admin_Panel SHALL display an error notification describing the unmet pre-condition.
5. WHEN a Booking is at the final Fulfilment_Stage ("inspected"), THE Admin_Panel bookings list SHALL hide the "Advance Stage" button for that Booking row.

### Requirement 7: Admin Quick Action — Mark Returned

**User Story:** As an admin, I want to mark a booking as returned directly from the bookings list, so that I can record equipment returns efficiently during busy periods.

#### Acceptance Criteria

1. WHILE a Booking's Fulfilment_Stage is "checked_out", THE Admin_Panel bookings list SHALL display a "Mark Returned" action button for that Booking row.
2. WHEN an admin clicks the "Mark Returned" button, THE Admin_Panel SHALL invoke FulfilmentStageService::advance to transition the Booking to the "returned" stage and record the return timestamp.
3. WHEN the return is recorded successfully, THE Admin_Panel SHALL update the displayed Fulfilment_Stage and display a success notification.
4. IF the return action fails, THEN THE Admin_Panel SHALL display an error notification with the failure reason.
5. WHEN a Booking's Fulfilment_Stage is not "checked_out", THE Admin_Panel bookings list SHALL hide the "Mark Returned" button for that Booking row.

### Requirement 8: Customer Portal — Rental Booking Details

**User Story:** As a customer, I want to see rental-specific information on my order detail page, so that I can track the status and timeline of my equipment bookings.

#### Acceptance Criteria

1. WHEN a Customer views an Order detail page containing rental OrderItems, THE Portal SHALL display booking dates (start date and end date) for each rental item.
2. THE Portal SHALL display the current Booking status and Fulfilment_Stage as a human-friendly label (e.g., "Packing" displayed as "Being Prepared", "checked_out" as "With You").
3. THE Portal SHALL display a mapping of Fulfilment_Stage values to customer-facing labels: Ordered → "Order Placed", Packing → "Being Prepared", Ready → "Ready for Collection", Checked Out → "With You", Returned → "Returned", Inspected → "Complete".
4. WHEN a Booking has no rental-specific information (non-rental OrderItem), THE Portal order detail page SHALL omit the booking details section for that item.

### Requirement 9: Customer Portal — Booking Confirmation PDF Download

**User Story:** As a customer, I want to download my booking confirmation as a PDF, so that I have a portable record of my rental booking details.

#### Acceptance Criteria

1. WHEN a Customer views an Order detail page with a rental Booking, THE Portal SHALL display a "Download Confirmation" button for that Booking.
2. WHEN a Customer clicks the "Download Confirmation" button, THE Portal SHALL serve the Booking_Confirmation_PDF for download.
3. IF the Booking_Confirmation_PDF has not been previously generated, THEN THE Portal SHALL generate the PDF on-the-fly before serving the download.

### Requirement 10: Customer Portal — View Inspection Reports

**User Story:** As a customer, I want to view inspection reports for my rental bookings, so that I can see the documented condition of equipment at checkout and return.

#### Acceptance Criteria

1. WHILE a Booking has a checkout Booking_Inspection record, THE Portal order detail page SHALL display the checkout inspection with photos and condition notes.
2. WHILE a Booking has a return Booking_Inspection record, THE Portal order detail page SHALL display the return inspection with photos and condition notes.
3. THE Portal SHALL display inspection photos as a thumbnail gallery that the Customer can view at full size.
4. THE Portal SHALL display the inspection timestamp for each inspection record.
5. THE Portal SHALL NOT display the inspecting admin's identity or internal damage flags to the Customer.
6. WHEN a Booking has no Booking_Inspection records, THE Portal order detail page SHALL omit the inspection section for that Booking.

### Requirement 11: Customer Portal — View Custom Question Answers

**User Story:** As a customer, I want to see the answers I provided to custom questions on my order detail page, so that I can confirm what information I submitted.

#### Acceptance Criteria

1. WHEN a Customer views an Order detail page with OrderItems that have associated Question_Answers, THE Portal SHALL display the question labels and provided answers for each item.
2. THE Portal SHALL display Question_Answers grouped under the relevant product line item.
3. WHEN an OrderItem has no associated Question_Answers, THE Portal order detail page SHALL omit the answers section for that item.

### Requirement 12: Admin Order Detail — Fulfilment Stage Timeline

**User Story:** As an admin, I want to see a visual progress indicator for each booking's fulfilment stage, so that I can quickly assess where a booking is in its lifecycle.

#### Acceptance Criteria

1. THE Admin_Panel order detail page SHALL display a progress stepper or timeline component for each rental Booking showing all Fulfilment_Stages in sequence.
2. THE progress stepper SHALL visually distinguish completed stages, the current active stage, and remaining future stages using distinct styling (colour, icons, or markers).
3. THE progress stepper SHALL display the stage labels: Ordered, Packing, Ready, Checked Out, Returned, Inspected.
4. WHEN a Booking's Fulfilment_Stage changes, THE progress stepper SHALL reflect the updated state on the next page load.

### Requirement 13: Customer Portal — Booking Lifecycle Timeline

**User Story:** As a customer, I want to see a visual timeline of my booking's lifecycle, so that I can understand where my rental is in the process at a glance.

#### Acceptance Criteria

1. THE Portal order detail page SHALL display a visual booking timeline for each rental Booking showing the lifecycle stages with customer-facing labels.
2. THE booking timeline SHALL visually distinguish completed stages and the current active stage using distinct styling.
3. THE booking timeline SHALL use the customer-facing stage labels defined in Requirement 8 (Order Placed, Being Prepared, Ready for Collection, With You, Returned, Complete).
4. THE booking timeline SHALL be rendered as a horizontal stepper on desktop viewports and a vertical stepper on mobile viewports.

### Requirement 14: Mobile-Friendly Portal Booking Information

**User Story:** As a customer using a mobile device, I want the booking information on my order detail page to be well-formatted, so that I can comfortably review my rental details on smaller screens.

#### Acceptance Criteria

1. THE Portal order detail page SHALL use a responsive layout that adapts booking information sections to viewport widths below 768px.
2. WHILE the viewport width is below 768px, THE Portal SHALL stack booking detail fields (dates, status, stage) vertically rather than in multi-column layouts.
3. WHILE the viewport width is below 768px, THE Portal SHALL display inspection photo thumbnails in a scrollable single-row gallery or a stacked grid limited to two columns.
4. THE Portal booking timeline SHALL remain readable and interactive at all supported viewport widths (minimum 320px).
5. THE Portal SHALL ensure all action buttons (download confirmation, view inspection) remain accessible with adequate touch target size (minimum 44x44px) on mobile viewports.

### Requirement 15: Booking Lifecycle State Machine

**User Story:** As an admin, I want a clearly defined state machine governing booking lifecycle transitions, so that all systems (web admin panel, iOS app, scheduler) enforce consistent rules about when and how bookings progress through stages.

#### Acceptance Criteria

1. THE Booking SHALL maintain two parallel state tracks: Booking_Status (confirmed, active, returned, cancelled) and Fulfilment_Stage (ordered, packing, ready, checked_out, returned, inspected).
2. WHEN the Booking start_date arrives AND the associated Order is paid, THE scheduler SHALL transition the Booking_Status from "confirmed" to "active".
3. WHEN an admin marks a Booking as returned, THE FulfilmentStageService SHALL transition the Booking_Status from "active" to "returned".
4. WHEN an admin cancels a Booking OR payment expires, THE FulfilmentStageService SHALL transition the Booking_Status from "confirmed" or "active" to "cancelled".
5. WHEN assets are assigned to a Booking OR payment is confirmed for the associated Order, THE FulfilmentStageService SHALL transition the Fulfilment_Stage from "ordered" to "packing".
6. WHEN all required assets are assigned to a Booking AND the associated Order is paid, THE FulfilmentStageService SHALL transition the Fulfilment_Stage from "packing" to "ready".
7. WHEN a Checkout_Inspection is completed (photos captured and optionally signed), THE FulfilmentStageService SHALL transition the Fulfilment_Stage from "ready" to "checked_out".
8. WHEN an admin marks a Booking as returned, THE FulfilmentStageService SHALL transition the Fulfilment_Stage from "checked_out" to "returned".
9. WHEN a Return_Inspection is completed (photos and condition notes captured), THE FulfilmentStageService SHALL transition the Fulfilment_Stage from "returned" to "inspected".
10. THE Admin_Panel and CKAdmin_App SHALL derive the Booking display state from the combination of Booking_Status and Fulfilment_Stage.
11. IF a Fulfilment_Stage transition is requested that does not match the next sequential stage, THEN THE FulfilmentStageService SHALL reject the transition and return an error describing the required current stage.

### Requirement 16: iOS App Checkout Flow (Handover Mode)

**User Story:** As an admin using the CKAdmin app on-site, I want a streamlined checkout inspection flow on my phone, so that I can capture equipment condition and hand over rentals to customers efficiently.

#### Acceptance Criteria

1. WHILE a Booking's Fulfilment_Stage is "ready", THE CKAdmin_App BookingDetailView SHALL display a "Check Out" action button.
2. WHEN an admin taps the "Check Out" button, THE CKAdmin_App SHALL present the Handover_Mode flow as a multi-step sheet.
3. THE Handover_Mode flow SHALL present a photo capture step allowing the admin to take photos using the device camera or select from the photo library.
4. THE Handover_Mode flow SHALL present an optional condition notes text field for documenting equipment state at checkout.
5. THE Handover_Mode flow SHALL present an optional signature capture step using a finger-drawing canvas (SignatureView).
6. WHEN the admin submits the Handover_Mode flow, THE CKAdmin_App SHALL call the existing inspect endpoint (`/api/admin/shop/orders/{order}/bookings/{booking}/inspect`) with the captured photos, condition notes, and Signature_Data.
7. WHEN the inspect endpoint responds successfully, THE CKAdmin_App SHALL update the BookingDetailView to reflect the Fulfilment_Stage as "checked_out".
8. IF the inspect endpoint returns an error, THEN THE CKAdmin_App SHALL display an alert with the error message and retain the captured data for retry.
9. WHEN a Booking's Fulfilment_Stage is not "ready", THE CKAdmin_App SHALL hide the "Check Out" action button.

### Requirement 17: iOS App Return Inspection Flow

**User Story:** As an admin using the CKAdmin app, I want to perform return inspections on my phone, so that I can document equipment condition upon return and progress the booking to completion.

#### Acceptance Criteria

1. WHILE a Booking's Fulfilment_Stage is "checked_out", THE CKAdmin_App BookingDetailView SHALL display a "Return Inspection" action button.
2. WHEN an admin taps the "Return Inspection" button, THE CKAdmin_App SHALL present the return inspection flow as a multi-step sheet.
3. THE return inspection flow SHALL present a photo capture step allowing the admin to take photos using the device camera or select from the photo library.
4. THE return inspection flow SHALL present an optional condition notes text field for documenting equipment state at return.
5. THE return inspection flow SHALL present a damage flag toggle that defaults to off.
6. WHEN the damage flag is toggled on, THE CKAdmin_App SHALL display a prominent warning label stating that flagged assets will be marked as "In Repair".
7. WHEN the admin submits the return inspection flow, THE CKAdmin_App SHALL call the inspect endpoint with the captured photos, condition notes, and damage flag.
8. WHEN the inspect endpoint responds successfully, THE CKAdmin_App SHALL update the BookingDetailView to reflect the Fulfilment_Stage as "inspected".
9. IF the inspect endpoint returns an error, THEN THE CKAdmin_App SHALL display an alert with the error message and retain the captured data for retry.
10. WHEN a Booking's Fulfilment_Stage is not "checked_out", THE CKAdmin_App SHALL hide the "Return Inspection" action button.

### Requirement 18: iOS App Quick Stage Actions

**User Story:** As an admin using the CKAdmin app, I want contextual quick-action buttons on the booking detail view, so that I can advance bookings through stages without leaving the detail screen.

#### Acceptance Criteria

1. WHILE a Booking's Fulfilment_Stage is "ordered", THE CKAdmin_App BookingDetailView SHALL display an "Advance to Packing" Stage_Action_Button.
2. WHILE a Booking's Fulfilment_Stage is "packing", THE CKAdmin_App BookingDetailView SHALL display a "Mark Ready" Stage_Action_Button.
3. WHILE a Booking's Fulfilment_Stage is "ready", THE CKAdmin_App BookingDetailView SHALL display the "Check Out" button that launches the Handover_Mode flow defined in Requirement 16.
4. WHILE a Booking's Fulfilment_Stage is "checked_out", THE CKAdmin_App BookingDetailView SHALL display a "Mark Returned" Stage_Action_Button and a "Return Inspection" button that launches the flow defined in Requirement 17.
5. WHILE a Booking's Fulfilment_Stage is "returned", THE CKAdmin_App BookingDetailView SHALL display a "Complete Inspection" button that launches the Return_Inspection flow defined in Requirement 17.
6. WHILE a Booking's Fulfilment_Stage is "inspected", THE CKAdmin_App BookingDetailView SHALL display no Stage_Action_Buttons.
7. WHEN an admin taps a Stage_Action_Button (other than those launching inspection flows), THE CKAdmin_App SHALL call the advanceStage endpoint (`/api/admin/shop/orders/{order}/bookings/{booking}/advance-stage`) and update the display on success.
8. IF the advanceStage endpoint returns an error due to unmet pre-conditions, THEN THE CKAdmin_App SHALL display an alert describing the unmet condition.
9. THE CKAdmin_App SHALL visually emphasise only the primary action for the current Fulfilment_Stage using prominent button styling.

### Requirement 19: Customer Signature Capture at Checkout

**User Story:** As an admin, I want to capture a customer's signature on the iOS app during checkout, so that I have a signed record of the equipment handover for dispute resolution.

#### Acceptance Criteria

1. THE CKAdmin_App Handover_Mode flow SHALL include an optional signature capture step presenting a finger-drawing canvas (SignatureView).
2. WHEN a rental agreement exists for the Product associated with the Booking, THE CKAdmin_App SHALL display the agreement text above the SignatureView canvas.
3. THE CKAdmin_App SHALL encode the captured signature as a base64 PNG string (Signature_Data).
4. WHEN the admin submits the Handover_Mode flow with a captured signature, THE CKAdmin_App SHALL include the Signature_Data in the request payload to the inspect endpoint.
5. WHEN the inspect endpoint receives Signature_Data, THE backend SHALL store the Signature_Data on the Booking record in the existing `signature_data` database field.
6. THE Admin_Panel order detail page SHALL display the stored Signature_Data as a rendered PNG image within the booking inspection section.
7. THE Booking_Confirmation_PDF SHALL include the stored Signature_Data rendered as an image when a signature has been captured.
8. WHEN no signature is captured during checkout, THE backend SHALL store a null value in the `signature_data` field and THE Admin_Panel and PDF SHALL omit the signature section.

### Requirement 20: iOS App Fulfilment Stage Badge and Timeline

**User Story:** As an admin using the CKAdmin app, I want a visual stage indicator on each booking detail view, so that I can immediately understand where a booking is in its lifecycle.

#### Acceptance Criteria

1. THE CKAdmin_App BookingDetailView SHALL display a Stage_Indicator component showing all Fulfilment_Stages in sequence.
2. THE Stage_Indicator SHALL assign each stage a distinct colour and SF Symbol icon: ordered (grey, clock), packing (blue, shippingbox), ready (green, checkmark.circle), checked_out (teal, person.fill.checkmark), returned (amber, arrow.uturn.backward), inspected (green, checkmark.seal.fill).
3. THE Stage_Indicator SHALL visually highlight the current Fulfilment_Stage with the assigned colour and icon at full opacity.
4. THE Stage_Indicator SHALL display completed stages (stages prior to the current stage) with their assigned colour at reduced opacity or with a checkmark overlay.
5. THE Stage_Indicator SHALL display future stages (stages after the current stage) in a muted grey style.
6. WHEN a Booking's Fulfilment_Stage changes, THE Stage_Indicator SHALL update to reflect the new current stage without requiring a full page reload.
