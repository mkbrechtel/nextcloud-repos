---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Database Layer - Remove Completely

## Category
Database Layer

## Files Affected
- `lib/Migration/*.php` - All migration files (20+ files)
- Database schema for:
  - `group_folders` - Folder metadata
  - `group_folders_groups` - Group/circle mappings
  - `group_folders_acl` - ACL rules
  - `group_folders_trash` - Trash items
  - `group_folders_versions` - File versions
  - `group_folders_manage` - ACL managers
  - `group_folders_delegation_groups` - Delegated admins
  - `group_folders_delegation_circles` - Delegated admin circles
  - `group_folders_user_mapping` - User identity mappings
- Repair steps

## Current State
Complete database schema for team folders with migrations covering:
- Folder management
- ACL system
- Versioning
- Trash
- Delegation
- User mappings

## Plan
**Remove completely and redesign:**

- Remove all migration files from `lib/Migration/`
- Database schema for repositories will be completely different
- **Later: Design new database schema for:**
  - Repository metadata (Git URL, branch, clone path, etc.)
  - Access control (group/user permissions)
  - Repository configuration
  - Synchronization state
  - Probably much simpler schema (Git handles versioning, trash, etc.)

### Implementation Strategy
- Remove all migration files during refactoring
- Don't worry about implementation functionality now, we are in the refactoring stage
- New database schema design and migrations TBD
