---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Trash/Recycle Bin - Keep and Adapt Later

## Category
Trash/Recycle Bin

## Files Affected
- `lib/Trash/TrashBackend.php` - Trash integration
- `lib/Trash/TrashManager.php` - Trash operations
- `lib/Trash/GroupTrashItem.php` - Data structure

## Current State
Manages deleted files:
- Lists deleted files in trash view
- Restores files from trash
- Permanently deletes files
- Tracks deletion metadata (original location, timestamp)
- Respects ACL rules in trash

## Plan
**Keep the trash system - will need rewrite later for Git integration:**

- Update namespace to `OCA\Repos`
- Keep all trash infrastructure intact for now
- **Later: Rewrite to integrate with Git**
  - Idea: Create branch `trash/$fileid` for each deleted file
  - Branch would reference the delete commit
  - Restore from trash = revert the commit on that branch
  - But let's see - design TBD

### Implementation Strategy
- Minimal changes during refactoring stage (namespace updates only)
- Don't worry about implementation functionality now, we are in the refactoring stage
- Keep trash system functional as-is
- Git-based trash design and implementation TBD
