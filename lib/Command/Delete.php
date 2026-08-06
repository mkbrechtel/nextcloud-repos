<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Command;

use OCA\Repos\Folder\FolderDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Delete extends FolderCommand {
	protected function configure(): void {
		$this
			->setName('repos:delete')
			->setDescription('Delete repository')
			->addArgument('folder_id', InputArgument::REQUIRED, 'Id of the repository to delete')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$folder = $this->getFolder($input, $output);
		if ($folder === null) {
			return -1;
		}

		$helper = $this->getHelper('question');
		$question = new ConfirmationQuestion('Are you sure you want to delete the repository ' . $folder['mount_point'] . ' and all files within, this cannot be undone (y/N).', false);
		if ($input->getOption('force') || $helper->ask($input, $output, $question)) {
			// Convert array to FolderDefinition for deleteStoragesForFolder
			$folderDef = new FolderDefinition(
				$folder['id'],
				$folder['mount_point'],
				$folder['quota'],
				$folder['acl'],
				$folder['storage_id'],
				$folder['root_id'],
				$folder['options']
			);
			$this->folderStorageManager->deleteStoragesForFolder($folderDef);
			$this->repoManager->deleteRepo($folder['id']);
		}

		return 0;
	}
}
