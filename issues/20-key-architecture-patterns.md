---
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Key Architecture Patterns - Postpone Decisions

## Category
Key Architecture Patterns

## Current State
Team folders app uses several architectural patterns:
- **Layered Architecture:** Presentation → API → Service → Domain → Data → Integration
- **Design Patterns:** Wrapper, Factory, Backend, Event-Driven, Repository
- **Integration Points:** Files app, Circles, Versions, Trash, Sharing, Settings, WebDAV
- **Dependency Injection:** Container configuration in Application.php
- **Security Architecture:** Multi-layer permissions (Group → ACL → Masks)
- **Caching Strategy:** ACL cache, folder cache, storage cache

## Plan
**Postpone architectural decisions until after refactoring:**

- Don't make major architectural decisions now
- Complete the refactoring first (namespace changes, file cleanup, etc.)
- **After refactoring:** Evaluate which patterns to keep/change/add:
  - Keep wrapper pattern for storage? (likely yes for Git-Annex integration)
  - Keep factory pattern? (probably yes)
  - Backend pattern for Git operations?
  - How to structure Git/Git-Annex integration?
  - Permission architecture with colleague's ideas
  - Caching strategy for Git operations

### Implementation Strategy
- No action during refactoring stage
- Focus on structural cleanup first
- Architectural decisions come after refactoring is complete
- Document decisions as they're made
