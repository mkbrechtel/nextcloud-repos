---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: REST API Layer - Keep Structure, Replace with Repository API

## Category
REST API Layer

## Files Affected
- `lib/Controller/FolderController.php`
- `lib/Controller/DelegationController.php`

## Current State
Current API provides OCS endpoints for:
- Folder CRUD operations
- Group/circle assignments and permissions
- ACL management
- Quota management
- Mount point configuration

## Plan
**Keep the controller files and structure, but API will need to be redesigned:**

- Keep the file locations and general controller pattern
- Rename from `FolderController` to something like `RepositoryController`
- Update namespace to `OCA\Repos`
- **Replace functionality with repository-specific API (TBD)**

### API Requirements (to be designed later)
We will need some sort of API for:
- Repository management (create, delete, list repositories)
- Access control (groups/users with repository permissions)
- Repository configuration (Git remote URLs, branch settings, etc.)
- Repository operations (clone, pull, push triggers, etc.)

### Implementation Strategy
- Rename and update namespace initially
- Keep controller structure intact
- Don't worry about implementation functionality now, we are in the refactoring stage
- Actual API design and implementation will be done later
