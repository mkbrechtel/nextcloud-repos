---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Localization - Keep Only German Translations

## Category
Localization

## Files Affected
- `l10n/` - 60+ language JSON files
  - `de.json` - German (KEEP)
  - `de.js` - German (KEEP)
  - All other language files (REMOVE)
- `.tx/config` - Transifex configuration (REMOVE)
- `.l10nignore` - Translation ignore rules (REMOVE)
- `translationfiles/` - Translation templates (REMOVE)

## Current State
Multi-language support:
- 60+ languages supported
- Transifex integration for translations
- Translation workflow automation
- Complete i18n infrastructure

## Plan
**Keep only German translations:**

- **Keep:** `l10n/de.json` and `l10n/de.js`
- **Remove:** All other language files
- **Remove:** Transifex configuration (`.tx/config`)
- **Remove:** Translation templates (`translationfiles/`)
- **Remove:** `.l10nignore`
- **Later:** Can add more languages if needed

### Implementation Strategy
- Remove non-German language files during refactoring
- Keep German translation files
- Remove Transifex integration
- Don't worry about implementation functionality now, we are in the refactoring stage
- Translation strings will need updating for repository terminology

## Status
✅ Implemented in commit 3cabee7a on 2025-10-24
