---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Core Business Logic - Defer and Redesign

## Category
Core Business Logic

## Files Affected
- `lib/Folder/FolderManager.php`
- `lib/Folder/FolderDefinition.php`
- `lib/Folder/FolderDefinitionWithMappings.php`
- `lib/Service/DelegationService.php`
- `lib/Service/ApplicationService.php`
- `lib/Service/FoldersFilter.php`

## Current State
Core business logic for team folders:
- CRUD operations for folders
- Permission calculation (group + circle memberships)
- Quota management
- Delegated admin authorization
- Folder filtering for API responses

## Plan
**Defer implementation - TBD**

- Keep files in place for now
- Update namespace to `OCA\Repos`
- Rename `FolderManager` to `RepositoryManager` (or similar)
- Core business logic for repository management will be redesigned and implemented later

### Implementation Strategy
- Minimal changes during refactoring stage (namespace updates only)
- Don't worry about implementation functionality now, we are in the refactoring stage
- Actual repository management logic to be designed later
