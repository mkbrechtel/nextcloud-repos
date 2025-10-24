<!--
SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Codebase Analysis: Nextcloud Repositories App (formerly Team Folders/GroupFolders)

This document provides a comprehensive analysis of the Nextcloud Repositories app codebase, organized by functional categories. **Note: This codebase has been forked from the Team Folders app (formerly "Group Folders") and is being refactored to create Git/Git-Annex/Datalad repository integration.**

**Refactoring Status:** A comprehensive refactoring plan has been created in the `issues/` directory, with 20 issue files documenting the strategy for converting this app to the Repositories app. See `issues/README.md` for details. **The app has been renamed from 'groupfolders' to 'repos' with namespace OCA\Repos.**

**App Information:**
- **App ID:** `repos` (renamed from `groupfolders`)
- **Display Name:** Repositories (renamed from "Team Folders")
- **Namespace:** `OCA\Repos` (changed from `OCA\GroupFolders`)
- **Version:** 21.0.0-dev.1
- **Nextcloud Requirement:** >= 33
- **Backend:** PHP 8.2 (97 files, ~9,236 lines)
- **Frontend:** TypeScript/React/Vue (148 files)

---

## Table of Contents

1. [App Configuration & Bootstrap](#1-app-configuration--bootstrap)
2. [REST API Layer](#2-rest-api-layer)
3. [Core Business Logic](#3-core-business-logic)
4. [Access Control Lists (ACL)](#4-access-control-lists-acl)
5. [File System Integration](#5-file-system-integration)
6. [Versioning System](#6-versioning-system)
7. [Trash/Recycle Bin](#7-trashrecycle-bin)
8. [WebDAV Integration](#8-webdav-integration)
9. [Admin Settings UI](#9-admin-settings-ui)
10. [Files App Integration](#10-files-app-integration)
11. [Circle/Team Integration](#11-circleteam-integration)
12. [Event System](#12-event-system)
13. [Background Jobs](#13-background-jobs)
14. [CLI Commands](#14-cli-commands)
15. [Database Layer](#15-database-layer)
16. [Testing Infrastructure](#16-testing-infrastructure)
17. [Build & CI/CD](#17-build--cicd)
18. [Localization](#18-localization)
19. [Configuration Files](#19-configuration-files)
20. [Key Architecture Patterns](#20-key-architecture-patterns)

---

## 1. App Configuration & Bootstrap

### Location: `appinfo/`

**Purpose:** Defines the app's identity, capabilities, and initializes all components.

### Status: ✅ UPDATED - App renamed to "repos"

#### Key Files:

##### `appinfo/info.xml` (App Manifest)
**Updated:** App renamed from 'groupfolders' to 'repos'

The central configuration file that defines:
- App metadata:
  - **ID:** `repos` (changed from `groupfolders`)
  - **Name:** "Repositories" (changed from "Team Folders")
  - Version, author, license
- Nextcloud version requirements
- Dependencies ~~(circles app removed)~~
- Registered services:
  - ~~Background jobs (removed)~~
  - Commands (create, delete, rename, scan, quota, group, ACL commands)
  - ~~Settings (admin panel removed)~~
  - Trash backend integration
  - Versioning backend integration
  - DAV plugins (PropFindPlugin, ACLPlugin)

##### `lib/AppInfo/Application.php` (Bootstrap)
**Lines:** ~250+ lines
**Significance:** 🔴 Critical - Application entry point
**Updated:** Namespace changed to `OCA\Repos`

Main bootstrap class implementing `IBootstrap`. Responsibilities:
- **Service Registration:** Configures dependency injection container
- **Event Listeners:** Registers listeners for:
  - `LoadAdditionalScriptsEvent` - Loads frontend resources
  - `BeforeTemplateRenderedEvent` - Injects sharing sidebar
  - ~~`CircleDestroyedEvent` (removed)~~
  - `NodeRenamedEvent` - Invalidates ACL cache on file rename
- **Mount Provider:** Registers `MountProvider` for folder discovery
- **ACL Manager:** Configures ACL system with user mapping
- ~~**Background Jobs:** (removed)~~

##### `lib/AppInfo/Capabilities.php`
**Updated:** Namespace changed to `OCA\Repos`

Exposes app capabilities to Nextcloud clients via the Capabilities API.

---

## 2. REST API Layer

### Location: `lib/Controller/`

**Purpose:** Provides REST/OCS API endpoints for managing team folders.

#### Key Files:

##### `lib/Controller/FolderController.php` (Main API)
**Lines:** ~600+ lines
**Significance:** 🔴 Critical - Primary API interface

OCS Controller providing endpoints for:

**Folder Management:**
- `GET /apps/groupfolders/folders` - List all accessible folders
- `POST /apps/groupfolders/folders` - Create new folder
- `DELETE /apps/groupfolders/folders/{id}` - Delete folder
- `POST /apps/groupfolders/folders/{id}/rename` - Rename folder
- `POST /apps/groupfolders/folders/{id}/quota` - Set quota

**Group/Circle Assignment:**
- `POST /apps/groupfolders/folders/{id}/groups` - Add group to folder
- `DELETE /apps/groupfolders/folders/{id}/groups/{group}` - Remove group
- `POST /apps/groupfolders/folders/{id}/groups/{group}` - Set group permissions

**ACL Management:**
- `POST /apps/groupfolders/folders/{id}/acl` - Enable/disable ACL
- `POST /apps/groupfolders/folders/{id}/manageACL` - Set ACL management permission
- `GET /apps/groupfolders/folders/{id}/search` - Search users/groups for ACL

**Advanced Folders:**
- `POST /apps/groupfolders/folders/{id}/mountpoint` - Set mount point name

##### `lib/Controller/DelegationController.php`
**Lines:** ~150+ lines
**Significance:** 🟡 Important - Delegated admin management

Manages delegated administration:
- Get/set admin groups (full folder management rights)
- Get/set subadmin groups (limited folder management rights)

---

## 3. Core Business Logic

### Location: `lib/Folder/`, `lib/Service/`

**Purpose:** Core folder management, permission calculation, and business rules.

#### Key Files:

##### `lib/Folder/FolderManager.php` (Core Manager)
**Lines:** ~500+ lines
**Significance:** 🔴 Critical - Core business logic

The heart of the application. Responsibilities:
- **CRUD Operations:** Create, read, update, delete folders
- **Group/Circle Mappings:** Associate folders with groups and circles
- **Permission Calculation:** Compute effective user permissions
  - Combines group memberships
  - Merges circle memberships
  - Applies permission bitwise operations
- **Quota Management:** Set and enforce storage quotas
- **Folder Renaming:** Handle mount point changes
- **User Access Queries:** Determine which folders a user can access
- **Database Operations:** Direct interaction with folder tables

**Key Methods:**
- `getAllFolders()` - Get all folders with mappings
- `getAllFoldersWithSize()` - Include storage size calculations
- `createFolder()` - Create new team folder
- `removeFolder()` - Delete folder and cleanup
- `getFoldersForGroup()` / `getFoldersForGroups()` - Query by group
- `getFoldersForUser()` - Get user's accessible folders
- `setMountPoint()` - Rename folder mount point
- `addApplicableGroup()` / `removeApplicableGroup()` - Manage group assignments

##### `lib/Folder/FolderDefinition.php`
Base data structure for folder metadata:
- ID, mount point, quota, size, ACL enabled status

##### `lib/Folder/FolderDefinitionWithMappings.php`
Extends base with group/circle mappings and permissions.

##### `lib/Service/DelegationService.php`
**Lines:** ~100+ lines
**Significance:** 🟡 Important - Authorization logic

Manages delegated admin roles:
- Checks if user has admin/subadmin rights
- Validates user permissions for folder operations
- Integrates with Nextcloud group manager

##### `lib/Service/ApplicationService.php`
Checks if required apps (Circles) are installed and enabled.

##### `lib/Service/FoldersFilter.php`
Filters folder lists for API responses based on user permissions.

---

## 4. Access Control Lists (ACL)

### Location: `lib/ACL/`

**Purpose:** Fine-grained permission management at the file/folder level within team folders.

#### Key Files:

##### `lib/ACL/ACLManager.php` (Permission Evaluator)
**Lines:** ~400+ lines
**Significance:** 🔴 Critical - Permission engine

Core ACL evaluation engine. Responsibilities:
- **Rule Retrieval:** Fetch ACL rules for files/paths
- **Permission Calculation:** Compute effective permissions
  - Evaluates rules based on user/group/circle membership
  - Applies permission masks
  - Handles inheritance
- **Caching:** Uses `CappedMemoryCache` for performance
- **Trash Integration:** Considers trash status in permission checks

**Key Methods:**
- `getACLPermissionsForPath()` - Get permissions for specific path
- `getRules()` - Get all ACL rules for a storage
- `hasACLPermissions()` - Check if user has specific permissions
- `canManageACL()` - Check if user can modify ACL rules

##### `lib/ACL/RuleManager.php`
**Lines:** ~300+ lines
**Significance:** 🟡 Important - ACL persistence

Database operations for ACL rules:
- CRUD operations for rules
- Query rules by path, file ID, or storage
- Save rules in batches
- Delete rules for specific paths

##### `lib/ACL/Rule.php`
Data structure representing a single ACL rule:
- User/group/circle mapping
- Path pattern
- Permission flags (read, write, delete, share, etc.)
- Rule inheritance settings

##### `lib/ACL/ACLStorageWrapper.php`
**Lines:** ~600+ lines
**Significance:** 🔴 Critical - Storage integration

Storage wrapper that applies ACL rules to file operations:
- **File Filtering:** Hides files user doesn't have access to
- **Permission Enforcement:** Blocks unauthorized operations
- **Directory Listings:** Filters folder contents based on ACL
- **Metadata Operations:** Applies ACL to stat, info, etc.

Wraps methods like:
- `opendir()` - Filter directory listings
- `file_exists()` - Check ACL before confirming existence
- `unlink()`, `rmdir()` - Enforce delete permissions
- `fopen()` - Check read/write permissions

##### `lib/ACL/ACLCacheWrapper.php`
Caches ACL-filtered file listings for performance.

##### `lib/ACL/ACLManagerFactory.php`
Factory pattern for creating per-user ACL manager instances.

##### `lib/ACL/UserMapping/` (User Identity Mapping)
Maps users/groups/circles to ACL entities:

- **`UserMapping.php`** - Individual mapping representation
- **`UserMappingManager.php`** - Database operations for mappings
  - Convert users/groups to mapping IDs
  - Bulk user mapping queries
  - Type detection (user vs group vs circle)

---

## 5. File System Integration

### Location: `lib/Mount/`

**Purpose:** Integrates team folders into Nextcloud's virtual file system.

#### Key Files:

##### `lib/Mount/MountProvider.php` (Mount Discovery)
**Lines:** ~400+ lines
**Significance:** 🔴 Critical - File system bridge

Implements `IMountProvider` to mount folders for users. Responsibilities:
- **User Mount Discovery:** Find all folders user has access to
- **Mount Point Creation:** Create mount points in user's file tree
- **Permission Application:** Apply group-level permissions to mounts
- **ACL Wrapping:** Wrap storage with ACL enforcement if enabled
- **Conflict Resolution:** Handle mount point name conflicts
- **Caching:** Uses folder cache for performance

**Key Methods:**
- `getMountsForUser()` - Get all mounts for a user
- `getFoldersForUser()` - Query accessible folders
- `createStorage()` - Instantiate storage backend
- `wrapStorage()` - Apply ACL and permission wrappers

##### `lib/Mount/GroupFolderStorage.php`
**Lines:** ~150+ lines
**Significance:** 🟡 Important - Storage wrapper

Custom storage implementation:
- Wraps underlying Nextcloud storage
- Provides folder-specific storage operations
- Handles quota enforcement
- Manages storage ID generation

##### `lib/Mount/GroupMountPoint.php`
**Lines:** ~100+ lines
**Significance:** 🟡 Important - Mount point abstraction

Represents a mounted team folder:
- Extends Nextcloud's `MountPoint`
- Stores folder metadata (ID, quota, ACL status)
- Provides folder-specific context

##### `lib/Mount/RootPermissionsMask.php`
**Lines:** ~100+ lines
**Significance:** 🟡 Important - Permission enforcement

Applies permission masks at folder root:
- Enforces group-level permissions
- Prevents privilege escalation
- Masks write/delete/share permissions based on group settings

##### `lib/Mount/CacheRootPermissionsMask.php`
Cached variant of permission mask for performance.

##### `lib/Mount/GroupFolderEncryptionJail.php`
Handles encrypted folder access with encryption app integration.

##### `lib/Mount/GroupFolderNoEncryptionStorage.php`
Storage variant for non-encrypted folders.

##### `lib/Mount/FolderStorageManager.php`
Manages storage instances for folders (singleton pattern per folder).

##### `lib/Mount/RootEntryCache.php`
Caches root folder entry metadata for faster access.

---

## 6. Versioning System

### Location: `lib/Versions/`

**Purpose:** Manages file version history within team folders.

#### Key Files:

##### `lib/Versions/VersionsBackend.php` (Versions Integration)
**Lines:** ~500+ lines
**Significance:** 🔴 Critical - Version management

Implements Nextcloud's versioning interfaces:
- `IVersionBackend` - Basic version operations
- `IMetadataVersionBackend` - Version metadata support
- `IDeletableVersionBackend` - Version deletion

**Responsibilities:**
- **Version Storage:** Stores versions in `.versions` subfolder
- **Version Retrieval:** Query version history for files
- **Version Restoration:** Restore previous file versions
- **Version Deletion:** Delete old/unwanted versions
- **Metadata Support:** Store/retrieve version metadata
- **Expiration:** Integrates with expiration system

**Key Methods:**
- `useFirstVersion()` - Check if versioning enabled
- `getVersionsForFile()` - Get all versions of a file
- `createVersion()` - Create new version on file change
- `rollback()` - Restore a previous version
- `deleteVersion()` - Remove a version
- `setMetadataValue()` / `getMetadata()` - Version metadata operations

##### `lib/Versions/GroupVersionsMapper.php`
**Lines:** ~200+ lines
**Significance:** 🟡 Important - Version persistence

Database mapper for versions:
- Query versions by file ID
- Store version metadata
- Delete version records

##### `lib/Versions/GroupVersion.php` / `GroupVersionEntity.php`
Data structures representing file versions.

##### `lib/Versions/GroupVersionsExpireManager.php`
**Lines:** ~300+ lines
**Significance:** 🟡 Important - Version cleanup

Manages version expiration:
- Applies retention policies
- Deletes old versions to save space
- Fires events during expiration (for extensions)
- Implements Nextcloud's expiration algorithm

##### `lib/Versions/ExpireManager.php`
Base expiration logic (shared with other apps).

---

## 7. Trash/Recycle Bin

### Location: `lib/Trash/`

**Purpose:** Manages deleted files within team folders (integration with Nextcloud Trash app).

#### Key Files:

##### `lib/Trash/TrashBackend.php` (Trash Integration)
**Lines:** ~400+ lines
**Significance:** 🔴 Critical - Trash management

Implements `ITrashBackend` for trash integration. Responsibilities:
- **Trash Listing:** Show deleted files in trash view
- **File Restoration:** Restore files from trash
- **Trash Cleanup:** Permanently delete files
- **Permission Enforcement:** Respect ACL rules in trash
- **Expiration:** Remove old trash items automatically

**Key Methods:**
- `listTrashRoot()` / `listTrashFolder()` - List trash contents
- `restoreItem()` - Restore deleted file
- `removeItem()` - Permanently delete
- `moveToTrash()` - Move file to trash
- `getTrashNodeById()` - Retrieve specific trash item

##### `lib/Trash/TrashManager.php`
**Lines:** ~250+ lines
**Significance:** 🟡 Important - Trash operations

Database and filesystem operations for trash:
- Query deleted items
- Track deletion metadata (original location, delete time)
- Handle trash restoration logic
- Manage trash folder structure

##### `lib/Trash/GroupTrashItem.php`
Data structure representing a trash item:
- Original path
- Deletion timestamp
- Restore target
- File metadata

---

## 8. WebDAV Integration

### Location: `lib/DAV/`

**Purpose:** Exposes team folders via WebDAV/CalDAV protocol for desktop/mobile clients.

#### Key Files:

##### `lib/DAV/RootCollection.php`
**Lines:** ~100+ lines
**Significance:** 🟡 Important - WebDAV entry point

Root DAV collection for group folders:
- Implements `AbstractPrincipalCollection` (Sabre/DAV)
- Returns `GroupFoldersHome` for each user
- Integrates with Nextcloud's DAV system

##### `lib/DAV/GroupFoldersHome.php`
**Lines:** ~150+ lines
**Significance:** 🟡 Important - User folder collection

User's WebDAV collection of group folders:
- Returns `GroupFolderNode` for each accessible folder
- Implements collection interface for Sabre

##### `lib/DAV/GroupFolderNode.php`
**Lines:** ~200+ lines
**Significance:** 🟡 Important - Folder WebDAV node

Individual folder node in DAV tree:
- Exposes folder as WebDAV collection
- Handles properties (quota, permissions, etc.)
- Provides folder metadata via DAV properties

##### `lib/DAV/ACLPlugin.php`
**Lines:** ~150+ lines
**Significance:** 🟡 Important - WebDAV ACL enforcement

Sabre plugin applying ACL to WebDAV:
- Intercepts DAV requests
- Applies ACL rules
- Returns proper DAV ACL responses

##### `lib/DAV/PropFindPlugin.php`
**Lines:** ~200+ lines
**Significance:** 🟡 Important - Custom DAV properties

Adds custom WebDAV properties:
- Folder ID
- Quota information
- ACL status
- Group/circle mappings

---

## 9. Admin Settings UI

### Status: ❌ REMOVED

**Removed in refactoring** - The entire admin settings UI has been deleted. This includes both backend and frontend components. A complete redesign will be needed for the Repositories app.

**What was removed:**

### Backend (lib/Settings/):
- `lib/Settings/Admin.php` - Settings provider and React app loader
- `lib/Settings/Section.php` - Admin section definition

### Frontend (src/settings/):
- `src/settings/App.tsx` - Main React admin component (~800 lines)
- `src/settings/Api.ts` - TypeScript API client (~400 lines)
- `src/settings/FolderGroups.tsx` - Group management component
- `src/settings/QuotaSelect.tsx` - Quota selector dropdown
- `src/settings/AdminGroupSelect.tsx` - Admin group selector
- `src/settings/SubAdminGroupSelect.tsx` - Subadmin group selector
- `src/settings/SubmitInput.tsx` - Input with submit button
- `src/settings/SortArrow.tsx` - Table sort indicator
- `src/settings/index.tsx` - React app entry point
- All related SCSS files (App.scss, EditSelect.scss, FolderGroups.scss)

**Current state:** No admin UI. The Repositories app will need a completely new admin interface designed for Git repository management (clone URLs, branch settings, etc.).

---

## 10. Files App Integration

### Location: `src/`

**Purpose:** Integrates team folders into Nextcloud Files app.

#### Key Files:

##### `src/init.ts` (Frontend Initialization)
**Lines:** ~150+ lines
**Significance:** 🔴 Critical - Files app integration

Main frontend entry point. Responsibilities:
- **View Registration:** Creates "Team folders" view in Files app
- **File Actions:** Registers "Open in Team folders" action
- **Navigation:** Adds navigation item to Files sidebar
- **Icon Support:** Provides light/dark theme icons

Uses Nextcloud Files APIs:
- `@nextcloud/files` - Navigation, View registration
- `registerFileAction` - Custom file actions
- `FileAction` - Action definitions

##### `src/services/groupfolders.ts` (WebDAV Service)
**Lines:** ~200+ lines
**Significance:** 🟡 Important - File access

WebDAV service for accessing team folders from frontend:
- Uses `webdav` library
- **Methods:**
  - `getContents()` - List folder contents
  - `resultToNode()` - Convert WebDAV response to Nextcloud Node
- **DAV Properties:** Queries custom properties (folder ID, permissions, etc.)

##### `src/services/client.ts`
Initializes and configures WebDAV client with Nextcloud authentication.

##### `src/components/SharingSidebarView.vue`
**Lines:** ~100+ lines
**Significance:** 🟡 Important - Sidebar integration

Vue component for file sharing sidebar:
- Shows team folder info in Files app sidebar
- Displays folder properties (quota, groups, etc.)

##### `src/components/AclStateButton.vue`
ACL state toggle button component.

##### `src/actions/openGroupfolderAction.ts`
File action to navigate to team folder view.

##### `src/files.js` / `src/SharingSidebarApp.js`
Legacy entry points for Files app integration.

---

## 11. Circle/Team Integration

### Status: ❌ REMOVED

**Removed in refactoring** - Circle/Team integration has been completely removed to simplify the codebase to group-based permissions only. Circle support is not needed for the Git/Git-Annex repository app.

**What was removed:**
- `lib/Listeners/CircleDestroyedEventListener.php` (deleted)
- Circle event listener registration in `Application.php`
- Circle-related methods from `FolderManager.php`:
  - `getCircles()`, `searchCircles()`, `getFoldersFromCircleMemberships()`
  - `deleteCircle()`, `isACircle()`, `getCircle()`, `getCirclesManager()`
- Database queries with `circle_id` checks
- Circle search functionality from API endpoints

**Current state:** The app now uses **group-based access control exclusively**.

---

## 12. Event System

### Location: `lib/Listeners/`, `lib/Event/`

**Purpose:** React to Nextcloud events and provide extension points.

### Event Listeners: `lib/Listeners/`

##### `lib/Listeners/LoadAdditionalScriptsListener.php`
**Lines:** ~100+ lines
**Significance:** 🟡 Important - Frontend loading

Listens to `LoadAdditionalScriptsEvent`:
- Loads frontend JavaScript/CSS when Files app or sharing UI loads
- Injects React/Vue bundles into page
- Provides initial state to frontend

##### `lib/Listeners/CircleDestroyedEventListener.php`
**Lines:** ~50+ lines
**Significance:** 🟡 Important - Circle cleanup

Handles circle deletion:
- Removes folder mappings when circle deleted
- Prevents orphaned permissions

##### `lib/Listeners/NodeRenamedListener.php`
**Lines:** ~75+ lines
**Significance:** 🟡 Important - Cache invalidation

Listens to `NodeRenamedEvent`:
- Invalidates ACL cache when files/folders renamed
- Ensures permission checks use updated paths
- Prevents stale ACL rules

### Custom Events: `lib/Event/`

**Purpose:** Extension points for plugins/customization.

##### `lib/Event/GroupVersionsExpireEnterFolderEvent.php`
Fired when version expiration enters a folder (allows custom retention policies).

##### `lib/Event/GroupVersionsExpireDeleteFileEvent.php`
Fired when file deleted during version expiration.

##### `lib/Event/GroupVersionsExpireDeleteVersionEvent.php`
Fired when version deleted during expiration.

**Use Case:** Plugins can listen to these events to:
- Implement custom retention policies
- Log version deletions
- Backup versions before deletion

---

## 13. Background Jobs

### Status: ❌ REMOVED

**Removed in refactoring** - All expiration background jobs have been deleted. These were specific to the old versioning and trash expiration system.

**What was removed:**
- `lib/BackgroundJob/ExpireGroupVersions.php` - Version expiration job
- `lib/BackgroundJob/ExpireGroupTrash.php` - Trash expiration job
- `lib/BackgroundJob/ExpireGroupPlaceholder.php` - Placeholder/test job
- Background job registrations in `appinfo/info.xml`

**Current state:** No background jobs. Will need to be recreated if the Repositories app requires periodic maintenance tasks (e.g., Git garbage collection, repo cleanup, etc.).

---

## 14. CLI Commands

### Location: `lib/Command/`

**Purpose:** Command-line administration tools (occ commands).

#### Key Files:

##### `lib/Command/Create.php`
**Command:** `occ groupfolders:create <mount_point>`
Creates a new team folder from CLI.

##### `lib/Command/Delete.php`
**Command:** `occ groupfolders:delete <folder_id>`
Deletes a team folder.

##### `lib/Command/Rename.php`
**Command:** `occ groupfolders:rename <folder_id> <new_name>`
Renames folder mount point.

##### `lib/Command/Scan.php`
**Command:** `occ groupfolders:scan <folder_id>`
**Lines:** ~150+ lines
**Significance:** 🟡 Important - File scan

Scans folder for file changes:
- Detects new/modified/deleted files
- Updates file cache
- Recalculates sizes
- Shows progress output

##### `lib/Command/Quota.php`
**Command:** `occ groupfolders:quota <folder_id> <quota>`
Sets storage quota for folder.

##### `lib/Command/Group.php`
**Command:** `occ groupfolders:group <folder_id> <group> [permissions]`
Manages group assignments and permissions.

##### `lib/Command/ACL.php`
**Command:** `occ groupfolders:acl <folder_id> [--enable|--disable]`
Enables/disables ACL for folder.

##### `lib/Command/ListCommand.php`
**Command:** `occ groupfolders:list`
Lists all folders with metadata.

##### `lib/Command/ExpireGroupVersions.php`
**Command:** `occ groupfolders:expire:versions`
Manually triggers version expiration.

##### `lib/Command/ExpireGroupTrash.php`
**Command:** `occ groupfolders:expire:trash`
Manually triggers trash expiration.

##### `lib/Command/ExpireGroupVersionsTrash.php`
**Command:** `occ groupfolders:expire:all`
Expires both versions and trash.

##### `lib/Command/FolderCommand.php`
**Lines:** ~100+ lines
Base class for folder commands (shared utilities).

**Usage Pattern:**
```bash
occ groupfolders:create "Engineering Team"
occ groupfolders:group 1 engineering 31  # Read+Write+Delete+Share
occ groupfolders:quota 1 50GB
occ groupfolders:scan 1
```

---

## 15. Database Layer

### Location: `lib/Migration/`

**Purpose:** Database schema management and migrations.

#### Migration Files:

Migrations are versioned PHP classes that modify database schema:

- **`Version101000Date...php`** - Initial schema
- **`Version400000Date...php`** - Add ACL support
- **`Version500000Date...php`** - Add circle support
- **`Version600000Date...php`** - Add trash metadata
- **`Version700000Date...php`** - Add version metadata
- **Version801000Date...php** - Add delegation tables
- **Version1201000Date...php** - Add user mapping
- Many more incremental migrations...

Each migration:
- Extends `SimpleMigrationStep`
- Implements `changeSchema()` method
- Uses Doctrine Schema API
- Creates/modifies tables, columns, indexes

#### Database Schema:

**Main Tables:**

1. **`group_folders`** - Folder metadata
   - Columns: `folder_id`, `mount_point`, `quota`, `size`, `acl`
   - Primary key: `folder_id`

2. **`group_folders_groups`** - Group/circle mappings
   - Columns: `folder_id`, `group_id`, `circle_id`, `permissions`
   - Maps folders to groups/circles with permission bitmask

3. **`group_folders_acl`** - ACL rules
   - Columns: `rule_id`, `folder_id`, `fileid`, `path`, `mapping_type`, `mapping_id`, `mask`, `permissions`
   - Stores fine-grained ACL rules

4. **`group_folders_trash`** - Trash items
   - Columns: `trash_id`, `folder_id`, `name`, `original_location`, `deleted_time`
   - Tracks deleted files

5. **`group_folders_versions`** - File versions
   - Columns: `id`, `file_id`, `timestamp`, `size`, `metadata`
   - Stores version history

6. **`group_folders_manage`** - ACL managers
   - Columns: `folder_id`, `mapping_type`, `mapping_id`
   - Users/groups who can manage ACL

7. **`group_folders_applicable`** - (Deprecated, merged into groups table)

8. **`group_folders_delegation_groups`** - Delegated admin groups

9. **`group_folders_delegation_circles`** - Delegated admin circles

10. **`group_folders_user_mapping`** - User identity mappings for ACL

#### Repair Steps:

##### `lib/Migration/WrongDefaultQuotaRepairStep.php`
Repair step that fixes incorrect quota values from earlier versions.

---

## 16. Testing Infrastructure

### Status: ❌ REMOVED

**Removed in refactoring** - All testing infrastructure has been completely removed to streamline the codebase. Tests will need to be rebuilt from scratch as the app is redesigned for Git/Git-Annex integration.

**What was removed:**

### Unit Tests (PHPUnit):
- Entire `tests/` directory (135 files deleted)
- All test cases for ACL, Folder Manager, Listeners, Trash, etc.
- 80+ stub files mocking Nextcloud core, Circles, Files app
- `tests/phpunit.xml` configuration
- `tests/psalm-baseline.xml`

### End-to-End Tests (Cypress):
- Entire `cypress/` directory
- E2E tests for groupfolders, sharing, encryption
- Version management test suites (7 test files)
- Test utilities and support files
- `cypress.config.ts` configuration
- `cypress/docker-compose.yml`

**Current state:** No test infrastructure. Will need to be rebuilt for the new Repositories app functionality.

---

## 17. Build & CI/CD

### Status: ⚠️ PARTIALLY REMOVED

**GitHub Actions workflows removed** - All 23 GitHub Actions workflow files have been deleted. These will need to be recreated from scratch for the Repositories app.

**What was removed:**
- All `.github/workflows/*.yml` files (23 workflows):
  - 6 PHPUnit test workflows (sqlite, pgsql, mysql, oci, sharding, s3)
  - 2 frontend test workflows (node, cypress)
  - 6 code quality workflows (php-cs, eslint, stylelint, psalm, reuse, info-xml)
  - 1 OpenAPI workflow
  - 1 App Store publishing workflow
  - 7 automation workflows (dependabot, npm-audit, pr-feedback, etc.)

**What remains:**
- Build configuration files (still present):
  - `webpack.js` - Frontend bundling
  - `tsconfig.json` - TypeScript configuration
  - `babel.config.js` / `.babelrc.js` - Transpiler config
  - `.eslintrc.js` - Linting rules
  - `.php-cs-fixer.dist.php` - PHP code style
  - `Makefile` - Build automation scripts
  - `krankerl.toml` - App packaging config

**Current state:** Build tools remain, but all CI/CD automation removed. Will need new workflows for the Repositories app.

---

## 18. Localization

### Status: ⚠️ REDUCED TO GERMAN ONLY

**Removed in refactoring** - Only German localization files have been kept. All other language files and translation infrastructure removed.

**What was removed:**
- 90+ language files (all `.js` and `.json` files except `de.js` and `de.json`)
- Languages removed include: English (GB), French, Spanish, Italian, Japanese, Chinese, Russian, etc.
- `.tx/config` - Transifex project configuration
- `.l10nignore` - Translation exclusion rules
- Translation workflow automation

**What remains:**
- `l10n/de.js` and `l10n/de.json` - German translations only
- `translationfiles/` directory (if present)

**Current state:** Single language support (German). Multi-language support will need to be added back later if needed for the Repositories app.

---

## 19. Configuration Files

### Root-Level Configuration:

| File | Purpose | Category |
|------|---------|----------|
| **package.json** | Node.js dependencies, build scripts | Build |
| **composer.json** | PHP dependencies | Backend |
| **webpack.js** | Frontend bundling | Build |
| **tsconfig.json** | TypeScript compilation | Build |
| **babel.config.js** | JavaScript transpilation | Build |
| **.eslintrc.js** | JavaScript linting | Code Quality |
| **.php-cs-fixer.dist.php** | PHP code style | Code Quality |
| **psalm.xml** | Static analysis | Code Quality |
| **Makefile** | Build automation | Build |
| **cypress.config.ts** | E2E testing | Testing |
| **openapi.json** | API specification | Documentation |
| **krankerl.toml** | App packaging | Deployment |
| **.nextcloudignore** | Files excluded from app package | Deployment |
| **.gitignore** | Git ignore rules | VCS |
| **LICENSES/** | License files (AGPL-3.0, etc.) | Legal |
| **.reuse/** | REUSE compliance | Legal |

---

## 20. Key Architecture Patterns

### 20.1 Layered Architecture

```
┌─────────────────────────────────────────┐
│     Presentation Layer                  │
│  (React UI, Vue Components, WebDAV)     │
├─────────────────────────────────────────┤
│     API Layer                           │
│  (FolderController, DelegationController)│
├─────────────────────────────────────────┤
│     Service Layer                       │
│  (FolderManager, DelegationService)     │
├─────────────────────────────────────────┤
│     Domain Layer                        │
│  (ACLManager, VersionsBackend, Trash)   │
├─────────────────────────────────────────┤
│     Data Layer                          │
│  (Database, Migrations, Mappers)        │
├─────────────────────────────────────────┤
│     Integration Layer                   │
│  (MountProvider, Storage Wrappers)      │
└─────────────────────────────────────────┘
```

### 20.2 Design Patterns Used

#### Wrapper Pattern
**Usage:** Storage wrappers for ACL, permission masks, encryption
**Files:**
- `lib/ACL/ACLStorageWrapper.php`
- `lib/Mount/RootPermissionsMask.php`
- `lib/Mount/GroupFolderStorage.php`

**Benefit:** Composable functionality layers without modifying core storage

#### Factory Pattern
**Usage:** Creating per-user instances
**Files:**
- `lib/ACL/ACLManagerFactory.php`
- `lib/Mount/FolderStorageManager.php`

**Benefit:** Encapsulates object creation logic

#### Backend Pattern
**Usage:** Integrating with Nextcloud subsystems
**Files:**
- `lib/Versions/VersionsBackend.php` (implements IVersionBackend)
- `lib/Trash/TrashBackend.php` (implements ITrashBackend)

**Benefit:** Standardized integration with Nextcloud core features

#### Event-Driven Pattern
**Usage:** Decoupled reactions to system events
**Files:**
- `lib/Listeners/*Listener.php`
- `lib/Event/*Event.php`

**Benefit:** Extensibility without tight coupling

#### Repository Pattern (Implicit)
**Usage:** Database abstraction
**Files:**
- `lib/ACL/RuleManager.php`
- `lib/Versions/GroupVersionsMapper.php`

**Benefit:** Separation of business logic from data access

### 20.3 Integration Points

#### Files App
- **Mount Provider:** `lib/Mount/MountProvider.php`
- **Frontend View:** `src/init.ts`
- **File Actions:** `src/actions/openGroupfolderAction.ts`
- **Sidebar:** `src/components/SharingSidebarView.vue`

#### Circles App
- **Folder Mappings:** Circle IDs in `group_folders_groups` table
- **Permission Calculation:** `lib/Folder/FolderManager.php`
- **Event Handling:** `lib/Listeners/CircleDestroyedEventListener.php`

#### Versions App
- **Backend Implementation:** `lib/Versions/VersionsBackend.php`
- **Registration:** `appinfo/info.xml` (versions backend)

#### Trash App
- **Backend Implementation:** `lib/Trash/TrashBackend.php`
- **Registration:** `appinfo/info.xml` (trash backend)

#### Sharing App
- **Script Loading:** `lib/Listeners/LoadAdditionalScriptsListener.php`
- **Sidebar Integration:** `src/components/SharingSidebarView.vue`

#### Settings App
- **Admin Panel:** `lib/Settings/Admin.php`
- **React UI:** `src/settings/App.tsx`

#### WebDAV/DAV
- **Root Collection:** `lib/DAV/RootCollection.php`
- **Plugin Registration:** `appinfo/info.xml`
- **ACL Plugin:** `lib/DAV/ACLPlugin.php`

### 20.4 Dependency Injection

**Container Configuration:** `lib/AppInfo/Application.php`

Example registrations:
```php
// ACL Manager Factory
$container->registerService(ACLManagerFactory::class, ...);

// Mount Provider
$container->registerService(MountProvider::class, ...);

// Folder Manager
$container->registerService(FolderManager::class, ...);
```

**Usage:** Constructor injection throughout codebase
```php
class FolderController extends OCSController {
    public function __construct(
        private FolderManager $manager,
        private DelegationService $delegation,
        // ...
    ) {}
}
```

### 20.5 Security Architecture

#### Permission Layers:
1. **Group-Level Permissions:** Assigned when group added to folder
2. **ACL Rules:** Fine-grained per-file/folder permissions
3. **Permission Masks:** Applied via storage wrappers
4. **Delegation:** Separate admin/subadmin authorization

#### Permission Enforcement Points:
- **Storage Layer:** `ACLStorageWrapper`, `RootPermissionsMask`
- **API Layer:** `FolderController` checks delegation
- **WebDAV Layer:** `ACLPlugin` applies rules to DAV requests
- **Cache Layer:** `ACLCacheWrapper` filters listings

#### Authorization Flow:
```
User Request
    ↓
Delegation Check (admin/subadmin?)
    ↓
Group Membership Check
    ↓
ACL Rule Evaluation
    ↓
Permission Mask Application
    ↓
Storage Operation
```

### 20.6 Caching Strategy

**ACL Cache:** `lib/ACL/ACLManager.php`
- Uses `CappedMemoryCache`
- Caches rule evaluations per request
- Invalidated on file rename/move

**Folder Cache:** `lib/Mount/MountProvider.php`
- Caches folder list per user
- Reduces database queries

**Storage Cache:** Nextcloud's built-in cache
- File metadata caching
- Wrapped by `ACLCacheWrapper`

**Frontend Cache:** Browser caching
- Static assets (JavaScript/CSS)
- Versioned by build hash

---

## Summary

This codebase implements a comprehensive team folder system for Nextcloud with:

- **Backend:** 97 PHP files (~9,236 lines) providing folder management, ACL, versioning, trash, WebDAV
- **Frontend:** 148 TypeScript/React/Vue files for admin UI and Files app integration
- **Testing:** 40+ PHPUnit tests + 7 Cypress E2E test suites
- **CI/CD:** 25+ GitHub Actions workflows testing 6 database configurations
- **Localization:** 60+ languages supported
- **Integrations:** Files app, Circles, Versions, Trash, Sharing, Settings, WebDAV

**Key Strengths:**
- Modular architecture with clear separation of concerns
- Extensive test coverage (unit + E2E)
- Strong integration with Nextcloud ecosystem
- Fine-grained permission system (group + ACL)
- Robust CI/CD pipeline
- Multi-language support

**For Fork Adaptation (Git/Git-Annex/Datalad):**

The most critical areas to modify would be:

1. **Storage Layer** (`lib/Mount/GroupFolderStorage.php`) - Integrate Git-Annex special remote
2. **Version System** (`lib/Versions/`) - Integrate with Git history
3. **Metadata** (`lib/DAV/PropFindPlugin.php`) - Expose Git commit info
4. **File Operations** (`lib/ACL/ACLStorageWrapper.php`) - Trigger Git commits on save
5. **Admin UI** (`src/settings/App.tsx`) - Add Git repo configuration
6. **Files UI** (`src/init.ts`) - Show Git history in sidebar

The architecture's use of wrappers and backends makes it well-suited for extending with Git integration without major refactoring.
