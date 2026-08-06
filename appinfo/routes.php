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
	],
];
