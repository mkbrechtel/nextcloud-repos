---
title: Live demo
description: A recorded walkthrough and a live demo instance you can try.
---

## Recorded walkthrough

The video below is produced automatically by the project's demo recorder
(`test/demo/`) against a real Nextcloud instance — a Debian container driving
Chromium and a web terminal, screen-recorded end to end. What you see is the
actual software doing the actual thing.

<video controls style="max-width:100%">
	<source src="/demo.webm" type="video/webm" />
	Your browser does not support the video tag.
</video>

The walkthrough covers:

1. A repository folder in the Nextcloud Files app
2. Cloning it over HTTPS in a terminal
3. Pushing a commit — and watching it appear in the Files app
4. Editing a file in the browser — and pulling the attributed commit
5. Retrieving annexed data with `git annex get` over the same URL

## Try it yourself

A live demo instance runs at
[nextcloud-repos-demo.devio.mkbrechtel.dev](https://nextcloud-repos-demo.devio.mkbrechtel.dev).

```bash
git clone https://nextcloud-repos-demo.devio.mkbrechtel.dev/apps/repos/repos/demo
cd demo
git annex get .
```

Ask the maintainer for demo credentials.
