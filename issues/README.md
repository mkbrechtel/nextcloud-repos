<!--
SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
SPDX-License-Identifier: CC0-1.0
-->

# Issue Reporting

This repository tracks issues as markdown files in this folder.

## How to Report an Issue

### Quick Start (Using Web Editor)

Click a link that opens a pre-filled template:

- [Create New Issue on Codeberg](https://codeberg.org/mkbrechtel/nextcloud-repos/_new/main/issues?filename=my-issue.md&value=---%0A%23%20SPDX-FileCopyrightText%3A%202025%20Your%20Name%20%3Cyour.email%40example.org%3E%0A%23%20SPDX-License-Identifier%3A%20CC0-1.0%0A---%0A%0A%23%20Issue%3A%20Issue%20Title%0A%0ADescribe%20the%20issue%20in%20detail.%0A)
- [Create New Issue on GitHub](https://github.com/mkbrechtel/nextcloud-repos/new/main/issues?filename=my-issue.md&value=---%0A%23%20SPDX-FileCopyrightText%3A%202025%20Your%20Name%20%3Cyour.email%40example.org%3E%0A%23%20SPDX-License-Identifier%3A%20CC0-1.0%0A---%0A%0A%23%20Issue%3A%20Issue%20Title%0A%0ADescribe%20the%20issue%20in%20detail.%0A)

### Manual Process

1. Create a markdown file in the `issues` folder (name it something like `my-issue.md`)
2. Use this template (copyright notice with CC0-1.0 is **required** for merge requests):
   ```markdown
   ---
   # SPDX-FileCopyrightText: YEAR Your Name <your.email@example.org>
   # SPDX-License-Identifier: CC0-1.0
   ---

   # Issue: Your Issue Title

   Describe the issue in detail.
   ```
3. Create a merge request to the `main` branch

