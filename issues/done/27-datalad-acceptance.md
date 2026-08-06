---
# SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
# SPDX-License-Identifier: CC0-1.0
---

# Issue: Datalad End-to-End Acceptance

## Category
Testing Infrastructure

## Current State
The dev container ships git-annex and datalad, but nothing exercises the full research workflow against the app.

## Plan
An automated end-to-end test in the containerized environment covering the founding user story:

1. Admin creates a repo folder; a data manager pushes a dataset with annexed files (over the webdav fallback or Nextcloud-side upload).
2. A consumer runs `datalad clone` against the Nextcloud URL and `datalad get` for annexed content — both must succeed with only an app password.
3. A non-technical user edits a text file in the Files app; the consumer pulls and sees an attributed commit.
4. A push from the consumer appears in the Files app.

Failures here define the remaining work; this issue closes when the loop runs green in CI (`make test`).
