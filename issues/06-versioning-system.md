---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Versioning System - Keep and Adapt Later

## Category
Versioning System

## Files Affected
- `lib/Versions/VersionsBackend.php` - Nextcloud versioning integration
- `lib/Versions/GroupVersionsMapper.php` - Database persistence
- `lib/Versions/GroupVersion.php` / `GroupVersionEntity.php` - Data structures
- `lib/Versions/GroupVersionsExpireManager.php` - Version cleanup
- `lib/Versions/ExpireManager.php` - Base expiration logic

## Current State
Manages file version history:
- Stores versions in `.versions` subfolder
- Retrieves and restores previous versions
- Handles version metadata
- Automatic expiration based on retention policies

## Plan
**Keep the versioning system - will need rewrite later for Git integration:**

- Update namespace to `OCA\Repos`
- Keep all versioning infrastructure intact for now
- **Later: Rewrite to integrate with Git history**
  - Instead of storing separate versions, expose Git commit history
  - Version restoration could use `git checkout` or similar
  - Version metadata from Git commit messages and authors
  - Expiration might not be needed (Git handles history)

### Implementation Strategy
- Minimal changes during refactoring stage (namespace updates only)
- Don't worry about implementation functionality now, we are in the refactoring stage
- Keep versioning system functional as-is
- Git history integration design and implementation TBD
