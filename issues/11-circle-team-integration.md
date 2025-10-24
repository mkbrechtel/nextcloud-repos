---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Circle/Team Integration - Remove Completely

## Category
Circle/Team Integration

## Files Affected
- `lib/Listeners/CircleDestroyedEventListener.php` - Circle deletion handler
- Circle-related code in `lib/Folder/FolderManager.php`
- Circle mappings in database schema
- Circle dependencies in `appinfo/info.xml`

## Current State
Integration with Nextcloud Circles app:
- Circles treated like groups for access control
- Circle-to-folder mappings in database
- Combined circle + group permission calculation
- Event listener for circle deletion cleanup

## Plan
**Remove completely:**

- Remove `CircleDestroyedEventListener.php`
- Remove circle dependency from `appinfo/info.xml`
- Remove circle-related code from business logic
- Remove circle columns/tables from database migrations
- Simplify to group-based permissions only (at least initially)

### Implementation Strategy
- Remove files and code during refactoring stage
- Don't worry about implementation functionality now, we are in the refactoring stage
- Focus on group-based access control only
- Circle support not planned for repositories app
