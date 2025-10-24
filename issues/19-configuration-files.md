---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Configuration Files - Clean Up and Simplify

## Category
Configuration Files

## Files Affected

### Remove:
- `package-lock.json` / `composer.lock` - Lock files
- `vendor-bin/` - Vendor binary dependencies
- Unneeded dependencies in `package.json` and `composer.json`

### Keep (with cleanup):
- `package.json` - Node.js dependencies (remove unused)
- `composer.json` - PHP dependencies (remove unused)
- `webpack.js` - Frontend bundling (might need for frontend work)
- `tsconfig.json` - TypeScript config (might need for frontend work)
- `babel.config.js` - Transpiler config (might need for frontend work)
- `.gitignore` - Git ignore rules (update as needed)
- `.nextcloudignore` - App packaging ignore rules (update as needed)
- `LICENSES/` - License files (keep, update as needed)
- `.reuse/` - REUSE compliance (keep)
- `Makefile` - Build automation (simplify)

### Remove or simplify:
- `.eslintrc.js` - Linting config (remove or simplify)
- `.php-cs-fixer.dist.php` - PHP code style (remove or simplify)
- `psalm.xml` - Static analysis (remove or simplify)
- `cypress.config.ts` - E2E testing (remove)
- `openapi.json` - API spec (remove or regenerate later)
- `krankerl.toml` - App packaging (keep, update)

## Current State
Full configuration for team folders app with:
- Complete dependency management
- All build tools configured
- Code quality tools
- Testing infrastructure configs

## Plan
**Clean up and simplify configuration:**

- **Remove:** Lock files (package-lock.json, composer.lock)
- **Remove:** `vendor-bin/` directory
- **Clean up:** `package.json` and `composer.json` - remove dependencies not needed for repositories app
- **Keep minimal:** Build configs needed for frontend development
- **Update:** `.gitignore` and `.nextcloudignore` for new app structure
- **Simplify:** Makefile for essential tasks only

### Implementation Strategy
- Remove unnecessary files during refactoring
- Clean up dependency lists
- Don't worry about implementation functionality now, we are in the refactoring stage
- Keep only what's essential for basic app structure and frontend build
