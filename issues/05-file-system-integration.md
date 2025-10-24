---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: File System Integration - Keep and Adapt Later

## Category
File System Integration

## Files Affected
- `lib/Mount/MountProvider.php` - Mount discovery
- `lib/Mount/GroupFolderStorage.php` - Custom storage wrapper
- `lib/Mount/GroupMountPoint.php` - Mount point abstraction
- `lib/Mount/RootPermissionsMask.php` - Permission enforcement
- `lib/Mount/CacheRootPermissionsMask.php` - Cached permission mask
- `lib/Mount/GroupFolderEncryptionJail.php` - Encryption handling
- `lib/Mount/GroupFolderNoEncryptionStorage.php` - Non-encrypted storage
- `lib/Mount/FolderStorageManager.php` - Storage instance management
- `lib/Mount/RootEntryCache.php` - Root folder cache

## Current State
Critical infrastructure that:
- Integrates team folders into Nextcloud's virtual file system
- Discovers and mounts folders for users
- Wraps storage with ACL and permission enforcement
- Handles quota and caching

## Plan
**Keep the mount system - will need rewrite later for Git integration:**

- Update namespace to `OCA\Repos`
- Rename `GroupFolderStorage` to `RepositoryStorage` (or similar)
- Keep all mount infrastructure intact for now
- **Later: Rewrite to connect to Git repositories**
  - This is where Git-Annex special remote integration will happen
  - Storage layer will need to interact with Git working trees
  - File operations may need to trigger Git commits

### Implementation Strategy
- Minimal changes during refactoring stage (namespace updates, renames)
- Don't worry about implementation functionality now, we are in the refactoring stage
- Keep mount system functional as-is
- Git integration design and implementation TBD
