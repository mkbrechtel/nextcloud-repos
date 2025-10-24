---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: CLI Commands - Keep, Major Changes Later

## Category
CLI Commands

## Files Affected
- `lib/Command/Create.php` - Create folder
- `lib/Command/Delete.php` - Delete folder
- `lib/Command/Rename.php` - Rename folder
- `lib/Command/Scan.php` - Scan for file changes
- `lib/Command/Quota.php` - Set quota
- `lib/Command/Group.php` - Manage group assignments
- `lib/Command/ACL.php` - Manage ACL
- `lib/Command/ListCommand.php` - List folders
- `lib/Command/ExpireGroupVersions.php` - Expire versions (remove)
- `lib/Command/ExpireGroupTrash.php` - Expire trash (remove)
- `lib/Command/ExpireGroupVersionsTrash.php` - Expire all (remove)
- `lib/Command/FolderCommand.php` - Base class

## Current State
OCC commands for CLI administration:
- Folder CRUD operations
- Group/quota management
- Scanning and maintenance
- Expiration commands

## Plan
**Keep CLI commands - major changes later:**

- Update namespace to `OCA\Repos`
- **Remove:** Expiration commands (ExpireGroupVersions, ExpireGroupTrash, ExpireGroupVersionsTrash)
- **Keep:** All other commands for now
- **Later: Major redesign for repository management**
  - `repos:create` → Clone Git repository
  - `repos:scan` → Git fetch/pull operations
  - New commands for Git operations (push, pull, status, gc, etc.)
  - Commands for Git-Annex operations
  - Different parameters and workflows

### Implementation Strategy
- Minimal changes during refactoring stage (namespace updates, remove expiration)
- Don't worry about implementation functionality now, we are in the refactoring stage
- Keep command infrastructure functional
- Major command redesign and implementation TBD
