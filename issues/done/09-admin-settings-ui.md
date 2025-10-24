---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Admin Settings UI - Remove and Reimplement Later

## Category
Admin Settings UI

## Files Affected
- `lib/Settings/Admin.php` - Backend settings integration
- `lib/Settings/Section.php` - Admin section definition
- `src/settings/App.tsx` - Main React admin component (800+ lines)
- `src/settings/Api.ts` - API client
- `src/settings/FolderGroups.tsx` - Group management UI
- `src/settings/QuotaSelect.tsx` - Quota selector
- `src/settings/AdminGroupSelect.tsx` / `SubAdminGroupSelect.tsx` - Admin selectors
- `src/settings/SubmitInput.tsx` - Input component
- `src/settings/SortArrow.tsx` - Table sort indicator
- `src/settings/*.scss` - Styles

## Current State
Admin panel for managing team folders:
- Folder creation/deletion
- Group/circle assignments
- Permission management
- Quota configuration
- ACL toggles
- Delegated admin selection

## Plan
**Remove and reimplement later - totally different UI needed:**

- Remove or stub out current admin UI during refactoring
- Update namespace to `OCA\Repos` if keeping stubs
- **Later: Design and implement completely new admin UI for repositories**
  - Repository creation (clone from Git URL)
  - Repository configuration (branches, remotes, Git-Annex settings)
  - Access control (who can access which repos)
  - Maybe provide more self-management functionality for users
  - Different UX/UI paradigm for repository management

### Implementation Strategy
- Remove or disable during refactoring stage
- Don't worry about implementation functionality now, we are in the refactoring stage
- New admin UI design and implementation TBD

## Status
✅ Implemented in commit d0bec6aa on 2025-10-24
