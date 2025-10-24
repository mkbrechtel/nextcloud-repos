---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Access Control Lists (ACL) - Keep for Now

## Category
Access Control Lists (ACL)

## Files Affected
- `lib/ACL/ACLManager.php` - Permission evaluator
- `lib/ACL/RuleManager.php` - ACL persistence
- `lib/ACL/Rule.php` - ACL rule data structure
- `lib/ACL/ACLStorageWrapper.php` - Storage integration
- `lib/ACL/ACLCacheWrapper.php` - Cache wrapper
- `lib/ACL/ACLManagerFactory.php` - Factory pattern
- `lib/ACL/UserMapping/UserMapping.php`
- `lib/ACL/UserMapping/UserMappingManager.php`

## Current State
Comprehensive ACL system providing:
- Fine-grained file/folder permissions
- Permission calculation based on user/group/circle membership
- Storage wrappers for enforcement
- Caching for performance
- User identity mapping

## Plan
**Keep the ACL system until we have a new permission system:**

- This is tricky - permissions need careful design
- Update namespace to `OCA\Repos`
- Keep all ACL infrastructure intact for now
- A colleague has ideas for a new permission system
- Will be replaced/redesigned once new permission system is designed

### Implementation Strategy
- Minimal changes during refactoring stage (namespace updates only)
- Don't worry about implementation functionality now, we are in the refactoring stage
- Keep system functional as-is until replacement is ready
- New permission system design TBD
