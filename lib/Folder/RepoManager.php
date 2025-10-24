<?php

declare(strict_types=1);

namespace OCA\Repos\Folder;

use OCA\Repos\Config\ConfigManager;
use OCA\Repos\Mount\FolderStorageManager;
use OCA\Repos\AppInfo\Application;
use OCP\Constants;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\FileInfo;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use Psr\Log\LoggerInterface;

/**
 * Simplified repository manager that works without database,
 * using configuration files instead.
 */
class RepoManager {
	public const SPACE_DEFAULT = -4;

	public function __construct(
		private readonly ConfigManager $configManager,
		private readonly FolderStorageManager $folderStorageManager,
		private readonly IEventDispatcher $eventDispatcher,
		private readonly IConfig $config,
		private readonly IAppConfig $appConfig,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Get all repositories
	 */
	public function getAllRepos(): array {
		$repos = $this->configManager->getRepositories();
		$result = [];

		foreach ($repos as $repo) {
			$id = $repo['id'];
			$groups = $this->configManager->getGroupsForRepository($id);
			$manageEntries = $this->configManager->getManageForRepository($id);

			$result[$id] = [
				'id' => $id,
				'mount_point' => $repo['mount_point'],
				'quota' => $this->getRealQuota($repo['quota'] ?? self::SPACE_DEFAULT),
				'acl' => $repo['acl'] ?? false,
				'storage_id' => $repo['storage_id'] ?? null,
				'root_id' => $repo['root_id'] ?? null,
				'options' => $repo['options'] ?? ['separate-storage' => true],
				'groups' => $this->formatGroups($groups),
				'manage' => $manageEntries,
			];
		}

		return $result;
	}

	/**
	 * Get a specific repository by ID
	 */
	public function getRepo(int $id): ?array {
		$repo = $this->configManager->getRepository($id);
		if (!$repo) {
			return null;
		}

		$groups = $this->configManager->getGroupsForRepository($id);
		$manageEntries = $this->configManager->getManageForRepository($id);

		return [
			'id' => $id,
			'mount_point' => $repo['mount_point'],
			'quota' => $this->getRealQuota($repo['quota'] ?? self::SPACE_DEFAULT),
			'acl' => $repo['acl'] ?? false,
			'storage_id' => $repo['storage_id'] ?? null,
			'root_id' => $repo['root_id'] ?? null,
			'options' => $repo['options'] ?? ['separate-storage' => true],
			'groups' => $this->formatGroups($groups),
			'manage' => $manageEntries,
		];
	}

	/**
	 * Create a new repository
	 */
	public function createRepo(string $mountPoint, array $options = []): int {
		// Create repository in config
		$id = $this->configManager->createRepository($mountPoint, $options);

		// Initialize storage
		try {
			['storage_id' => $storageId, 'root_id' => $rootId] = $this->folderStorageManager->initRootAndStorageForFolder($id, true, $options);

			// Update storage IDs
			$this->configManager->updateRepository($id, [
				'storage_id' => $storageId,
				'root_id' => $rootId,
			]);

			$this->eventDispatcher->dispatchTyped(
				new CriticalActionPerformedEvent('A new repository "%s" was created with id %d', [$mountPoint, $id])
			);

			$this->updateOverwriteHomeFolders();

			return $id;
		} catch (\Exception $e) {
			// Rollback on error
			$this->configManager->deleteRepository($id);
			throw $e;
		}
	}

	/**
	 * Delete a repository
	 */
	public function deleteRepo(int $id): bool {
		$result = $this->configManager->deleteRepository($id);

		if ($result) {
			$this->eventDispatcher->dispatchTyped(
				new CriticalActionPerformedEvent('The repository with id %d was removed', [$id])
			);
			$this->updateOverwriteHomeFolders();
		}

		return $result;
	}

	/**
	 * Rename a repository
	 */
	public function renameRepo(int $id, string $newMountPoint): bool {
		$result = $this->configManager->updateRepository($id, ['mount_point' => $newMountPoint]);

		if ($result) {
			$this->eventDispatcher->dispatchTyped(
				new CriticalActionPerformedEvent('The repository with id %d was renamed to "%s"', [$id, $newMountPoint])
			);
			$this->updateOverwriteHomeFolders();
		}

		return $result;
	}

	/**
	 * Set repository quota
	 */
	public function setRepoQuota(int $id, int $quota): bool {
		$result = $this->configManager->updateRepository($id, ['quota' => $quota]);

		if ($result) {
			$this->eventDispatcher->dispatchTyped(
				new CriticalActionPerformedEvent('The quota for repository with id %d was set to %d bytes', [$id, $quota])
			);
		}

		return $result;
	}

	/**
	 * Add a group to repository with permissions
	 */
	public function addGroupToRepo(int $folderId, string $groupId, int $permissions = Constants::PERMISSION_ALL): void {
		$this->configManager->addGroupToRepository($folderId, $groupId, $permissions);

		$this->eventDispatcher->dispatchTyped(
			new CriticalActionPerformedEvent('The group "%s" was given access to the repository with id %d', [$groupId, $folderId])
		);
	}

	/**
	 * Remove a group from repository
	 */
	public function removeGroupFromRepo(int $folderId, string $groupId): void {
		$this->configManager->removeGroupFromRepository($folderId, $groupId);

		$this->eventDispatcher->dispatchTyped(
			new CriticalActionPerformedEvent('The group "%s" was revoked access to the repository with id %d', [$groupId, $folderId])
		);
	}

	/**
	 * Set group permissions for repository
	 */
	public function setGroupPermissions(int $folderId, string $groupId, int $permissions): void {
		$this->configManager->addGroupToRepository($folderId, $groupId, $permissions);

		$this->eventDispatcher->dispatchTyped(
			new CriticalActionPerformedEvent('The permissions of group "%s" to the repository with id %d was set to %d', [$groupId, $folderId, $permissions])
		);
	}

	/**
	 * Set manage ACL for repository
	 */
	public function setManageACL(int $folderId, string $type, string $id, bool $manageAcl): void {
		$this->configManager->setManageForRepository($folderId, $type, $id, $manageAcl);

		$action = $manageAcl ? 'given' : 'revoked';
		$this->eventDispatcher->dispatchTyped(
			new CriticalActionPerformedEvent('The %s "%s" was %s acl management rights to the repository with id %d', [$type, $id, $action, $folderId])
		);
	}

	/**
	 * Enable/disable ACL for repository
	 */
	public function setRepoACL(int $folderId, bool $acl): bool {
		$result = $this->configManager->updateRepository($folderId, ['acl' => $acl]);

		if ($result) {
			if ($acl === false) {
				// Clear manage ACL entries when disabling ACL
				$manageEntries = $this->configManager->getManageForRepository($folderId);
				foreach ($manageEntries as $entry) {
					$this->configManager->setManageForRepository(
						$folderId,
						$entry['mapping_type'],
						$entry['mapping_id'],
						false
					);
				}
			}

			$action = $acl ? 'enabled' : 'disabled';
			$this->eventDispatcher->dispatchTyped(
				new CriticalActionPerformedEvent('Advanced permissions for the repository with id %d was %s', [$folderId, $action])
			);
		}

		return $result;
	}

	/**
	 * Get real quota value (resolving SPACE_DEFAULT)
	 */
	private function getRealQuota(int $quota): int {
		if ($quota === self::SPACE_DEFAULT) {
			$defaultQuota = $this->config->getSystemValueInt('groupfolders.quota.default', FileInfo::SPACE_UNLIMITED);
			// Prevent setting the default quota option to be the default quota value creating an unresolvable self reference
			if ($defaultQuota <= 0 && $defaultQuota !== FileInfo::SPACE_UNLIMITED) {
				throw new \Exception('Default repository quota value ' . $defaultQuota . ' is not allowed');
			}

			return $defaultQuota;
		}

		return $quota;
	}

	/**
	 * Format groups array for output
	 */
	private function formatGroups(array $groups): array {
		$result = [];
		foreach ($groups as $group) {
			$groupId = $group['group_id'] ?? '';
			$result[$groupId] = [
				'displayName' => $groupId,
				'permissions' => $group['permissions'] ?? Constants::PERMISSION_ALL,
				'type' => 'group',
			];
		}
		return $result;
	}

	/**
	 * Check if any mountpoint is configured that overwrites the home folder
	 */
	private function hasHomeFolderOverwriteMount(): bool {
		$repos = $this->configManager->getRepositories();
		foreach ($repos as $repo) {
			if (($repo['mount_point'] ?? '') === '/') {
				return true;
			}
		}
		return false;
	}

	/**
	 * Update app config for home folder overwrite
	 */
	public function updateOverwriteHomeFolders(): void {
		$appIdsList = $this->appConfig->getValueArray('files', 'overwrites_home_folders');

		if ($this->hasHomeFolderOverwriteMount()) {
			if (!in_array(Application::APP_ID, $appIdsList)) {
				$appIdsList[] = Application::APP_ID;
				$this->appConfig->setValueArray('files', 'overwrites_home_folders', $appIdsList);
			}
		} else {
			if (in_array(Application::APP_ID, $appIdsList)) {
				$appIdsList = array_values(array_filter($appIdsList, fn ($v) => $v !== Application::APP_ID));
				$this->appConfig->setValueArray('files', 'overwrites_home_folders', $appIdsList);
			}
		}
	}

}
