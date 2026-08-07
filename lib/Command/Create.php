<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Command;

use OC\Core\Command\Base;
use OCA\Repos\Folder\RepoManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Create extends Base {
	public function __construct(
		private readonly RepoManager $repoManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('repos:create')
			->setDescription('Create a new repository')
			->addArgument('name', InputArgument::REQUIRED, 'Name or mount point of the new repository')
			->addOption('bucket', null, InputOption::VALUE_REQUIRED, 'Overwrite the bucket used for the new repository');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$name = trim($input->getArgument('name'));

		// Check if the repository name is valid
		if (empty($name)) {
			$output->writeln('<error>Repository name cannot be empty</error>');
			return 1;
		}

		// Check if mount point already exists
		$repos = $this->repoManager->getAllRepos();
		foreach ($repos as $repo) {
			if ($repo['mount_point'] === $name) {
				$output->writeln('<error>A repository with the name ' . $name . ' already exists</error>');
				return 1;
			}
		}

		$options = [];
		if ($bucket = $input->getOption('bucket')) {
			$options['bucket'] = $bucket;
		}

		$id = $this->repoManager->createRepo($name, $options);
		$output->writeln((string)$id);

		return 0;
	}
}
