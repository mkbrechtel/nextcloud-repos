---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Files App Integration - Keep and Enhance Later

## Category
Files App Integration

## Files Affected
- `src/init.ts` - Frontend initialization, view registration
- `src/services/groupfolders.ts` - WebDAV service
- `src/services/client.ts` - WebDAV client
- `src/components/SharingSidebarView.vue` - Sidebar integration
- `src/components/AclStateButton.vue` - ACL state toggle
- `src/actions/openGroupfolderAction.ts` - File actions
- `src/files.js` / `src/SharingSidebarApp.js` - Legacy entry points

## Current State
Integrates team folders into Nextcloud Files app:
- Registers "Team folders" view in Files app
- Adds navigation item to Files sidebar
- Shows folder info in sharing sidebar
- Custom file actions

## Plan
**Keep Files app integration - enhance with Git/Git-Annex info later:**

- Update namespace to `OCA\Repos`
- Rename "Team folders" to "Repositories" (or similar)
- Keep all Files app integration intact
- **Later: Add Git and Git-Annex information to Files app**
  - Show Git commit history in sidebar
  - Display current branch, commit info
  - Show Git-Annex status (annexed vs. regular file)
  - Show special remote storage location
  - Maybe file actions for Git operations (checkout, pull, etc.)

### Implementation Strategy
- Minimal changes during refactoring stage (namespace updates, renames)
- Don't worry about implementation functionality now, we are in the refactoring stage
- Keep Files app integration functional as-is
- Git/Git-Annex metadata display TBD
