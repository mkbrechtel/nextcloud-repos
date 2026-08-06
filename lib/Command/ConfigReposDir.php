<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Command;

use OC\Core\Command\Base;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Configure the filesystem directory where Git repositories are stored
 */
class ConfigReposDir extends Base {
	public function __construct(
		private IConfig $config,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('repos:config:repos-dir')
			->setDescription('Get or set the filesystem directory for Git repositories')
			->addArgument(
				'directory',
				InputArgument::OPTIONAL,
				'The absolute path to the directory where Git repositories will be stored'
			);
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$directory = $input->getArgument('directory');

		if ($directory === null) {
			// Get current value
			$currentDir = $this->config->getAppValue('repos', 'repos_directory', '');
			if ($currentDir === '') {
				$output->writeln('<info>No repos directory configured yet.</info>');
				$output->writeln('<comment>Set it with: occ repos:config:repos-dir /path/to/repos</comment>');
				return 1;
			}
			$output->writeln('<info>Current repos directory: ' . $currentDir . '</info>');
			return 0;
		}

		// Set new value
		$directory = rtrim($directory, '/');

		// Validate directory exists or can be created
		if (!is_dir($directory)) {
			$output->writeln('<comment>Directory does not exist, attempting to create: ' . $directory . '</comment>');
			if (!mkdir($directory, 0755, true)) {
				$output->writeln('<error>Failed to create directory: ' . $directory . '</error>');
				return 1;
			}
			$output->writeln('<info>Directory created successfully.</info>');
		}

		// Check if directory is writable
		if (!is_writable($directory)) {
			$output->writeln('<error>Directory is not writable: ' . $directory . '</error>');
			return 1;
		}

		$this->config->setAppValue('repos', 'repos_directory', $directory);
		$output->writeln('<info>Repos directory set to: ' . $directory . '</info>');

		return 0;
	}
}
