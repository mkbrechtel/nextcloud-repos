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
		// Git smart HTTP protocol at the conventional clone URL:
		//   https://<instance>/apps/repos/<name>.git
		// The .git suffix is required and keeps these routes from shadowing
		// anything else under the app prefix.
		// Based on https://git-scm.com/docs/http-protocol
		['name' => 'git_repo#infoRefs', 'url' => '/{repo}/info/refs', 'verb' => 'GET', 'requirements' => ['repo' => '[^/]+\.git']],
		['name' => 'git_repo#uploadPack', 'url' => '/{repo}/git-upload-pack', 'verb' => 'POST', 'requirements' => ['repo' => '[^/]+\.git']],
		['name' => 'git_repo#receivePack', 'url' => '/{repo}/git-receive-pack', 'verb' => 'POST', 'requirements' => ['repo' => '[^/]+\.git']],

		// Dumb protocol pieces: HEAD, config (git-annex uuid discovery),
		// loose objects
		['name' => 'git_repo#getHead', 'url' => '/{repo}/HEAD', 'verb' => 'GET', 'requirements' => ['repo' => '[^/]+\.git']],
		['name' => 'git_repo#getConfig', 'url' => '/{repo}/config', 'verb' => 'GET', 'requirements' => ['repo' => '[^/]+\.git']],
		['name' => 'git_repo#getObject', 'url' => '/{repo}/objects/{path}', 'verb' => 'GET', 'requirements' => ['repo' => '[^/]+\.git', 'path' => '.+']],

		// Annex objects - git-annex fetches keys over the clone URL (issue 25)
		['name' => 'git_repo#annexObject', 'url' => '/{repo}/annex/objects/{path}', 'verb' => 'GET', 'requirements' => ['repo' => '[^/]+\.git', 'path' => '.+']],

		// File history for the Files app sidebar (issue 26)
		['name' => 'history#fileHistory', 'url' => '/api/history/{folderId}', 'verb' => 'GET'],

		// OAuth2 authorization entry point for git credential helpers (issue 28)
		['name' => 'oAuth#authorize', 'url' => '/oauth/authorize', 'verb' => 'GET'],
	],
];
