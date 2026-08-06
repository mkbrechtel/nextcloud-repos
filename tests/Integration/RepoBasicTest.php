<?php
/**
 * SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Repos\Tests\Integration;

use OCA\Repos\Config\ConfigManager;
use OCA\Repos\Folder\RepoManager;
use OCA\Repos\Mount\MountProvider;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for basic repository functionality
 */
class RepoBasicTest extends TestCase {
    private RepoManager $repoManager;
    private ConfigManager $configManager;
    private MountProvider $mountProvider;
    private IUserManager $userManager;
    private IGroupManager $groupManager;
    private IRootFolder $rootFolder;
    private IUser $testUser;
    private IGroup $testGroup;

    protected function setUp(): void {
        parent::setUp();

        // Get services from the server container
        $this->configManager = \OC::$server->get(ConfigManager::class);
        $this->repoManager = \OC::$server->get(RepoManager::class);
        $this->mountProvider = \OC::$server->get(MountProvider::class);
        $this->userManager = \OC::$server->get(IUserManager::class);
        $this->groupManager = \OC::$server->get(IGroupManager::class);
        $this->rootFolder = \OC::$server->get(IRootFolder::class);

        // Clean up any existing test data
        $this->cleanupTestData();

        // Create test user
        if ($this->userManager->userExists('testuser')) {
            $this->testUser = $this->userManager->get('testuser');
        } else {
            $this->testUser = $this->userManager->createUser('testuser', 'testpassword');
        }
        $this->assertNotNull($this->testUser, 'Failed to create test user');

        // Create test group
        if ($this->groupManager->groupExists('testgroup')) {
            $this->testGroup = $this->groupManager->get('testgroup');
        } else {
            $this->testGroup = $this->groupManager->createGroup('testgroup');
        }
        $this->assertNotNull($this->testGroup, 'Failed to create test group');

        // Add user to group
        $this->testGroup->addUser($this->testUser);
    }

    protected function tearDown(): void {
        // Clean up test data
        $this->cleanupTestData();

        // Remove test user and group
        if ($this->testUser) {
            $this->testGroup->removeUser($this->testUser);
            $this->testUser->delete();
        }
        if ($this->testGroup) {
            $this->testGroup->delete();
        }

        parent::tearDown();
    }

    private function cleanupTestData(): void {
        // Clear config cache
        $this->configManager->clearCache();

        // Delete all test repositories
        $repos = $this->repoManager->getAllRepos();
        foreach ($repos as $repo) {
            if (str_starts_with($repo['mount_point'], 'test_')) {
                $this->repoManager->deleteRepo($repo['id']);
            }
        }
    }

    public function testCreateRepository(): void {
        // Create a repository
        $repoId = $this->repoManager->createRepo('test_repo_1');
        $this->assertGreaterThan(0, $repoId, 'Repository ID should be positive');

        // Verify it was created
        $repo = $this->repoManager->getRepo($repoId);
        $this->assertNotNull($repo, 'Repository should exist');
        $this->assertEquals('test_repo_1', $repo['mount_point']);
        $this->assertEquals($repoId, $repo['id']);
    }

    public function testListRepositories(): void {
        // Create multiple repositories
        $repo1Id = $this->repoManager->createRepo('test_repo_list_1');
        $repo2Id = $this->repoManager->createRepo('test_repo_list_2');

        // Get all repositories
        $repos = $this->repoManager->getAllRepos();
        $this->assertGreaterThanOrEqual(2, count($repos), 'Should have at least 2 repositories');

        // Find our test repos
        $foundRepo1 = false;
        $foundRepo2 = false;
        foreach ($repos as $repo) {
            if ($repo['id'] === $repo1Id) {
                $foundRepo1 = true;
                $this->assertEquals('test_repo_list_1', $repo['mount_point']);
            }
            if ($repo['id'] === $repo2Id) {
                $foundRepo2 = true;
                $this->assertEquals('test_repo_list_2', $repo['mount_point']);
            }
        }
        $this->assertTrue($foundRepo1, 'First repository should be in list');
        $this->assertTrue($foundRepo2, 'Second repository should be in list');
    }

    public function testAddGroupToRepository(): void {
        // Create a repository
        $repoId = $this->repoManager->createRepo('test_repo_group');

        // Add group with specific permissions
        $permissions = Constants::PERMISSION_READ | Constants::PERMISSION_UPDATE | Constants::PERMISSION_CREATE;
        $this->repoManager->addGroupToRepo($repoId, 'testgroup', $permissions);

        // Verify group was added
        $repo = $this->repoManager->getRepo($repoId);
        $this->assertArrayHasKey('groups', $repo);
        $this->assertArrayHasKey('testgroup', $repo['groups']);
        $this->assertEquals($permissions, $repo['groups']['testgroup']['permissions']);
    }

    public function testRemoveGroupFromRepository(): void {
        // Create a repository and add a group
        $repoId = $this->repoManager->createRepo('test_repo_remove_group');
        $this->repoManager->addGroupToRepo($repoId, 'testgroup', Constants::PERMISSION_ALL);

        // Verify group was added
        $repo = $this->repoManager->getRepo($repoId);
        $this->assertArrayHasKey('testgroup', $repo['groups']);

        // Remove the group
        $this->repoManager->removeGroupFromRepo($repoId, 'testgroup');

        // Verify group was removed
        $repo = $this->repoManager->getRepo($repoId);
        $this->assertArrayNotHasKey('testgroup', $repo['groups']);
    }

    public function testDeleteRepository(): void {
        // Create a repository
        $repoId = $this->repoManager->createRepo('test_repo_delete');

        // Verify it exists
        $repo = $this->repoManager->getRepo($repoId);
        $this->assertNotNull($repo);

        // Delete it
        $result = $this->repoManager->deleteRepo($repoId);
        $this->assertTrue($result, 'Delete should return true');

        // Verify it's gone
        $repo = $this->repoManager->getRepo($repoId);
        $this->assertNull($repo, 'Repository should not exist after deletion');
    }

    public function testRepositoryMounting(): void {
        // Create a repository and add the test group
        $repoId = $this->repoManager->createRepo('test_repo_mount');
        $this->repoManager->addGroupToRepo($repoId, 'testgroup', Constants::PERMISSION_ALL);

        // Get mounts for test user
        $mounts = $this->mountProvider->getMountsForUser($this->testUser, \OC::$server->get(\OCP\Files\Storage\IStorageFactory::class));

        // Verify the repository is mounted
        $foundMount = false;
        foreach ($mounts as $mount) {
            if (str_contains($mount->getMountPoint(), 'test_repo_mount')) {
                $foundMount = true;
                break;
            }
        }
        $this->assertTrue($foundMount, 'Repository should be mounted for user');
    }

    public function testFileAccessInRepository(): void {
        // Create a repository and add the test group with write permissions
        $repoId = $this->repoManager->createRepo('test_repo_file_access');
        $this->repoManager->addGroupToRepo(
            $repoId,
            'testgroup',
            Constants::PERMISSION_ALL
        );

        // Get the user's folder
        $userFolder = $this->rootFolder->getUserFolder($this->testUser->getUID());

        // Try to access the repository folder
        try {
            $repoFolder = $userFolder->get('test_repo_file_access');
            $this->assertNotNull($repoFolder, 'Repository folder should be accessible');
            $this->assertTrue($repoFolder->isReadable(), 'Repository folder should be readable');

            // Try to create a file in the repository
            $file = $repoFolder->newFile('test.txt');
            $file->putContent('test content');

            // Verify the file was created
            $this->assertTrue($repoFolder->nodeExists('test.txt'), 'File should exist');

            // Read the file
            $readFile = $repoFolder->get('test.txt');
            $this->assertEquals('test content', $readFile->getContent());

        } catch (\OCP\Files\NotFoundException $e) {
            $this->fail('Repository folder should be accessible: ' . $e->getMessage());
        }
    }
}
