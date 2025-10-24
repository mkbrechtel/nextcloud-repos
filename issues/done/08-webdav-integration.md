---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: WebDAV Integration - Keep, Special Attention Needed

## Category
WebDAV Integration

## Files Affected
- `lib/DAV/RootCollection.php` - WebDAV entry point
- `lib/DAV/GroupFoldersHome.php` - User folder collection
- `lib/DAV/GroupFolderNode.php` - Folder WebDAV node
- `lib/DAV/ACLPlugin.php` - WebDAV ACL enforcement
- `lib/DAV/PropFindPlugin.php` - Custom DAV properties

## Current State
Exposes team folders via WebDAV/CalDAV protocol:
- Desktop/mobile clients access files via WebDAV
- Custom properties (quota, permissions, folder ID)
- ACL enforcement in WebDAV layer
- Integrates with Sabre/DAV

## Plan
**Keep WebDAV - needs special attention for Git-Annex special remote integration:**

- Update namespace to `OCA\Repos`
- Keep all WebDAV infrastructure intact
- **IMPORTANT: This is where we marry WebDAV with Git-Annex special remote**
  - WebDAV will be the interface for the Git-Annex special remote
  - Annexed files accessed via Nextcloud's WebDAV
  - Need to design how WebDAV operations interact with Git-Annex
  - Custom DAV properties might expose Git/Annex metadata

### Implementation Strategy
- Minimal changes during refactoring stage (namespace updates only)
- Don't worry about implementation functionality now, we are in the refactoring stage
- Keep WebDAV functional as-is
- **Requires special attention** for Git-Annex special remote design
- WebDAV + Git-Annex integration TBD
