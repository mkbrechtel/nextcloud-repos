<!--
SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
SPDX-License-Identifier: CC0-1.0

Sync Impact Report:
Version: 1.0.0 → 1.1.0 (Test-First Development principle added; Governance section removed)
Amended: 2025-10-26

Principles Modified:
- Added: VI. Test-First Development
- Removed: Governance section (Amendment Process, Compliance Review, Versioning)

Rationale:
- Test-first development ensures specifications drive implementation with verifiable outcomes
- Governance section was administrative overhead; constitutional amendments follow standard MR workflow
- Test references to specs provide traceability from requirements through implementation

Templates Status:
✅ plan-template.md - Constitution Check section present
✅ spec-template.md - Requirements alignment compatible; test-first compatible
✅ tasks-template.md - Already includes test tasks with NOTE about writing tests first
⚠ No additional template updates needed; test-first is already reflected in tasks template

Follow-up Items:
- Ensure test files include spec references in comments or metadata
- Verify tests are written and fail before implementation begins
- Maintain REUSE compliance for all test files
-->

# Nextcloud Repositories Constitution

## Core Principles

### I. REUSE Standard Compliance

All development artifacts MUST be marked as open source using the REUSE License Standard (https://reuse.software/) to ensure proper copyright attribution and license clarity.

**Requirements**:
- Every file MUST include SPDX-FileCopyrightText with year and author information
- Every file MUST include SPDX-License-Identifier declaring its license
- When using or deriving work from other authors, ALL original copyright holders MUST be preserved and attributed
- Third-party code or assets MUST retain original copyright notices and license identifiers
- License files MUST be present in the LICENSES/ directory for all referenced licenses
- Use CC0-1.0 for documentation and issue tracking files
- Use AGPL-3.0-or-later for application source code unless otherwise specified
- When forking or deriving from existing projects (e.g., Team Folders app), original copyright and authorship MUST be maintained
- Contributors MUST NOT remove or modify existing copyright notices without permission

**Rationale**: REUSE compliance is fundamentally about respecting intellectual property and ensuring proper attribution to all contributors and copyright holders. When we build upon others' work, we have both legal and ethical obligations to acknowledge their contributions. The machine-readable format enables compliance automation, but the core purpose is to maintain an accurate record of who created what, when they created it, and under what terms it can be used. This protects both the project and individual contributors.

### II. Containerized Development

All builds, development environments, and execution environments MUST happen in container images using Podman.

**Requirements**:
- Development environment MUST consist of two files:
  - **Containerfile**: Contains actual build instructions and environment setup
  - **Makefile**: Orchestrates environment creation with `podman build` and executes operations based on current git repository state
- No local dependency installation required beyond Podman
- All build artifacts MUST be reproducible within containers
- Container images MUST be versioned and documented

**Rationale**: Containerization ensures consistent development and build environments across all contributors, eliminates "works on my machine" issues, and provides isolation for testing. Using Podman aligns with rootless container security practices. The two-file constraint keeps the containerization simple and maintainable.

### III. Issue Tracking in Markdown

Issue tracking happens in the `issues/` folder as markdown files, not in external issue trackers.

**Requirements**:
- All issues MUST be markdown files in the `issues/` directory
- Each issue file MUST include REUSE-compliant headers (CC0-1.0)
- Issue files MUST follow the template format defined in `issues/README.md`
- Issues are submitted via pull/merge requests to the main branch
- See `issues/README.md` for detailed workflow and templates

**Rationale**: Git-based issue tracking keeps all project artifacts in one repository, enables offline work, provides full version control for issues, and avoids dependence on external platforms. This aligns with decentralized collaboration principles and ensures issues remain accessible even if hosting platforms change.

### IV. Git-Based Version Control

All development work MUST use Git with a branch-based workflow.

**Requirements**:
- Main branch is `main` (not master)
- Feature branches MUST follow naming convention: `[###-feature-name]`
- All changes MUST be submitted via merge requests
- Commit messages MUST be descriptive and reference related issues when applicable
- No force-push to main branch
- Git history MUST remain clean and meaningful

**Rationale**: Git provides distributed version control essential for collaborative open source development. Branch-based workflows enable parallel development, code review, and safe integration of changes. Clean Git history serves as project documentation and enables effective debugging through history analysis.

### V. Nextcloud Integration Architecture

This project creates tight integration between Nextcloud and Git/Git-Annex/Datalad repositories.

**Requirements**:
- Primary target: Latest Nextcloud stable release
- Development against Nextcloud packaged in Podman containers
- Source code mounted into containers for live editing
- Components MUST maintain separation:
  - **Nextcloud App** ("Nextcloud Repositories"): Forked from Team Folders, provides repository interaction in Files App
  - **Git-annex special remote**: OAuth-authenticated remote for syncing with Nextcloud
- Integration MUST support non-technical users while maintaining Git semantics
- Version history MUST be visible in Nextcloud interface

**Rationale**: The architecture enables seamless collaboration between technical and non-technical users by bridging Nextcloud's user-friendly interface with Git's powerful version control. Maintaining component separation ensures modularity and allows independent development and testing of each integration piece.

### VI. Test-First Development

Tests for intended functionality MUST be implemented before the functionality itself, and each test MUST reference the specification that caused its implementation.

**Requirements**:
- Test files MUST include references to the specification (spec.md) that defines the requirement being tested
- Tests MUST be written and MUST fail before implementation begins
- Test file headers or comments MUST include:
  - Reference to the spec file (e.g., `specs/[###-feature]/spec.md`)
  - Reference to specific user story, functional requirement, or acceptance criteria being tested
  - Reference format: `# Spec: specs/###-feature/spec.md#FR-001` or similar traceable identifier
- Implementation MUST NOT begin until tests exist and demonstrate failure
- Tests validate acceptance criteria from specifications, not implementation details
- Contract tests, integration tests, and unit tests all follow test-first discipline when requested

**Rationale**: Test-first development ensures that specifications drive implementation with verifiable outcomes. By requiring tests to reference their originating specification, we maintain complete traceability from user requirements through acceptance criteria to test validation and implementation. This creates a living documentation chain where tests serve as executable specifications, and changes to requirements are immediately visible in test expectations. Tests that fail first prove they actually validate the requirement rather than just passing by accident. This discipline prevents implementation drift from specifications and ensures all functionality has measurable acceptance criteria.

## Development Workflow

### Repository Structure

Development follows a containerized workflow with clear separation of concerns:

- **Root**: Nextcloud app source code (PHP, JavaScript)
- **git-annex-special-remote/**: Special remote implementation (Go)
- **issues/**: Markdown-based issue tracking
- **.specify/**: Feature specifications and planning artifacts
- **LICENSES/**: REUSE-compliant license files

### Development Cycle

1. **Issue Creation**: Report issues as markdown files in `issues/` folder
2. **Feature Specification**: Use `/speckit.*` commands to plan and specify features
3. **Implementation**: Work in feature branches with containerized environment
4. **Testing**: Test within containers matching target Nextcloud version
5. **Review**: Submit merge request to main branch
6. **Integration**: Merge after review and CI validation

### Containerization Workflow

All development tasks execute through the Makefile + Containerfile pattern:

```bash
# Build development container
make build

# Run development environment
make dev

# Run tests in container
make test

# Validate REUSE compliance
make reuse-lint

# Build production artifact
make package
```

**Container Build Requirements**:
- The development container MUST include the `reuse` tool for license management
- The container MUST provide test environments for each test stage in `tests/$stage/` folders
- Each test stage folder gets its own containerized test environment

## Quality Standards

### Licensing Compliance

- Use the `reuse` tool (https://reuse.software/) for all license management tasks
- Every file MUST pass `reuse lint` validation before merge
- Pull requests MUST NOT be merged if REUSE compliance fails
- Use `reuse addheader` to add copyright and license headers to files
- Use `reuse download` to fetch missing license texts
- Contributors MUST use their real name and email in copyright notices
- License identifiers MUST match files in LICENSES/ directory
- When incorporating third-party code:
  - Use `reuse annotate` or manual headers to preserve original copyright notices
  - License compatibility MUST be verified before integration
  - Derivative works MUST acknowledge all copyright holders
  - Documentation MUST list all third-party components and their licenses

### Code Quality

- PHP code MUST follow Nextcloud coding standards
- JavaScript MUST be linted and formatted consistently
- Go code MUST follow `gofmt` and standard Go conventions
- All user-facing strings MUST be internationalized

### Testing

- All tests MUST run inside containerized environments, not during container build
- Test stages are organized in `tests/$stage/` folders (e.g., `tests/unit/`, `tests/integration/`)
- Each test stage folder receives its own containerized test environment via the container
- Test environments MUST match target production Nextcloud versions
- Integration tests MUST validate component interactions
- Unit tests SHOULD provide adequate coverage for business logic
- Tests are executed via `make test` which runs tests inside the container

### Documentation

- README MUST explain project purpose, setup, and contribution workflow
- Complex features MUST include specification in `.specify/` directory
- API contracts MUST be documented when services interact
- Containerfile MUST include comments explaining non-obvious steps

## Governance

**Version**: 1.1.0 | **Ratified**: 2025-10-26 | **Last Amended**: 2025-10-26
