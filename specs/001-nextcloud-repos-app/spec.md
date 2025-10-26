---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Feature Specification: Nextcloud Repositories App

**Feature Branch**: `001-nextcloud-repos-app`
**Created**: 2025-10-26
**Status**: Draft
**Input**: User description: "The Nextcloud Repositories App provides Git repositories to Nextcloud users. The repositories are organized by organization and reachable under /apps/repos/{organization}/{repo}."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Create Repository Under Organization (Priority: P1)

A user wants to create a Git repository under an organization so that it's accessible via a structured URL path.

**Why this priority**: This is the core functionality - creating repositories organized under organizations with predictable URL routing. This is the foundation for all other functionality.

**Independent Test**: Can be fully tested by creating a repository under an organization and verifying it's accessible at `/apps/repos/{organization}/{repo}`.

**Acceptance Scenarios**:

1. **Given** a user creates a repository under an organization, **When** the creation completes, **Then** the repository becomes accessible at `/apps/repos/{organization}/{repo}`
2. **Given** a repository exists under an organization, **When** the user views the organization's repositories, **Then** they see the repository listed with its name and URL path
3. **Given** a repository is under an organization, **When** a user accesses `/apps/repos/{organization}/{repo}`, **Then** the system routes to the repository (file handling is separate functionality)

---

### User Story 2 - List Organization Repositories (Priority: P2)

A user wants to see all repositories organized by organization so they can easily discover and navigate to repositories.

**Why this priority**: Repository listing improves discoverability but isn't essential if users can access repositories directly via URL.

**Independent Test**: Can be tested by viewing the repository list interface and verifying it shows repositories grouped by organization.

**Acceptance Scenarios**:

1. **Given** repositories exist under multiple organizations, **When** the user views the repository list, **Then** they see repositories grouped by organization name
2. **Given** a new repository is created under an organization, **When** the user refreshes the repository list, **Then** the new repository appears under its organization
3. **Given** a repository is deleted from an organization, **When** the user views the repository list, **Then** that repository no longer appears

---

### User Story 3 - Delete Repository (Priority: P3)

A user wants to delete a repository from an organization so that it's no longer accessible and its URL path is freed.

**Why this priority**: Repository deletion is necessary for lifecycle management but can be deferred after creation and listing.

**Independent Test**: Can be tested by deleting a repository and verifying its URL is no longer accessible.

**Acceptance Scenarios**:

1. **Given** a repository exists under an organization, **When** the user deletes the repository, **Then** the repository URL `/apps/repos/{organization}/{repo}` returns a not found response
2. **Given** a repository is deleted, **When** the user views the organization's repository list, **Then** the deleted repository no longer appears
3. **Given** a repository is deleted, **When** the user attempts to create a new repository with the same name, **Then** the creation succeeds (name is now available)

---

### Edge Cases

- What happens when a repository URL is accessed but the repository doesn't exist or was deleted?
- How are repository and organization names with special characters (spaces, unicode, slashes) handled in URLs?
- What happens when two repositories in different organizations have the same name?
- What happens when a user tries to create a repository that already exists under the same organization?
- How does the system handle concurrent deletion requests for the same repository?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST organize repositories under organizations
- **FR-002**: System MUST provide URL routing for repositories following the pattern `/apps/repos/{organization}/{repo}`
- **FR-003**: System MUST allow users to create repositories under organizations
- **FR-004**: System MUST allow users to delete repositories from organizations
- **FR-005**: System MUST display appropriate error messages when repositories are not found
- **FR-006**: System MUST handle repository and organization names in URLs safely and unambiguously
- **FR-007**: System MUST provide a list view of repositories grouped by organization
- **FR-008**: System MUST prevent duplicate repository names within the same organization
- **FR-009**: System MUST allow repositories with the same name to exist under different organizations

### Key Entities

- **Repository** (German: "Ablage"): A Git repository organized under an organization. Identified by organization and repository name. Storage location and management are implementation details.
- **Organization**: A grouping structure for repositories. Repositories belong to exactly one organization. Organization names form part of repository URL paths.
- **Repository Metadata**: Information about a repository including its name, URL path, and organization association.

### Localization

- **German terminology**: Repositories are called "Ablage" (plural: "Ablagen") in German localization
- All user-facing text MUST support internationalization
- The term "repository" in English corresponds to "Ablage" in German

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users can create a repository under an organization in under 1 minute
- **SC-002**: Repository URLs are accessible within 2 seconds of request
- **SC-003**: Users can discover repositories organized by organization without external documentation
- **SC-004**: Repository deletion completes within 5 seconds
- **SC-005**: 90% of users can successfully locate and access a repository on first attempt
- **SC-006**: Repository list displays correctly for organizations with at least 100 repositories
