<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Rename extends FolderCommand {
	protected function configure(): void {
		$this
			->setName('groupfolders:rename')
			->setDescription('Rename Team folder')
			->addArgument('folder_id', InputArgument::REQUIRED, 'Id of the folder to rename')
			->addArgument('name', InputArgument::REQUIRED, 'New value name of the folder');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$folder = $this->getFolder($input, $output);
		if ($folder === null) {
			return -1;
		}

		// Check if the new name is valid
		$name = trim($input->getArgument('name'));
		if (empty($name)) {
			$output->writeln('<error>Folder name cannot be empty</error>');
			return 1;
		}

		// Check if the name actually changed
		if ($folder->mountPoint === $name) {
			$output->writeln('The name is already set to ' . $name);
			return 0;
		}

		// Check if mount point already exists
		$folders = $this->folderManager->getAllFolders();
		foreach ($folders as $existingFolder) {
			if ($existingFolder->mountPoint === $name) {
				$output->writeln('<error>A Folder with the name ' . $name . ' already exists</error>');
				return 1;
			}
		}

		$this->folderManager->renameFolder($folder->id, $input->getArgument('name'));

		return 0;
	}
}
