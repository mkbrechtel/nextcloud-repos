---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Background Jobs - Keep Structure, Remove Expiration

## Category
Background Jobs

## Files Affected

### Keep:
- Background job infrastructure and registration

### Remove:
- `lib/BackgroundJob/ExpireGroupVersions.php` - Version expiration
- `lib/BackgroundJob/ExpireGroupTrash.php` - Trash expiration
- `lib/BackgroundJob/ExpireGroupPlaceholder.php` - Test/placeholder job

## Current State
Periodic maintenance tasks executed by Nextcloud cron:
- Expires old file versions based on retention policy
- Deletes old trash items (30+ days)
- Background job infrastructure

## Plan
**Keep background job infrastructure, remove expiration jobs:**

- **Remove:** Expiration jobs (versions, trash) - Git handles history differently
- **Keep:** Background job registration mechanism - will come in handy later
- Update namespace to `OCA\Repos`
- **Later:** Use background jobs for repository maintenance tasks
  - Git garbage collection
  - Git-Annex unused file cleanup
  - Repository synchronization
  - Other periodic Git operations

### Implementation Strategy
- Remove expiration job files
- Keep background job infrastructure in bootstrap
- Don't worry about implementation functionality now, we are in the refactoring stage
- New background jobs for Git operations TBD
