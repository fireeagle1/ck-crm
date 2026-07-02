# Requirements Document

## Introduction

The Customer Projects feature provides a simple project management capability within the CRM, primarily for tracking website builds and similar deliverables for customers. Admins use it as a project tracker with tasks, comments, documents, and approvals. Customers see progress updates, public comments, shared documents, and can provide approvals when requested. Each project belongs to exactly one customer.

## Glossary

- **Project**: A body of work delivered to a single customer, tracked from creation through to completion
- **Project_Task**: A discrete unit of work within a Project that contributes to overall progress
- **Project_Document**: A file attached to a Project (agreement, contract, quote, or design asset)
- **Project_Comment**: A note on a Project, either internal (admin-only) or public (visible to the customer)
- **Project_Decision**: A recorded decision or design requirement captured during a Project
- **Approval_Request**: A request sent to the customer to approve a document or mark a project complete
- **Admin_User**: A User with is_admin set to true who manages projects
- **Customer_User**: A User with is_admin set to false who views their own company projects via the portal
- **CRM_System**: The ck-crm Laravel application
- **Notification_Service**: The component responsible for sending email communications to customers

## Requirements

### Requirement 1: Project Creation and Ownership

**User Story:** As an admin, I want to create a project assigned to a specific customer, so that I can track deliverables for that customer.

#### Acceptance Criteria

1. THE CRM_System SHALL allow Admin_User to create a Project with a required title (maximum 255 characters), an optional description (maximum 5000 characters), and a status that defaults to "Not Started"
2. WHEN a Project is created, THE CRM_System SHALL associate the Project with exactly one existing Customer selected by the Admin_User
3. THE CRM_System SHALL enforce that each Project belongs to one and only one Customer
4. WHEN an Admin_User views the project list, THE CRM_System SHALL display all Projects with their associated Customer name and current status, ordered by most recently created first
5. IF an Admin_User submits a Project creation form with a missing title or without selecting a Customer, THEN THE CRM_System SHALL reject the submission and display a validation error message indicating which required fields are missing

### Requirement 2: Project Status Tracking

**User Story:** As an admin, I want to update project status through defined stages, so that I can track where each project is up to.

#### Acceptance Criteria

1. THE CRM_System SHALL support the following Project statuses: Not Started, In Progress, On Hold, Awaiting Approval, Completed
2. WHEN an Admin_User changes a Project status, THE CRM_System SHALL record the new status and the timestamp of the change in a status history log
3. WHILE a Project status is Completed, THE CRM_System SHALL prevent further status changes unless an Admin_User explicitly reopens the Project, which sets the status back to In Progress
4. IF an Admin_User attempts to change the status of a Completed Project without using the reopen action, THEN THE CRM_System SHALL reject the change and display an error message indicating the Project must be reopened first

### Requirement 3: Project Tasks

**User Story:** As an admin, I want to create and manage tasks within a project, so that I can break down the work and show progress to the customer.

#### Acceptance Criteria

1. WHEN an Admin_User adds a Project_Task to a Project, THE CRM_System SHALL store the task title (maximum 255 characters), optional description (maximum 2000 characters), status defaulting to "To Do", and display order
2. THE CRM_System SHALL support the following Project_Task statuses: To Do, In Progress, Done
3. WHEN a Customer_User views a Project, THE CRM_System SHALL display all Project_Tasks with their current status, ordered by display order ascending
4. IF a Project has zero Project_Tasks, THEN THE CRM_System SHALL display project progress as 0%; otherwise THE CRM_System SHALL calculate and display project progress as the percentage of Project_Tasks marked as Done, rounded down to the nearest whole number
5. WHEN an Admin_User updates a Project_Task, THE CRM_System SHALL allow modification of the title, description, status, and display order
6. WHEN an Admin_User deletes a Project_Task, THE CRM_System SHALL remove the task from the Project and recalculate the project progress percentage

### Requirement 4: Project Documents

**User Story:** As an admin, I want to upload documents (agreements, contracts, quotes) to a project, so that the customer can access them in one place.

#### Acceptance Criteria

1. WHEN an Admin_User uploads a Project_Document, THE CRM_System SHALL store the file with a label (maximum 255 characters) and document type (Agreement, Contract, Quote, Design Asset, Other), accepting files up to 20 MB in size and restricted to the following formats: PDF, DOCX, XLSX, PNG, JPG, and ZIP
2. THE CRM_System SHALL allow Customer_User to download only Project_Documents associated with their own Project
3. WHEN a Project_Document is uploaded, THE CRM_System SHALL record the upload date and the Admin_User who uploaded the document
4. IF a Project_Document upload fails due to invalid file type, exceeding the maximum file size, or a storage error, THEN THE CRM_System SHALL reject the upload, display an error message indicating the reason for failure, and retain any previously uploaded documents unchanged
5. WHEN an Admin_User deletes a Project_Document, THE CRM_System SHALL remove the file from storage and remove the document record from the Project

### Requirement 5: Project Comments

**User Story:** As an admin, I want to add internal and public comments to a project, so that I can keep internal notes separate from customer-facing updates.

#### Acceptance Criteria

1. WHEN an Admin_User adds a Project_Comment, THE CRM_System SHALL require a comment body between 1 and 5000 characters and allow the Admin_User to mark the comment as internal or public
2. WHILE a Project_Comment is marked as internal, THE CRM_System SHALL display the comment only to Admin_Users
3. WHEN a Customer_User views a Project, THE CRM_System SHALL display only public Project_Comments in oldest-first chronological order
4. WHEN an Admin_User adds a Project_Comment, THE CRM_System SHALL record the author name and timestamp regardless of whether the comment is internal or public
5. WHEN an Admin_User views a Project, THE CRM_System SHALL display both internal and public Project_Comments in oldest-first chronological order, with internal comments visually distinguished from public comments
6. IF an Admin_User submits a Project_Comment with an empty body, THEN THE CRM_System SHALL reject the submission and display an error message indicating that comment body is required

### Requirement 6: Customer Notifications

**User Story:** As an admin, I want to send the customer a notification when there is a project update, so that the customer stays informed without needing to check the portal.

#### Acceptance Criteria

1. WHEN an Admin_User adds a public Project_Comment, THE Notification_Service SHALL send an email to all Customer_Users belonging to the Customer company associated with the Project, within 5 minutes of the comment being added
2. WHEN an Approval_Request is created, THE Notification_Service SHALL send an email to all Customer_Users belonging to the Customer company associated with the Project, including a link to the relevant Approval_Request in the portal
3. THE CRM_System SHALL include the project title and a summary of no more than 200 characters in all project notification emails
4. IF the Notification_Service fails to deliver an email, THEN THE CRM_System SHALL log the failure and display a notification to the Admin_User indicating that the customer email could not be sent

### Requirement 7: Document Approval Workflow

**User Story:** As an admin, I want to request customer approval on specific documents, so that I have a recorded sign-off before proceeding.

#### Acceptance Criteria

1. WHEN an Admin_User requests approval on a Project_Document, THE CRM_System SHALL create an Approval_Request linked to that document with an initial status of Pending
2. WHEN a Customer_User approves an Approval_Request, THE CRM_System SHALL update the Approval_Request status to Approved and record the Customer_User identity and timestamp
3. WHEN a Customer_User rejects an Approval_Request, THE CRM_System SHALL update the Approval_Request status to Rejected and record the rejection with an optional reason (maximum 1000 characters) provided by the Customer_User
4. THE CRM_System SHALL display the approval status (Pending, Approved, Rejected) on each Project_Document that has an Approval_Request
5. WHILE an Approval_Request status is Approved or Rejected, THE CRM_System SHALL prevent further status changes to that Approval_Request
6. IF an Admin_User requests approval on a Project_Document that already has a Pending Approval_Request, THEN THE CRM_System SHALL reject the request and display an error message indicating an active Approval_Request already exists for that document

### Requirement 8: Project Completion Approval

**User Story:** As an admin, I want to request customer sign-off to mark a project complete, so that both parties agree the work is finished.

#### Acceptance Criteria

1. WHEN an Admin_User requests completion approval, THE CRM_System SHALL create an Approval_Request of type "Project Completion" linked to the Project and set the Project status to Awaiting Approval
2. IF an Admin_User requests completion approval and a pending completion Approval_Request already exists for that Project, THEN THE CRM_System SHALL reject the request and display an error message indicating a completion approval is already pending
3. WHEN the Customer_User approves the completion Approval_Request, THE CRM_System SHALL record the approval with the Customer_User identity and timestamp, and update the Project status to Completed
4. IF the Customer_User rejects the completion Approval_Request, THEN THE CRM_System SHALL record the rejection with a reason provided by the Customer_User (between 1 and 1000 characters), keep the Project status as its previous value before Awaiting Approval, and notify the Admin_User via email of the rejection reason

### Requirement 9: Decisions and Design Requirements

**User Story:** As an admin, I want to record decisions and design requirements within a project, so that there is a clear record of what was agreed.

#### Acceptance Criteria

1. WHEN an Admin_User adds a Project_Decision, THE CRM_System SHALL store the decision title (maximum 255 characters), description (maximum 2000 characters), and date recorded
2. WHEN a user views a Project, THE CRM_System SHALL display all Project_Decisions showing the title, category label, description, and date recorded, ordered by date recorded descending (newest first)
3. WHEN an Admin_User adds a Project_Decision, THE CRM_System SHALL allow an optional category label (Design Requirement, Client Decision, Technical Decision)
4. IF an Admin_User submits a Project_Decision without a title, THEN THE CRM_System SHALL reject the submission and display a validation error indicating that a title is required

### Requirement 10: Customer Portal Project View

**User Story:** As a customer, I want to see my active projects in the portal, so that I can check progress and access documents without contacting the admin.

#### Acceptance Criteria

1. WHEN a Customer_User navigates to the projects section of the portal, THE CRM_System SHALL display a list of all Projects belonging to the Customer_User company that have a status other than Completed, ordered by most recently updated first
2. WHEN a Customer_User selects a Project, THE CRM_System SHALL display the project status, progress percentage, Project_Tasks ordered by display order, public Project_Comments in reverse chronological order, Project_Documents, Project_Decisions, and any pending Approval_Requests
3. IF a Customer_User has no Projects with a status other than Completed, THEN THE CRM_System SHALL display a message indicating no active projects exist
4. IF a Customer_User attempts to access a Project that does not belong to their company, THEN THE CRM_System SHALL deny access and return a not-found response
5. THE CRM_System SHALL restrict Customer_Users to viewing only Projects belonging to their own company, scoped by the Customer_User company_id
