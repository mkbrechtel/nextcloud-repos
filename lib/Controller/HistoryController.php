<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Controller;

use OCA\Repos\Folder\RepoManager;
use OCA\Repos\Git\Native\NativeGitService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Read-only file history for the Files app sidebar (issue 26), served from
 * the native backend's commit walk and annex state.
 */
class HistoryController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly RepoManager $repoManager,
		private readonly NativeGitService $nativeGit,
		private readonly IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param string $folderId numeric folder id or the repo's mount point name
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function fileHistory(string $folderId, string $path = ''): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
		}
		if (str_contains($path, '..')) {
			return new DataResponse(['error' => 'Invalid path'], Http::STATUS_BAD_REQUEST);
		}
		if (str_ends_with($folderId, '.git')) {
			$folderId = substr($folderId, 0, -4);
		}
		$folder = null;
		foreach ($this->repoManager->getFoldersForUser($user) as $candidate) {
			if ((string)$candidate->id === $folderId || $candidate->mountPoint === $folderId) {
				$folder = $candidate;
				break;
			}
		}
		if ($folder === null) {
			return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
		}

		return new DataResponse([
			'history' => $this->nativeGit->history($folder->id, $path),
			'annex' => $path !== '' ? $this->nativeGit->annexInfo($folder->id, $path) : null,
		]);
	}
}
