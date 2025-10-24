---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Build & CI/CD - Remove Completely

## Category
Build & CI/CD

## Files Affected

### GitHub Actions Workflows (`.github/workflows/`):
- All PHPUnit workflows (sqlite, pgsql, mysql, oci, sharding, s3)
- Frontend testing (node.yml, cypress.yml)
- Linting workflows (php, php-cs, eslint, stylelint, info-xml, psalm, reuse)
- API & Documentation (openapi.yml)
- Deployment (appstore-build-publish.yml)
- Automation workflows (update-nextcloud-ocp, pr-feedback, npm-audit-fix, fixup, dependabot)

### Build Configuration:
- `webpack.js` - Frontend bundling
- `tsconfig.json` - TypeScript config
- `babel.config.js` / `.babelrc.js` - Transpiler config
- `.eslintrc.js` - Linting config
- `.php-cs-fixer.dist.php` - PHP code style
- `psalm.xml` - Static analysis
- `Makefile` - Build automation
- `krankerl.toml` - App packaging
- `cypress.config.ts` - E2E testing config

## Current State
Comprehensive CI/CD with:
- 25+ GitHub Actions workflows
- Testing across 6 database configurations
- Multiple linters and code quality tools
- Automated deployment to app store
- Build tools for frontend

## Plan
**Remove completely and rebuild later:**

- Remove entire `.github/workflows/` directory
- Remove or keep build configs minimal (webpack, tsconfig may be needed for frontend)
- **Later: Create new CI/CD for repository app**
  - Simpler workflow initially
  - Focus on essential tests
  - Add workflows as needed

### Implementation Strategy
- Remove CI/CD workflows during refactoring
- Keep minimal build tools if frontend development needed
- Don't worry about implementation functionality now, we are in the refactoring stage
- New CI/CD design and implementation TBD
