<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Mirian Brechtel <brechtel@med.uni-frankfurt.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Controller;

use OCA\Repos\Folder\RepoManager;
use OCA\Repos\Git\RepoGitService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Read-only file history for the Files app sidebar (issue 26).
 */
class HistoryController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly RepoManager $repoManager,
		private readonly RepoGitService $repoGitService,
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
		$folderId = $folder->id;

		$history = $this->repoGitService->getHistory($folderId, $path);

		$annex = null;
		if ($path !== '') {
			$key = $this->repoGitService->getAnnexKey($folderId, $path);
			if ($key !== null && $key !== '') {
				$annex = [
					'key' => $key,
					'present' => $this->repoGitService->getAnnexContentPath($folderId, $key) !== null,
				];
			}
		}

		return new DataResponse([
			'history' => $history,
			'annex' => $annex,
		]);
	}
}
