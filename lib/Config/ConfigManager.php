<?php

declare(strict_types=1);

namespace OCA\Repos\Config;

use OCP\Files\IAppData;
use OCP\IConfig;

/**
 * Manages repository configuration stored in a JSON file
 * instead of database tables.
 */
class ConfigManager {
	private const CONFIG_FILE = 'repos_config.json';

	/** @var array|null Cached configuration */
	private ?array $configCache = null;

	/** @var string Path to configuration file */
	private string $configPath;

	public function __construct(
		private IConfig $config,
	) {
		// Store config in Nextcloud data directory
		$dataDir = $this->config->getSystemValue('datadirectory', '');
		if (empty($dataDir)) {
			throw new \RuntimeException('Data directory not configured');
		}
		$this->configPath = $dataDir . '/' . self::CONFIG_FILE;
	}

	/**
	 * Load configuration from file
	 */
	private function loadConfig(): array {
		if ($this->configCache !== null) {
			return $this->configCache;
		}

		if (!file_exists($this->configPath)) {
			$this->configCache = $this->getDefaultConfig();
			return $this->configCache;
		}

		$handle = fopen($this->configPath, 'r');
		if ($handle === false) {
			throw new \RuntimeException('Failed to open config file for reading');
		}

		// Acquire shared lock for reading
		if (!flock($handle, LOCK_SH)) {
			fclose($handle);
			throw new \RuntimeException('Failed to acquire read lock on config file');
		}

		$content = stream_get_contents($handle);
		flock($handle, LOCK_UN);
		fclose($handle);

		if ($content === false) {
			throw new \RuntimeException('Failed to read config file');
		}

		$config = json_decode($content, true);
		if (!is_array($config)) {
			// Invalid JSON, use default config
			$config = $this->getDefaultConfig();
		}

		$this->configCache = $config;
		return $this->configCache;
	}

	/**
	 * Save configuration to file
	 */
	private function saveConfig(array $config): void {
		// Ensure directory exists
		$dir = dirname($this->configPath);
		if (!is_dir($dir)) {
			mkdir($dir, 0770, true);
		}

		// Write to temporary file first
		$tempFile = $this->configPath . '.tmp';
		$handle = fopen($tempFile, 'w');
		if ($handle === false) {
			throw new \RuntimeException('Failed to open config file for writing');
		}

		// Acquire exclusive lock for writing
		if (!flock($handle, LOCK_EX)) {
			fclose($handle);
			throw new \RuntimeException('Failed to acquire write lock on config file');
		}

		$json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			flock($handle, LOCK_UN);
			fclose($handle);
			throw new \RuntimeException('Failed to encode config as JSON');
		}

		if (fwrite($handle, $json) === false) {
			flock($handle, LOCK_UN);
			fclose($handle);
			throw new \RuntimeException('Failed to write config file');
		}

		flock($handle, LOCK_UN);
		fclose($handle);

		// Atomic rename
		if (!rename($tempFile, $this->configPath)) {
			throw new \RuntimeException('Failed to replace config file');
		}

		// Update cache
		$this->configCache = $config;
	}

	/**
	 * Get default configuration structure
	 */
	private function getDefaultConfig(): array {
		return [
			'version' => 1,
			'repositories' => [],
			'groups' => [],
			'manage' => [],
		];
	}

	/**
	 * Get all repositories
	 */
	public function getRepositories(): array {
		$config = $this->loadConfig();
		return $config['repositories'] ?? [];
	}

	/**
	 * Get a specific repository by ID
	 */
	public function getRepository(int $id): ?array {
		$repos = $this->getRepositories();
		foreach ($repos as $repo) {
			if (($repo['id'] ?? null) === $id) {
				return $repo;
			}
		}
		return null;
	}

	/**
	 * Create a new repository
	 */
	public function createRepository(string $mountPoint, array $options = []): int {
		$config = $this->loadConfig();
		$repos = $config['repositories'] ?? [];

		// Generate new ID
		$maxId = 0;
		foreach ($repos as $repo) {
			if (isset($repo['id']) && $repo['id'] > $maxId) {
				$maxId = $repo['id'];
			}
		}
		$newId = $maxId + 1;

		// Create new repository
		$newRepo = [
			'id' => $newId,
			'mount_point' => $mountPoint,
			'quota' => -4, // Default quota
			'acl' => false,
			'storage_id' => null,
			'root_id' => null,
			'options' => array_merge(['separate-storage' => true], $options),
		];

		$repos[] = $newRepo;
		$config['repositories'] = $repos;
		$this->saveConfig($config);

		return $newId;
	}

	/**
	 * Update a repository
	 */
	public function updateRepository(int $id, array $data): bool {
		$config = $this->loadConfig();
		$repos = $config['repositories'] ?? [];

		$found = false;
		foreach ($repos as $key => $repo) {
			if (($repo['id'] ?? null) === $id) {
				$repos[$key] = array_merge($repo, $data);
				$found = true;
				break;
			}
		}

		if (!$found) {
			return false;
		}

		$config['repositories'] = $repos;
		$this->saveConfig($config);
		return true;
	}

	/**
	 * Delete a repository
	 */
	public function deleteRepository(int $id): bool {
		$config = $this->loadConfig();
		$repos = $config['repositories'] ?? [];

		$newRepos = [];
		$found = false;
		foreach ($repos as $repo) {
			if (($repo['id'] ?? null) === $id) {
				$found = true;
				// Also clean up groups and manage entries for this repo
				$this->deleteGroupsForRepository($id);
				$this->deleteManageForRepository($id);
				continue;
			}
			$newRepos[] = $repo;
		}

		if (!$found) {
			return false;
		}

		$config['repositories'] = $newRepos;
		$this->saveConfig($config);
		return true;
	}

	/**
	 * Get groups for a repository
	 */
	public function getGroupsForRepository(int $folderId): array {
		$config = $this->loadConfig();
		$groups = $config['groups'] ?? [];

		$result = [];
		foreach ($groups as $group) {
			if (($group['folder_id'] ?? null) === $folderId) {
				$result[] = $group;
			}
		}
		return $result;
	}

	/**
	 * Add group to repository
	 */
	public function addGroupToRepository(int $folderId, string $groupId, int $permissions): void {
		$config = $this->loadConfig();
		$groups = $config['groups'] ?? [];

		// Check if already exists
		foreach ($groups as $key => $group) {
			if (($group['folder_id'] ?? null) === $folderId && ($group['group_id'] ?? null) === $groupId) {
				// Update permissions
				$groups[$key]['permissions'] = $permissions;
				$config['groups'] = $groups;
				$this->saveConfig($config);
				return;
			}
		}

		// Add new group mapping
		$groups[] = [
			'folder_id' => $folderId,
			'group_id' => $groupId,
			'permissions' => $permissions,
		];
		$config['groups'] = $groups;
		$this->saveConfig($config);
	}

	/**
	 * Remove group from repository
	 */
	public function removeGroupFromRepository(int $folderId, string $groupId): void {
		$config = $this->loadConfig();
		$groups = $config['groups'] ?? [];

		$newGroups = [];
		foreach ($groups as $group) {
			if (($group['folder_id'] ?? null) === $folderId && ($group['group_id'] ?? null) === $groupId) {
				continue;
			}
			$newGroups[] = $group;
		}

		$config['groups'] = $newGroups;
		$this->saveConfig($config);
	}

	/**
	 * Delete all groups for a repository
	 */
	private function deleteGroupsForRepository(int $folderId): void {
		$config = $this->loadConfig();
		$groups = $config['groups'] ?? [];

		$newGroups = [];
		foreach ($groups as $group) {
			if (($group['folder_id'] ?? null) !== $folderId) {
				$newGroups[] = $group;
			}
		}

		$config['groups'] = $newGroups;
		$this->saveConfig($config);
	}

	/**
	 * Get manage ACL entries for a repository
	 */
	public function getManageForRepository(int $folderId): array {
		$config = $this->loadConfig();
		$manage = $config['manage'] ?? [];

		$result = [];
		foreach ($manage as $entry) {
			if (($entry['folder_id'] ?? null) === $folderId) {
				$result[] = $entry;
			}
		}
		return $result;
	}

	/**
	 * Set manage ACL for repository
	 */
	public function setManageForRepository(int $folderId, string $type, string $id, bool $manageAcl): void {
		$config = $this->loadConfig();
		$manage = $config['manage'] ?? [];

		if ($manageAcl) {
			// Add or update
			$found = false;
			foreach ($manage as $key => $entry) {
				if (($entry['folder_id'] ?? null) === $folderId
					&& ($entry['mapping_type'] ?? null) === $type
					&& ($entry['mapping_id'] ?? null) === $id) {
					$found = true;
					break;
				}
			}

			if (!$found) {
				$manage[] = [
					'folder_id' => $folderId,
					'mapping_type' => $type,
					'mapping_id' => $id,
				];
			}
		} else {
			// Remove
			$newManage = [];
			foreach ($manage as $entry) {
				if (($entry['folder_id'] ?? null) === $folderId
					&& ($entry['mapping_type'] ?? null) === $type
					&& ($entry['mapping_id'] ?? null) === $id) {
					continue;
				}
				$newManage[] = $entry;
			}
			$manage = $newManage;
		}

		$config['manage'] = $manage;
		$this->saveConfig($config);
	}

	/**
	 * Delete all manage entries for a repository
	 */
	private function deleteManageForRepository(int $folderId): void {
		$config = $this->loadConfig();
		$manage = $config['manage'] ?? [];

		$newManage = [];
		foreach ($manage as $entry) {
			if (($entry['folder_id'] ?? null) !== $folderId) {
				$newManage[] = $entry;
			}
		}

		$config['manage'] = $manage;
		$this->saveConfig($config);
	}

	/**
	 * Clear configuration cache
	 */
	public function clearCache(): void {
		$this->configCache = null;
	}
}
