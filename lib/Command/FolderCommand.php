<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Command;

use OC\Core\Command\Base;
use OCA\Repos\Folder\RepoManager;
use OCA\Repos\Mount\FolderStorageManager;
use OCA\Repos\Mount\MountProvider;
use OCP\Files\IRootFolder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base command for commands asking the user for a repository id.
 */
abstract class FolderCommand extends Base {

	public function __construct(
		protected RepoManager $repoManager,
		protected IRootFolder $rootFolder,
		protected MountProvider $mountProvider,
		protected FolderStorageManager $folderStorageManager,
	) {
		parent::__construct();
	}

	protected function getFolder(InputInterface $input, OutputInterface $output): ?array {
		$folderId = (int)$input->getArgument('folder_id');
		if ((string)$folderId !== $input->getArgument('folder_id')) {
			// Protect against removing folderId === 0 when typing a string (e.g. folder name instead of folder id)
			$output->writeln('<error>Repository id argument is not an integer. Got ' . $input->getArgument('folder_id') . '</error>');

			return null;
		}

		$folder = $this->repoManager->getRepo($folderId);
		if ($folder === null) {
			$output->writeln('<error>Repository not found: ' . $folderId . '</error>');
			return null;
		}

		return $folder;
	}
}
