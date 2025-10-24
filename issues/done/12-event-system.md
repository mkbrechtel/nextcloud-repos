---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Event System - Keep Core, Remove Circles and Expiration

## Category
Event System

## Files Affected

### Keep:
- `lib/Listeners/LoadAdditionalScriptsListener.php` - Frontend loading
- `lib/Listeners/NodeRenamedListener.php` - Cache invalidation

### Remove:
- `lib/Listeners/CircleDestroyedEventListener.php` - Circle cleanup
- `lib/Event/GroupVersionsExpireEnterFolderEvent.php` - Expiration events
- `lib/Event/GroupVersionsExpireDeleteFileEvent.php` - Expiration events
- `lib/Event/GroupVersionsExpireDeleteVersionEvent.php` - Expiration events

## Current State
Event system provides:
- Frontend asset loading when Files app loads
- ACL cache invalidation when files renamed
- Circle deletion cleanup
- Custom extension points for version expiration

## Plan
**Keep core event listeners, remove circles and expiration:**

- **Keep:** `LoadAdditionalScriptsListener` - needed for frontend
- **Keep:** `NodeRenamedListener` - needed for cache invalidation
- **Remove:** `CircleDestroyedEventListener` - circles not supported
- **Remove:** All expiration events in `lib/Event/` - version expiration will work differently with Git
- Update namespace to `OCA\Repos` for kept files

### Implementation Strategy
- Delete circle and expiration event files
- Keep and update namespace for core listeners
- Don't worry about implementation functionality now, we are in the refactoring stage
- Core event listeners remain functional
