---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Testing Infrastructure - Remove Completely

## Category
Testing Infrastructure

## Files Affected
- `tests/` - All PHPUnit tests
  - `tests/ACL/` - ACL system tests
  - `tests/Folder/` - Folder manager tests
  - `tests/Listeners/` - Event listener tests
  - `tests/Trash/` - Trash functionality tests
  - `tests/AppInfo/` - Capabilities tests
  - `tests/stubs/` - 80+ stub files
- `cypress/` - All Cypress E2E tests
  - `cypress/e2e/groupfolders.cy.ts` - Main E2E tests
  - `cypress/e2e/sharing.cy.ts` - Sharing tests
  - `cypress/e2e/encryption.cy.ts` - Encryption tests
  - `cypress/e2e/files_versions/` - Version tests
  - `cypress/support/` - Test utilities
  - `cypress/docker-compose.yml` - Test environment

## Current State
Comprehensive test coverage:
- 40+ PHPUnit unit tests
- 7 Cypress E2E test suites
- Test stubs for dependencies
- Docker-based test environment

## Plan
**Remove completely and rewrite later:**

- Remove entire `tests/` directory
- Remove entire `cypress/` directory
- **Later: Write new tests for repository functionality**
  - Unit tests for Git operations
  - Integration tests for Git-Annex
  - E2E tests for repository workflows
  - Different test scenarios entirely

### Implementation Strategy
- Remove test directories during refactoring
- Don't worry about implementation functionality now, we are in the refactoring stage
- New test suite design and implementation TBD

## Status
✅ Implemented in commit fb21c322 on 2025-10-24
