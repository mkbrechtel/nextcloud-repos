---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: App Configuration & Bootstrap - Rename and Adapt

## Category
App Configuration & Bootstrap

## Files Affected
- `appinfo/info.xml`
- `lib/AppInfo/Application.php`
- `lib/AppInfo/Capabilities.php`

## Current State
The app is currently named "groupfolders" with namespace `OCA\GroupFolders`. It's configured for team folder management with ACL, versioning, and trash integration.

## Plan
**Keep the structure, rename and update metadata:**

- **New app name:** `repos`
- **New namespace:** `OCA\Repos`
- **Display name:** "Repositories" or "Nextcloud Repositories"

### Changes needed:

1. **`appinfo/info.xml`:**
   - Change app ID from `groupfolders` to `repos`
   - Update display name to "Repositories"
   - Add vague summary describing Git/Git-Annex/Datalad repository integration
   - Keep most registered services (background jobs, commands, settings, trash, versioning, DAV plugins) - they'll be adapted in their respective categories
   - Update author/maintainer info

2. **`lib/AppInfo/Application.php`:**
   - Change namespace to `OCA\Repos`
   - Keep bootstrap structure intact
   - Keep service registration, event listeners, mount provider registration
   - Update any references from "groupfolders" to "repos"

3. **`lib/AppInfo/Capabilities.php`:**
   - Change namespace to `OCA\Repos`
   - Keep capabilities API structure
   - Update capability keys to reflect new app name

### Implementation Strategy
- Mostly search-and-replace operation for namespace and app name
- Keep all the bootstrap infrastructure as-is
- Don't worry about implementation functionality now, we are in the refactoring stage

## Status
✅ Implemented in commit 4550f3be on 2025-10-24
