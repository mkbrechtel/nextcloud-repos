<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\Folder;

use OC\Files\Cache\Cache;
use OC\Files\Node\Node;
use OCA\Repos\ACL\UserMapping\IUserMapping;
use OCA\Repos\ACL\UserMapping\IUserMappingManager;
use OCA\Repos\ACL\UserMapping\UserMapping;
use OCA\Repos\AppInfo\Application;
use OCA\Repos\Config\ConfigManager;
use OCA\Repos\Mount\FolderStorageManager;
use OCA\Repos\Mount\GroupMountPoint;
use OCA\Repos\ResponseDefinitions;
use OCP\AutoloadNotAllowedException;
use OCP\Constants;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\FileInfo;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type GroupFoldersGroup from ResponseDefinitions
 * @psalm-import-type GroupFoldersUser from ResponseDefinitions
 * @psalm-import-type GroupFoldersAclManage from ResponseDefinitions
 * @psalm-import-type GroupFoldersApplicable from ResponseDefinitions
 * @psalm-type InternalFolderMapping = array{
 *   folder_id: int,
 *   mapping_type: 'user'|'group',
 *   mapping_id: string,
 * }
 */
class FolderManager {
	public const SPACE_DEFAULT = -4;

	public function __construct(
		private readonly ConfigManager $configManager,
		private readonly IGroupManager $groupManager,
		private readonly IMimeTypeLoader $mimeTypeLoader,
		private readonly LoggerInterface $logger,
		private readonly IEventDispatcher $eventDispatcher,
		private readonly IConfig $config,
		private readonly IUserMappingManager $userMappingManager,
		private readonly FolderStorageManager $folderStorageManager,
		private readonly IAppConfig $appConfig,
	) {
	}

	/**
	 * @return array<int, FolderDefinitionWithMappings>
	 * @throws Exception
	 */
	public function getAllFolders(): array {
		$repos = $this->configManager->getRepositories();
		$folderMap = [];

		foreach ($repos as $repo) {
			$id = $repo['id'];
			$folder = $this->repoArrayToFolder($repo);
			$groups = $this->configManager->getGroupsForRepository($id);
			$manageEntries = $this->configManager->getManageForRepository($id);

			$applicableMap = $this->groupsArrayToApplicableMap($groups);
			$manageAcl = $this->getManageAcl($manageEntries);

			$folderMap[$id] = FolderDefinitionWithMappings::fromFolder(
				$folder,
				$applicableMap,
				$manageAcl,
			);
		}

		return $folderMap;
	}

	/**
	 * Convert repository array from config to FolderDefinition
	 */
	private function repoArrayToFolder(array $repo): FolderDefinition {
		return new FolderDefinition(
			(int)($repo['id'] ?? 0),
			(string)($repo['mount_point'] ?? ''),
			$this->getRealQuota((int)($repo['quota'] ?? self::SPACE_DEFAULT)),
			(bool)($repo['acl'] ?? false),
			(int)($repo['storage_id'] ?? 0),
			(int)($repo['root_id'] ?? 0),
			$repo['options'] ?? ['separate-storage' => true],
		);
	}

	/**
	 * Convert groups array to applicable map format
	 */
	private function groupsArrayToApplicableMap(array $groups): array {
		$map = [];
		foreach ($groups as $group) {
			$groupId = (string)($group['group_id'] ?? '');
			if ($groupId) {
				$map[$groupId] = [
					'displayName' => $groupId,
					'permissions' => (int)($group['permissions'] ?? Constants::PERMISSION_ALL),
					'type' => 'group',
				];
			}
		}
		return $map;
	}

	/**
	 * @return array<int, FolderWithMappingsAndCache>
	 * @throws Exception
	 */
	public function getAllFoldersWithSize(): array {
		$repos = $this->configManager->getRepositories();
		$folderMap = [];

		foreach ($repos as $repo) {
			$id = $repo['id'];
			$folder = $this->repoArrayToFolder($repo);
			$groups = $this->configManager->getGroupsForRepository($id);
			$manageEntries = $this->configManager->getManageForRepository($id);

			$applicableMap = $this->groupsArrayToApplicableMap($groups);
			$manageAcl = $this->getManageAcl($manageEntries);

			// Create a minimal cache entry (size calculation requires scanning, which is done separately)
			$cacheEntry = Cache::cacheEntryFromData([
				'fileid' => $repo['root_id'] ?? 0,
				'storage' => $repo['storage_id'] ?? 0,
				'path' => '',
				'name' => $repo['mount_point'] ?? '',
				'mimetype' => 'httpd/unix-directory',
				'mimepart' => 'httpd',
				'size' => -1, // Unknown size, needs scan
				'mtime' => time(),
				'storage_mtime' => time(),
				'etag' => '',
				'encrypted' => 0,
				'parent' => -1,
				'permissions' => Constants::PERMISSION_ALL,
			], $this->mimeTypeLoader);

			$folderMap[$id] = FolderWithMappingsAndCache::fromFolderWithMapping(
				FolderDefinitionWithMappings::fromFolder(
					$folder,
					$applicableMap,
					$manageAcl,
				),
				$cacheEntry,
			);
		}

		return $folderMap;
	}

	/**
	 * @return array<int, FolderWithMappingsAndCache>
	 * @throws Exception
	 */
	public function getAllFoldersForUserWithSize(IUser $user): array {
		$groups = $this->groupManager->getUserGroupIds($user);
		$applicableMap = $this->getAllApplicable();

		$query = $this->selectWithFileCache();
		$query->innerJoin(
			'f',
			'group_folders_groups',
			'a',
			$query->expr()->eq('f.folder_id', 'a.folder_id'),
		)
			->selectAlias('a.permissions', 'group_permissions')
			->where($query->expr()->in('a.group_id', $query->createNamedParameter($groups, IQueryBuilder::PARAM_STR_ARRAY)));

		$rows = $query->executeQuery()->fetchAll();

		$folderMappings = $this->getAllFolderMappings();

		$folderMap = [];
		foreach ($rows as $row) {
			$folder = $this->rowToFolder($row);
			$id = $folder->id;
			$folderMap[$id] = FolderWithMappingsAndCache::fromFolderWithMapping(
				FolderDefinitionWithMappings::fromFolder(
					$folder,
					$applicableMap[$id] ?? [],
					$this->getManageAcl($folderMappings[$id] ?? []),
				),
				Cache::cacheEntryFromData($row, $this->mimeTypeLoader),
			);
		}

		return $folderMap;
	}

	/**
	 * @return array<int, list<InternalFolderMapping>>
	 * @throws Exception
	 */
	private function getAllFolderMappings(): array {
		$query = $this->connection->getQueryBuilder();

		$query->select('*')
			->from('group_folders_manage', 'g');

		$rows = $query->executeQuery()->fetchAll();

		$folderMap = [];
		foreach ($rows as $row) {
			$id = (int)$row['folder_id'];

			if (!isset($folderMap[$id])) {
				$folderMap[$id] = [$row];
			} else {
				$folderMap[$id][] = $row;
			}
		}

		return $folderMap;
	}

	/**
	 * @return array<int, InternalFolderMapping>
	 * @throws Exception
	 */
	private function getFolderMappings(int $id): array {
		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('group_folders_manage')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $query->executeQuery()->fetchAll();
	}

	/**
	 * @param InternalFolderMapping[] $mappings
	 * @return list<GroupFoldersAclManage>
	 */
	private function getManageAcl(array $mappings): array {
		return array_values(array_filter(array_map(function (array $entry): ?array {
			if ($entry['mapping_type'] === 'user') {
				$user = Server::get(IUserManager::class)->get($entry['mapping_id']);
				if ($user === null) {
					return null;
				}

				return [
					'type' => 'user',
					'id' => (string)$user->getUID(),
					'displayname' => (string)$user->getDisplayName(),
				];
			}

			if ($entry['mapping_type'] === 'group') {
				$group = Server::get(IGroupManager::class)->get($entry['mapping_id']);
				if ($group === null) {
					return null;
				}

				return [
					'type' => 'group',
					'id' => $group->getGID(),
					'displayname' => $group->getDisplayName(),
				];
			}

			if ($entry['mapping_type'] === 'circle') {
				$circle = $this->getCircle($entry['mapping_id']);
				if ($circle === null) {
					return null;
				}

				return [
					'type' => 'circle',
					'id' => $circle->getSingleId(),
					'displayname' => $circle->getDisplayName(),
				];
			}

			return null;
		}, $mappings)));
	}

	public function getFolder(int $id): ?FolderWithMappingsAndCache {
		$applicableMap = $this->getAllApplicable();

		$query = $this->selectWithFileCache();

		$query->where($query->expr()->eq('f.folder_id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		if (!$row) {
			return null;
		}

		$folderMappings = $this->getFolderMappings($id);

		$folder = $this->rowToFolder($row);
		$id = $folder->id;
		return FolderWithMappingsAndCache::fromFolderWithMapping(
			FolderDefinitionWithMappings::fromFolder(
				$folder,
				$applicableMap[$id] ?? [],
				$this->getManageAcl($folderMappings),
			),
			Cache::cacheEntryFromData($row, $this->mimeTypeLoader),
		);
	}

	/**
	 * Return just the ACL for the folder.
	 *
	 * @throws Exception
	 */
	public function getFolderAclEnabled(int $id): bool {
		$query = $this->connection->getQueryBuilder();
		$query->select('acl')
			->from('group_folders', 'f')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return (bool)($row['acl'] ?? false);
	}

	public function getFolderByPath(string $path): int {
		/** @var Node $node */
		$node = Server::get(IRootFolder::class)->get($path);
		/** @var GroupMountPoint $mountPoint */
		$mountPoint = $node->getMountPoint();

		return $mountPoint->getFolderId();
	}

	/**
	 * @return array<int, array<string, GroupFoldersApplicable>>
	 * @throws Exception
	 */
	private function getAllApplicable(): array {
		$query = $this->connection->getQueryBuilder();
		$query->select('g.folder_id', 'g.group_id', 'g.permissions')
			->from('group_folders_groups', 'g')
			->where($query->expr()->neq('g.group_id', $query->createNamedParameter('')));

		$rows = $query->executeQuery()->fetchAll();
		$applicableMap = [];
		foreach ($rows as $row) {
			$id = (int)$row['folder_id'];
			if (!array_key_exists($id, $applicableMap)) {
				$applicableMap[$id] = [];
			}

			$entityId = (string)$row['group_id'];
			$entry = [
				'displayName' => $row['group_id'],
				'permissions' => (int)$row['permissions'],
				'type' => 'group',
			];

			$applicableMap[$id][$entityId] = $entry;
		}

		return $applicableMap;
	}

	/**
	 * @return list<GroupFoldersGroup>
	 * @throws Exception
	 */
	private function getGroups(int $id): array {
		$groups = $this->getAllApplicable()[$id] ?? [];
		$groups = array_map(fn (string $gid): ?IGroup => $this->groupManager->get($gid), array_keys($groups));

		return array_map(fn (IGroup $group): array => [
			'gid' => $group->getGID(),
			'displayname' => $group->getDisplayName(),
		], array_values(array_filter($groups)));
	}

	/**
	 * Check if the user is able to configure the advanced folder permissions. This
	 * is the case if the user is an admin, has admin permissions for the group folder
	 * app or is member of a group that can manage permissions for the specific folder.
	 *
	 * @throws Exception
	 */
	public function canManageACL(int $folderId, IUser $user): bool {
		$userId = $user->getUId();
		if ($this->groupManager->isAdmin($userId)) {
			return true;
		}

		// Call private server api
		if (class_exists(\OC\Settings\AuthorizedGroupMapper::class)) {
			$authorizedGroupMapper = Server::get(\OC\Settings\AuthorizedGroupMapper::class);
			$settingClasses = $authorizedGroupMapper->findAllClassesForUser($user);
			if (in_array(\OCA\GroupFolders\Settings\Admin::class, $settingClasses, true)) {
				return true;
			}
		}

		$managerMappings = $this->getManagerMappings($folderId);
		return $this->userMappingManager->userInMappings($user, $managerMappings);
	}

	/**
	 * @param int $folderId
	 * @return IUserMapping[]
	 */
	private function getManagerMappings(int $folderId): array {
		$query = $this->connection->getQueryBuilder();
		$query->select('mapping_type', 'mapping_id')
			->from('group_folders_manage')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		$managerMappings = [];

		$rows = $query->executeQuery()->fetchAll();
		foreach ($rows as $manageRule) {
			$managerMappings[] = new UserMapping($manageRule['mapping_type'], $manageRule['mapping_id']);
		}
		return $managerMappings;
	}

	/**
	 * @return list<GroupFoldersGroup>
	 * @throws Exception
	 */
	public function searchGroups(int $id, string $search = ''): array {
		$groups = $this->getGroups($id);
		if ($search === '') {
			return $groups;
		}

		return array_values(array_filter($groups, fn (array $group): bool => (stripos($group['gid'], $search) !== false) || (stripos($group['displayname'], $search) !== false)));
	}

	/**
	 * @return list<GroupFoldersUser>
	 * @throws Exception
	 */
	public function searchUsers(int $id, string $search = '', int $limit = 10, int $offset = 0): array {
		$groups = $this->getGroups($id);
		$users = [];
		foreach ($groups as $groupArray) {
			$group = $this->groupManager->get($groupArray['gid']);
			if ($group) {
				$foundUsers = $this->groupManager->displayNamesInGroup($group->getGID(), $search, $limit, $offset);
				foreach ($foundUsers as $uid => $displayName) {
					if (!isset($users[$uid])) {
						$users[$uid] = [
							'uid' => (string)$uid,
							'displayname' => $displayName,
						];
					}
				}
			}
		}

		return array_values($users);
	}

	private function getFolderOptions(array $row): array {
		if (!isset($row['options'])) {
			return [];
		}

		try {
			$options = json_decode($row['options'], true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			$this->logger->warning('Error while decoding the folder options', ['exception' => $e, 'folder_id' => $row['folder_id'] ?? 'unknown']);
			return [];
		}

		if (!is_array($options)) {
			return [];
		}

		return $options;
	}

	private function rowToFolder(array $row): FolderDefinition {
		return new FolderDefinition(
			(int)$row['folder_id'],
			(string)$row['mount_point'],
			$this->getRealQuota((int)$row['quota']),
			(bool)$row['acl'],
			(int)$row['storage_id'],
			(int)$row['root_id'],
			$this->getFolderOptions($row),
		);
	}

	/**
	 * @param string[] $groupIds
	 * @return list<FolderDefinitionWithPermissions>
	 * @throws Exception
	 */
	public function getFoldersForGroups(array $groupIds, ?int $folderId = null): array {
		if (count($groupIds) === 0) {
			return [];
		}
		$query = $this->selectWithFileCache();

		$query->innerJoin(
			'f',
			'group_folders_groups',
			'a',
			$query->expr()->eq('f.folder_id', 'a.folder_id'),
		)
			->selectAlias('a.permissions', 'group_permissions')
			->where($query->expr()->in('a.group_id', $query->createParameter('groupIds')));

		if ($folderId !== null) {
			$query->andWhere($query->expr()->eq('f.folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		}

		// add chunking because Oracle can't deal with more than 1000 values in an expression list for in queries.
		$result = [];
		foreach (array_chunk($groupIds, 1000) as $chunk) {
			$query->setParameter('groupIds', $chunk, IQueryBuilder::PARAM_STR_ARRAY);
			$result = array_merge($result, $query->executeQuery()->fetchAll());
		}

		return array_values(array_map(function (array $row): FolderDefinitionWithPermissions {
			$folder = $this->rowToFolder($row);
			return FolderDefinitionWithPermissions::fromFolder(
				$folder,
				Cache::cacheEntryFromData($row, $this->mimeTypeLoader),
				(int)$row['group_permissions']
			);
		}, $result));
	}


	/**
	 * @throws Exception
	 */
	public function createFolder(string $mountPoint, array $options = []): int {
		$query = $this->connection->getQueryBuilder();

		$query->insert('group_folders')
			->values([
				'mount_point' => $query->createNamedParameter($mountPoint),
				'quota' => self::SPACE_DEFAULT,
				'options' => $query->createNamedParameter(json_encode([
					'separate-storage' => true,
				]))
			]);
		$query->executeStatement();
		$id = $query->getLastInsertId();

		['storage_id' => $storageId, 'root_id' => $rootId] = $this->folderStorageManager->initRootAndStorageForFolder($id, true, $options);
		$query->update('group_folders')
			->set('root_id', $query->createNamedParameter($rootId))
			->set('storage_id', $query->createNamedParameter($storageId))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($id)));
		$query->executeStatement();

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('A new groupfolder "%s" was created with id %d', [$mountPoint, $id]));

		$this->updateOverwriteHomeFolders();

		return $id;
	}

	/**
	 * @throws Exception
	 */
	public function addApplicableGroup(int $folderId, string $groupId): void {
		$query = $this->connection->getQueryBuilder();

		$query->insert('group_folders_groups')
			->values([
				'folder_id' => $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT),
				'group_id' => $query->createNamedParameter($groupId),
				'permissions' => $query->createNamedParameter(Constants::PERMISSION_ALL),
			]);
		$query->executeStatement();

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('The group "%s" was given access to the groupfolder with id %d', [$groupId, $folderId]));
	}

	/**
	 * @throws Exception
	 */
	public function removeApplicableGroup(int $folderId, string $groupId): void {
		$query = $this->connection->getQueryBuilder();

		$query->delete('group_folders_groups')
			->where(
				$query->expr()->eq(
					'folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT),
				),
			)
			->andWhere($query->expr()->eq('group_id', $query->createNamedParameter($groupId)));
		$query->executeStatement();

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('The group "%s" was revoked access to the groupfolder with id %d', [$groupId, $folderId]));
	}


	/**
	 * @throws Exception
	 */
	public function setGroupPermissions(int $folderId, string $groupId, int $permissions): void {
		$query = $this->connection->getQueryBuilder();

		$query->update('group_folders_groups')
			->set('permissions', $query->createNamedParameter($permissions, IQueryBuilder::PARAM_INT))
			->where(
				$query->expr()->eq(
					'folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT),
				),
			)
			->andWhere($query->expr()->eq('group_id', $query->createNamedParameter($groupId)));

		$query->executeStatement();

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('The permissions of group "%s" to the groupfolder with id %d was set to %d', [$groupId, $folderId, $permissions]));
	}

	/**
	 * @throws Exception
	 */
	public function setManageACL(int $folderId, string $type, string $id, bool $manageAcl): void {
		$query = $this->connection->getQueryBuilder();
		if ($manageAcl === true) {
			$query->insert('group_folders_manage')
				->values([
					'folder_id' => $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT),
					'mapping_type' => $query->createNamedParameter($type),
					'mapping_id' => $query->createNamedParameter($id),
				]);
		} else {
			$query->delete('group_folders_manage')
				->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
				->andWhere($query->expr()->eq('mapping_type', $query->createNamedParameter($type)))
				->andWhere($query->expr()->eq('mapping_id', $query->createNamedParameter($id)));
		}

		$query->executeStatement();

		$action = $manageAcl ? 'given' : 'revoked';
		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('The %s "%s" was %s acl management rights to the groupfolder with id %d', [$type, $id, $action, $folderId]));
	}

	/**
	 * @throws Exception
	 */
	public function removeFolder(int $folderId): void {
		$query = $this->connection->getQueryBuilder();

		$query->delete('group_folders')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		$query->executeStatement();

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('The groupfolder with id %d was removed', [$folderId]));

		$this->updateOverwriteHomeFolders();
	}

	/**
	 * @throws Exception
	 */
	public function setFolderQuota(int $folderId, int $quota): void {
		$query = $this->connection->getQueryBuilder();

		$query->update('group_folders')
			->set('quota', $query->createNamedParameter($quota))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId)));
		$query->executeStatement();

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('The quota for groupfolder with id %d was set to %d bytes', [$folderId, $quota]));
	}

	/**
	 * @throws Exception
	 */
	public function renameFolder(int $folderId, string $newMountPoint): void {
		$query = $this->connection->getQueryBuilder();

		$query->update('group_folders')
			->set('mount_point', $query->createNamedParameter($newMountPoint))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		$query->executeStatement();

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('The groupfolder with id %d was renamed to "%s"', [$folderId, $newMountPoint]));

		$this->updateOverwriteHomeFolders();
	}

	/**
	 * @throws Exception
	 */
	public function deleteGroup(string $groupId): void {
		$query = $this->connection->getQueryBuilder();

		$query->delete('group_folders_groups')
			->where($query->expr()->eq('group_id', $query->createNamedParameter($groupId)));
		$query->executeStatement();

		$query = $this->connection->getQueryBuilder();
		$query->delete('group_folders_manage')
			->where($query->expr()->eq('mapping_id', $query->createNamedParameter($groupId)))
			->andWhere($query->expr()->eq('mapping_type', $query->createNamedParameter('group')));
		$query->executeStatement();

		$query = $this->connection->getQueryBuilder();
		$query->delete('group_folders_acl')
			->where($query->expr()->eq('mapping_id', $query->createNamedParameter($groupId)))
			->andWhere($query->expr()->eq('mapping_type', $query->createNamedParameter('group')));
		$query->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function setFolderACL(int $folderId, bool $acl): void {
		$query = $this->connection->getQueryBuilder();

		$query->update('group_folders')
			->set('acl', $query->createNamedParameter((int)$acl, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId)));
		$query->executeStatement();

		if ($acl === false) {
			$query = $this->connection->getQueryBuilder();
			$query->delete('group_folders_manage')
				->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId)));
			$query->executeStatement();
		}

		$action = $acl ? 'enabled' : 'disabled';
		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('Advanced permissions for the groupfolder with id %d was %s', [$folderId, $action]));
	}

	/**
	 * @return list<FolderDefinitionWithPermissions>
	 * @throws Exception
	 */
	public function getFoldersForUser(IUser $user, ?int $folderId = null): array {
		$groups = $this->groupManager->getUserGroupIds($user);
		/** @var list<FolderDefinitionWithPermissions> $folders */
		$folders = $this->getFoldersForGroups($groups, $folderId);

		/** @var array<int, FolderDefinitionWithPermissions> $mergedFolders */
		$mergedFolders = [];
		foreach ($folders as $folder) {
			$id = $folder->id;
			if (isset($mergedFolders[$id])) {
				$mergedFolders[$id] = $mergedFolders[$id]->withAddedPermissions($folder->permissions);
			} else {
				$mergedFolders[$id] = $folder;
			}
		}

		return array_values($mergedFolders);
	}

	/**
	 * @throws Exception
	 */
	public function getFolderPermissionsForUser(IUser $user, int $folderId): int {
		$groups = $this->groupManager->getUserGroupIds($user);
		/** @var list<FolderDefinitionWithPermissions> $folders */
		$folders = array_merge(
			$this->getFoldersForGroups($groups, $folderId),
			$this->getFoldersFromCircleMemberships($user, $folderId),
		);

		$permissions = 0;
		foreach ($folders as $folder) {
			if ($folderId === $folder->id) {
				$permissions |= $folder->permissions;
			}
		}

		return $permissions;
	}

	private function getRealQuota(int $quota): int {
		if ($quota === self::SPACE_DEFAULT) {
			$defaultQuota = $this->config->getSystemValueInt('groupfolders.quota.default', FileInfo::SPACE_UNLIMITED);
			// Prevent setting the default quota option to be the default quota value creating an unresolvable self reference
			if ($defaultQuota <= 0 && $defaultQuota !== FileInfo::SPACE_UNLIMITED) {
				throw new \Exception('Default Groupfolder quota value ' . $defaultQuota . ' is not allowed');
			}

			return $defaultQuota;
		}

		return $quota;
	}

	/**
	 * Check if any mountpoint is configured that overwrite the home folder
	 */
	private function hasHomeFolderOverwriteMount(): bool {
		$builder = $this->connection->getQueryBuilder();
		$query = $builder->select('folder_id')
			->from('group_folders')
			->where($builder->expr()->eq('mount_point', $builder->createNamedParameter('/')))
			->setMaxResults(1);
		$result = $query->executeQuery();
		return $result->rowCount() > 0;
	}

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
