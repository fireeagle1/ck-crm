# Implementation Plan: Customer Projects

## Overview

This plan implements the Customer Projects feature for the CRM, adding project management capabilities with tasks, documents, comments, decisions, and approval workflows. The implementation follows a bottom-up approach: database migrations and models first, then service layer, form requests, controllers, routes, and finally Blade views.

## Tasks

- [x] 1. Database migrations and Eloquent models
  - [x] 1.1 Create migrations for all project-related tables
    - Create migration for `projects` table with columns: id, company_id (FK → customers.company_id), title, description, status (default "Not Started"), previous_status, timestamps
    - Create migration for `project_tasks` table with columns: id, project_id (FK cascade), title, description, status (default "To Do"), display_order (default 0), timestamps
    - Create migration for `project_documents` table with columns: id, project_id (FK cascade), label, document_type, file_path, original_filename, file_size, uploaded_by (FK → users.id), timestamps
    - Create migration for `project_comments` table with columns: id, project_id (FK cascade), user_id (FK), body, is_internal (default false), timestamps
    - Create migration for `project_decisions` table with columns: id, project_id (FK cascade), title, description, category (nullable), date_recorded, timestamps
    - Create migration for `project_approval_requests` table with columns: id, project_id (FK cascade), project_document_id (nullable FK, SET NULL), type, status (default "Pending"), responded_by (nullable FK → users.id), responded_at, rejection_reason, timestamps
    - Create migration for `project_status_logs` table with columns: id, project_id (FK cascade), status, changed_by (FK → users.id), created_at
    - _Requirements: 1.1, 1.2, 1.3, 2.2, 3.1, 4.1, 4.3, 5.1, 5.4, 7.1, 7.2, 7.3, 8.1, 9.1_

  - [x] 1.2 Create the Project Eloquent model
    - Create `app/Models/Project.php` with fillable fields, STATUSES constant, relationships (customer, tasks, documents, comments, decisions, approvalRequests, statusLogs), and `getProgressPercentageAttribute` accessor
    - Add `projects()` HasMany relationship to the existing `Customer` model
    - _Requirements: 1.1, 1.2, 1.3, 3.4_

  - [x] 1.3 Create remaining Eloquent models
    - Create `app/Models/ProjectTask.php` with fillable fields, STATUSES constant, and project relationship
    - Create `app/Models/ProjectDocument.php` with fillable fields, TYPES/ALLOWED_EXTENSIONS/MAX_SIZE_MB constants, and relationships (project, uploader, approvalRequest)
    - Create `app/Models/ProjectComment.php` with fillable fields, boolean cast for is_internal, and relationships (project, user)
    - Create `app/Models/ProjectDecision.php` with fillable fields, CATEGORIES constant, date cast, and project relationship
    - Create `app/Models/ProjectApprovalRequest.php` with fillable fields, TYPES/STATUSES constants, datetime cast, and relationships (project, document, respondedBy)
    - Create `app/Models/ProjectStatusLog.php` with $timestamps = false, fillable fields, datetime cast for created_at, and relationships (project, changedBy)
    - _Requirements: 3.1, 3.2, 4.1, 5.1, 7.1, 9.1, 2.2_

  - [x]* 1.4 Write property test for task progress calculation
    - **Property 4: Task progress calculation**
    - Generate random sets of ProjectTask records with random statuses; verify progress_percentage equals `floor(done_count / total_count * 100)` when total > 0, and 0 when total = 0
    - **Validates: Requirements 3.4, 3.6**

- [x] 2. Form Request validation classes
  - [x] 2.1 Create project and task Form Requests
    - Create `app/Http/Requests/StoreProjectRequest.php` validating: title (required, max:255), description (nullable, max:5000), company_id (required, exists:customers,company_id)
    - Create `app/Http/Requests/UpdateProjectRequest.php` validating: title (required, max:255), description (nullable, max:5000), status (in valid list)
    - Create `app/Http/Requests/StoreProjectTaskRequest.php` validating: title (required, max:255), description (nullable, max:2000), display_order (integer)
    - Create `app/Http/Requests/UpdateProjectTaskRequest.php` validating: title (max:255), description (nullable, max:2000), status (in list), display_order (integer)
    - _Requirements: 1.1, 1.5, 2.1, 3.1, 3.2, 3.5_

  - [x] 2.2 Create document, comment, decision, and approval Form Requests
    - Create `app/Http/Requests/StoreProjectDocumentRequest.php` validating: file (required, max:20480, mimes:pdf,docx,xlsx,png,jpg,zip), label (required, max:255), document_type (required, in list)
    - Create `app/Http/Requests/StoreProjectCommentRequest.php` validating: body (required, min:1, max:5000), is_internal (boolean)
    - Create `app/Http/Requests/StoreProjectDecisionRequest.php` validating: title (required, max:255), description (nullable, max:2000), category (nullable, in list)
    - Create `app/Http/Requests/RejectApprovalRequest.php` validating: reason (max:1000), conditionally required for completion type
    - _Requirements: 4.1, 4.4, 5.1, 5.6, 7.3, 8.4, 9.1, 9.4_

  - [x]* 2.3 Write property test for document upload file validation
    - **Property 6: Document upload file validation**
    - Generate random file extensions and sizes; verify acceptance only for allowed extensions AND size ≤ 20 MB
    - **Validates: Requirements 4.1, 4.4**

- [x] 3. ProjectNotificationService
  - [x] 3.1 Implement ProjectNotificationService
    - Create `app/Services/ProjectNotificationService.php`
    - Implement `notifyPublicComment()` — resolve customer users for the project's company, queue an email with project title and summary
    - Implement `notifyApprovalRequest()` — send email with portal link to approval
    - Implement `notifyCompletionRejection()` — send email to admin with rejection reason
    - Implement `getNotificationSummary()` — truncate text to max 200 characters
    - Wrap email dispatch in try/catch, log failures via `Log::error()`, return failure indicator for flash messages
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 8.4_

  - [x]* 3.2 Write property test for notification summary truncation
    - **Property 9: Notification summary truncation**
    - Generate random strings of varying lengths; verify output never exceeds 200 characters
    - **Validates: Requirements 6.3**

  - [x]* 3.3 Write unit tests for ProjectNotificationService
    - Test email dispatch using `Mail::fake()` for public comment notifications
    - Test email dispatch for approval request notifications
    - Test failure handling and logging when email fails
    - _Requirements: 6.1, 6.2, 6.4_

- [x] 4. Checkpoint - Ensure migrations, models, and service layer work
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Admin controllers
  - [x] 5.1 Implement Admin ProjectController
    - Create `app/Http/Controllers/Admin/ProjectController.php` with index, create, store, show, edit, update, destroy methods
    - `index` — list all projects with customer name, ordered by created_at desc
    - `store` — create project, log initial status
    - `update` — validate status transitions, block changes on Completed projects without reopen, log status changes
    - `reopen` — set Completed project back to In Progress, log status change
    - _Requirements: 1.1, 1.2, 1.4, 1.5, 2.1, 2.2, 2.3, 2.4_

  - [x] 5.2 Implement Admin ProjectTaskController
    - Create `app/Http/Controllers/Admin/ProjectTaskController.php` with index, create, store, edit, update, destroy methods
    - `store` — create task with default display_order
    - `update` — allow modification of title, description, status, display_order
    - `destroy` — delete task (progress auto-recalculates via model accessor)
    - `reorder` — accept array of task IDs and update display_order accordingly
    - _Requirements: 3.1, 3.2, 3.5, 3.6_

  - [x] 5.3 Implement Admin ProjectDocumentController
    - Create `app/Http/Controllers/Admin/ProjectDocumentController.php` with store and destroy methods
    - `store` — validate file, store via Storage facade under `project-documents/{project_id}/`, create document record with uploader
    - `destroy` — delete file from storage and remove record
    - _Requirements: 4.1, 4.3, 4.4, 4.5_

  - [x] 5.4 Implement Admin ProjectCommentController
    - Create `app/Http/Controllers/Admin/ProjectCommentController.php` with store method
    - `store` — create comment, if public then trigger `ProjectNotificationService::notifyPublicComment()`
    - _Requirements: 5.1, 5.4, 5.6, 6.1_

  - [x] 5.5 Implement Admin ProjectDecisionController
    - Create `app/Http/Controllers/Admin/ProjectDecisionController.php` with index, create, store, edit, update, destroy methods
    - `store` — create decision with date_recorded defaulting to today
    - _Requirements: 9.1, 9.2, 9.3, 9.4_

  - [x] 5.6 Implement Admin ProjectApprovalController
    - Create `app/Http/Controllers/Admin/ProjectApprovalController.php` with requestDocumentApproval and requestCompletionApproval methods
    - `requestDocumentApproval` — check no pending approval exists for the document, create Approval_Request, trigger notification
    - `requestCompletionApproval` — check no pending completion approval exists, store previous_status, set project to "Awaiting Approval", create Approval_Request, trigger notification
    - _Requirements: 7.1, 7.5, 7.6, 8.1, 8.2, 6.2_

  - [x]* 5.7 Write property test for completed status blocking transitions
    - **Property 3: Completed status blocks transitions**
    - For any project with status "Completed" and any target status, verify direct status change is rejected
    - **Validates: Requirements 2.3, 2.4**

  - [x]* 5.8 Write property test for no duplicate pending approval per document
    - **Property 11: No duplicate pending approval per document**
    - For any document with a Pending approval request, verify creating another is rejected
    - **Validates: Requirements 7.6**

- [x] 6. Portal controllers
  - [x] 6.1 Implement Portal ProjectController
    - Create `app/Http/Controllers/Portal/ProjectController.php` with index, show, and downloadDocument methods
    - `index` — list projects for the authenticated user's company where status != "Completed", ordered by updated_at desc
    - `show` — display project detail with tasks (ordered by display_order), public comments (oldest first), documents, decisions (newest first), pending approvals
    - `downloadDocument` — verify document belongs to user's company, return file download
    - Scope all queries by the user's company_id; return 404 for unauthorized access
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 4.2, 3.3, 5.3_

  - [x] 6.2 Implement Portal ProjectApprovalController
    - Create `app/Http/Controllers/Portal/ProjectApprovalController.php` with approve and reject methods
    - `approve` — verify approval is Pending and belongs to user's company, update status to Approved, record user and timestamp; if completion type, set project status to Completed
    - `reject` — verify approval is Pending, update status to Rejected, record reason; if completion type, revert project status from previous_status, notify admin via ProjectNotificationService
    - Block responses on non-Pending approval requests
    - _Requirements: 7.2, 7.3, 7.4, 7.5, 8.3, 8.4_

  - [x]* 6.3 Write property test for customer comment visibility filtering
    - **Property 7: Customer comment visibility filtering**
    - Generate random mixes of internal/public comments; verify customer query returns only public comments in oldest-first order
    - **Validates: Requirements 5.2, 5.3**

  - [x]* 6.4 Write property test for customer portal project filtering
    - **Property 15: Customer portal project filtering**
    - Generate random projects with various statuses and companies; verify portal returns only non-Completed projects for the user's company, ordered by updated_at desc
    - **Validates: Requirements 10.1**

  - [x]* 6.5 Write property test for customer company-scoped access control
    - **Property 16: Customer company-scoped access control**
    - For any customer user accessing a project from another company, verify 404 response
    - **Validates: Requirements 10.4, 10.5**

- [x] 7. Checkpoint - Ensure controllers and business logic work
  - Ensure all tests pass, ask the user if questions arise.

- [x] 8. Route registration
  - [x] 8.1 Register admin project routes
    - Add project resource routes and nested routes to the admin group in `routes/web.php`
    - Register: `Route::resource('projects', Admin\ProjectController::class)`, reopen route, and nested task/document/comment/decision/approval routes as specified in the design
    - _Requirements: 1.1, 1.4, 2.1, 3.1, 4.1, 5.1, 7.1, 8.1, 9.1_

  - [x] 8.2 Register portal project routes
    - Add portal project routes to the portal group in `routes/web.php`
    - Register: projects index, show, document download, approval approve/reject routes as specified in the design
    - _Requirements: 10.1, 10.2, 4.2, 7.2, 7.3, 8.3_

- [x] 9. Admin Blade views
  - [x] 9.1 Create admin project list and create/edit views
    - Create `resources/views/admin/projects/index.blade.php` — list projects with customer name, status, progress, and action links; ordered by created_at desc
    - Create `resources/views/admin/projects/create.blade.php` — form with title, description, and customer dropdown
    - Create `resources/views/admin/projects/edit.blade.php` — form with title, description, status dropdown, and reopen button for Completed projects
    - _Requirements: 1.1, 1.4, 1.5, 2.1, 2.3_

  - [x] 9.2 Create admin project show view
    - Create `resources/views/admin/projects/show.blade.php` — display project details with tabs/sections for tasks, documents, comments, decisions, and approvals
    - Show progress percentage, status badge, and status history
    - Include task list with status indicators and reorder capability
    - Include document list with upload form and approval status indicators
    - Include comments section with internal/public visual distinction and add comment form
    - Include decisions section with add decision form
    - Include approval request buttons (request document approval, request completion approval)
    - _Requirements: 1.4, 2.2, 3.3, 4.1, 4.3, 5.4, 5.5, 7.1, 7.4, 8.1, 9.2_

  - [x] 9.3 Create admin task create/edit views
    - Create `resources/views/admin/projects/tasks/create.blade.php` — form with title, description fields
    - Create `resources/views/admin/projects/tasks/edit.blade.php` — form with title, description, status dropdown, display_order
    - _Requirements: 3.1, 3.5_

  - [x] 9.4 Create admin decision create/edit views
    - Create `resources/views/admin/projects/decisions/create.blade.php` — form with title, description, category dropdown
    - Create `resources/views/admin/projects/decisions/edit.blade.php` — form with same fields pre-populated
    - _Requirements: 9.1, 9.3_

- [x] 10. Portal Blade views
  - [x] 10.1 Create portal project list view
    - Create `resources/views/portal/projects/index.blade.php` — list active projects with title, status, progress bar; show "no active projects" message when empty
    - _Requirements: 10.1, 10.3_

  - [x] 10.2 Create portal project show view
    - Create `resources/views/portal/projects/show.blade.php` — display project status, progress percentage, task list with statuses, public comments (oldest first), documents with download links, decisions, and pending approval requests with approve/reject buttons
    - Include rejection reason textarea for reject action (required for completion type)
    - _Requirements: 10.2, 3.3, 5.3, 4.2, 7.2, 7.3, 7.4, 8.3, 8.4, 9.2_

- [x] 11. Email templates
  - [x] 11.1 Create email notification Blade templates
    - Create `resources/views/emails/project-comment.blade.php` — public comment notification with project title, summary (max 200 chars), and portal link
    - Create `resources/views/emails/project-approval-request.blade.php` — approval request notification with project title, document/completion context, and direct portal link
    - Create `resources/views/emails/project-completion-rejected.blade.php` — rejection notification to admin with project title, rejection reason
    - _Requirements: 6.1, 6.2, 6.3, 8.4_

- [x] 12. Integration wiring and final verification
  - [x] 12.1 Add portal projects link to portal navigation
    - Update the portal layout/navigation Blade template to include a "Projects" link pointing to `route('portal.projects.index')`
    - _Requirements: 10.1_

  - [x] 12.2 Add admin projects link to admin navigation
    - Update the admin layout/navigation Blade template to include a "Projects" link pointing to `route('admin.projects.index')`
    - _Requirements: 1.4_

  - [x]* 12.3 Write integration tests for approval workflow
    - Test full lifecycle: create project → upload document → request document approval → customer approves → verify status
    - Test completion workflow: request completion → customer approves → project marked Completed
    - Test completion rejection: request completion → customer rejects → project status reverts to previous_status
    - Use `Mail::fake()` to verify notification emails are queued
    - _Requirements: 7.1, 7.2, 7.3, 8.1, 8.3, 8.4_

  - [x]* 12.4 Write integration tests for portal access control
    - Test customer can only see their own company's projects
    - Test customer receives 404 when accessing another company's project
    - Test customer can download documents from their own projects only
    - _Requirements: 10.4, 10.5, 4.2_

- [x] 13. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- Unit tests validate specific examples and edge cases
- The implementation uses PHP/Laravel following existing project patterns (Eloquent, Blade, Form Requests)
- File storage uses Laravel's Storage facade — configure disk in `.env` (local for dev, S3 for production)
- Email notifications use `Mail::queue()` to avoid blocking requests

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2", "1.3"] },
    { "id": 2, "tasks": ["1.4", "2.1", "2.2", "3.1"] },
    { "id": 3, "tasks": ["2.3", "3.2", "3.3"] },
    { "id": 4, "tasks": ["5.1", "5.2", "5.3", "5.4", "5.5", "5.6"] },
    { "id": 5, "tasks": ["5.7", "5.8", "6.1", "6.2"] },
    { "id": 6, "tasks": ["6.3", "6.4", "6.5", "8.1", "8.2"] },
    { "id": 7, "tasks": ["9.1", "9.3", "9.4", "10.1"] },
    { "id": 8, "tasks": ["9.2", "10.2", "11.1"] },
    { "id": 9, "tasks": ["12.1", "12.2"] },
    { "id": 10, "tasks": ["12.3", "12.4"] }
  ]
}
```
