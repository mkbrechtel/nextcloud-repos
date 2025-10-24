<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Command;

use OC\Core\Command\Base;
use OCA\Repos\Folder\RepoManager;
use OCP\Constants;
use OCP\IGroupManager;
use OCP\IUserManager;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Base {
	/** @var array<int,string> */
	public const PERMISSION_NAMES = [
		Constants::PERMISSION_READ => 'read',
		Constants::PERMISSION_UPDATE => 'write',
		Constants::PERMISSION_SHARE => 'share',
		Constants::PERMISSION_DELETE => 'delete'
	];


	public function __construct(
		private readonly RepoManager $repoManager,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('repos:list')
			->setDescription('List the configured repositories')
			->addOption('user', 'u', InputArgument::OPTIONAL, 'List repositories applicable for a user');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userId = $input->getOption('user');
		$groups = $this->groupManager->search('');
		$groupNames = [];
		foreach ($groups as $group) {
			$groupNames[$group->getGID()] = $group->getDisplayName();
		}

		// Get all repos (user filtering not yet implemented)
		$repos = $this->repoManager->getAllRepos();

		// Filter by user groups if specified
		if ($userId) {
			$user = $this->userManager->get($userId);
			if (!$user) {
				$output->writeln("<error>user $userId not found</error>");
				return 1;
			}

			$userGroupIds = $this->groupManager->getUserGroupIds($user);
			$repos = array_filter($repos, function ($repo) use ($userGroupIds) {
				foreach ($repo['groups'] as $groupId => $group) {
					if (in_array($groupId, $userGroupIds)) {
						return true;
					}
				}
				return false;
			});
		}

		$outputType = $input->getOption('output');
		if (count($repos) === 0) {
			if ($outputType === self::OUTPUT_FORMAT_JSON || $outputType === self::OUTPUT_FORMAT_JSON_PRETTY) {
				$output->writeln('[]');
			} else {
				$output->writeln('<info>No repositories configured</info>');
			}

			return 0;
		}

		if ($outputType === self::OUTPUT_FORMAT_JSON || $outputType === self::OUTPUT_FORMAT_JSON_PRETTY) {
			$this->writeArrayInOutputFormat($input, $output, $repos);
		} else {
			$table = new Table($output);
			$table->setHeaders(['Repo Id', 'Name', 'Groups', 'Quota', 'Advanced Permissions', 'Manage advanced permissions']);
			$table->setRows(array_map(function (array $repo) use ($groupNames): array {
				$formatted = ['id' => $repo['id'], 'name' => $repo['mount_point']];
				$formatted['quota'] = ($repo['quota'] > 0) ? \OCP\Util::humanFileSize($repo['quota']) : 'Unlimited';
				$groupStrings = array_map(function (string $groupId, array $entry) use ($groupNames): string {
					$permissions = $entry['permissions'];
					$displayName = $entry['displayName'];
					$groupName = array_key_exists($groupId, $groupNames) && ($groupNames[$groupId] !== $groupId) ? $groupNames[$groupId] . ' (' . $groupId . ')' : $displayName;

					return $groupName . ': ' . $this->permissionsToString($permissions);
				}, array_keys($repo['groups']), array_values($repo['groups']));
				$formatted['groups'] = implode("\n", $groupStrings);
				$formatted['acl'] = $repo['acl'] ? 'Enabled' : 'Disabled';
				$manageStrings = array_map(fn (array $manage): string => ($manage['mapping_id'] ?? '') . ' (' . ($manage['mapping_type'] ?? '') . ')', $repo['manage']);
				$formatted['manage'] = implode("\n", $manageStrings);

				return $formatted;
			}, $repos));
			$table->render();
		}

		return 0;
	}

	private function permissionsToString(int $permissions): string {
		if ($permissions === 0) {
			return 'none';
		}

		return implode(', ', array_filter(self::PERMISSION_NAMES, fn (int $possiblePermission): int => $possiblePermission & $permissions, ARRAY_FILTER_USE_KEY));
	}
}
