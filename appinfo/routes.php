<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Route definitions for the Repos app
 */
return [
	'routes' => [
		// Git HTTP protocol endpoints
		// Based on https://git-scm.com/docs/http-protocol

		// Info/refs endpoint - Initial discovery for clone/fetch/push
		['name' => 'git_repo#infoRefs', 'url' => '/repos/{repo}/info/refs', 'verb' => 'GET'],

		// git-upload-pack - Used for clone and fetch operations
		['name' => 'git_repo#uploadPack', 'url' => '/repos/{repo}/git-upload-pack', 'verb' => 'POST'],

		// git-receive-pack - Used for push operations
		['name' => 'git_repo#receivePack', 'url' => '/repos/{repo}/git-receive-pack', 'verb' => 'POST'],

		// HEAD file - Current branch reference
		['name' => 'git_repo#getHead', 'url' => '/repos/{repo}/HEAD', 'verb' => 'GET'],

		// Objects - Git object storage (for fallback to "dumb" HTTP protocol)
		['name' => 'git_repo#getObject', 'url' => '/repos/{repo}/objects/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.+']],

		// Repo config - git-annex probes this to learn the annex uuid
		['name' => 'git_repo#getConfig', 'url' => '/repos/{repo}/config', 'verb' => 'GET'],

		// Annex objects - git-annex fetches keys over the clone URL (issue 25)
		['name' => 'git_repo#annexObject', 'url' => '/repos/{repo}/annex/objects/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.+']],

		// File history for the Files app sidebar (issue 26)
		['name' => 'history#fileHistory', 'url' => '/api/history/{folderId}', 'verb' => 'GET'],

		// Short clone URLs: /apps/repos/<name>.git — the conventional form.
		// Only .git-suffixed names match, so these cannot shadow other routes.
		['name' => 'git_repo#infoRefs', 'postfix' => 'short', 'url' => '/{repo}/info/refs', 'verb' => 'GET', 'requirements' => ['repo' => '[^/]+\.git']],
		['name' => 'git_repo#uploadPack', 'postfix' => 'short', 'url' => '/{repo}/git-upload-pack', 'verb' => 'POST', 'requirements' => ['repo' => '[^/]+\.git']],
		['name' => 'git_repo#receivePack', 'postfix' => 'short', 'url' => '/{repo}/git-receive-pack', 'verb' => 'POST', 'requirements' => ['repo' => '[^/]+\.git']],
		['name' => 'git_repo#getHead', 'postfix' => 'short', 'url' => '/{repo}/HEAD', 'verb' => 'GET', 'requirements' => ['repo' => '[^/]+\.git']],
		['name' => 'git_repo#getConfig', 'postfix' => 'short', 'url' => '/{repo}/config', 'verb' => 'GET', 'requirements' => ['repo' => '[^/]+\.git']],
		['name' => 'git_repo#getObject', 'postfix' => 'short', 'url' => '/{repo}/objects/{path}', 'verb' => 'GET', 'requirements' => ['repo' => '[^/]+\.git', 'path' => '.+']],
		['name' => 'git_repo#annexObject', 'postfix' => 'short', 'url' => '/{repo}/annex/objects/{path}', 'verb' => 'GET', 'requirements' => ['repo' => '[^/]+\.git', 'path' => '.+']],
	],
];
