<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Repos\AppInfo;

use OCA\DAV\Connector\Sabre\Principal;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\Files_Trashbin\Expiration;
use OCA\Repos\ACL\ACLManagerFactory;
use OCA\Repos\ACL\UserMapping\IUserMappingManager;
use OCA\Repos\ACL\UserMapping\UserMappingManager;
use OCA\Repos\AuthorizedAdminSettingMiddleware;
use OCA\Repos\BackgroundJob\ExpireGroupPlaceholder;
use OCA\Repos\BackgroundJob\ExpireGroupTrash as ExpireGroupTrashJob;
use OCA\Repos\BackgroundJob\ExpireGroupVersions as ExpireGroupVersionsJob;
use OCA\Repos\CacheListener;
use OCA\Repos\Command\ExpireGroup\ExpireGroupBase;
use OCA\Repos\Command\ExpireGroup\ExpireGroupTrash;
use OCA\Repos\Command\ExpireGroup\ExpireGroupVersions;
use OCA\Repos\Command\ExpireGroup\ExpireGroupVersionsTrash;
use OCA\Repos\Folder\FolderManager;
use OCA\Repos\Listeners\LoadAdditionalScriptsListener;
use OCA\Repos\Listeners\NodeRenamedListener;
use OCA\Repos\Mount\FolderStorageManager;
use OCA\Repos\Mount\MountProvider;
use OCA\Repos\Trash\TrashBackend;
use OCA\Repos\Trash\TrashManager;
use OCA\Repos\Versions\GroupVersionsExpireManager;
use OCA\Repos\Versions\VersionsBackend;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMountManager;
use OCP\Files\Storage\IStorageFactory;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Server;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {
	public const APP_ID = 'repos';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public const APPS_USE_REPOS = [
		'workspace'
	];

	public function register(IRegistrationContext $context): void {
		/** Register $principalBackend for the DAV collection */
		$context->registerServiceAlias('principalBackend', Principal::class);

		$context->registerCapability(Capabilities::class);

		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScriptsListener::class);
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, LoadAdditionalScriptsListener::class);
		$context->registerEventListener(NodeRenamedEvent::class, NodeRenamedListener::class);

		$context->registerService(MountProvider::class, function (ContainerInterface $c): MountProvider {
			/** @var IAppConfig $config */
			$config = $c->get(IAppConfig::class);
			$allowRootShare = $config->getValueBool('repos', 'allow_root_share', true);
			$enableEncryption = $config->getValueBool('repos', 'enable_encryption');

			return new MountProvider(
				$c->get(FolderManager::class),
				$c->get(ACLManagerFactory::class),
				$c->get(IUserSession::class),
				$c->get(IRequest::class),
				$c->get(IMountProviderCollection::class),
				$c->get(IDBConnection::class),
				$c->get(FolderStorageManager::class),
				$allowRootShare,
				$enableEncryption
			);
		});

		$context->registerService(TrashBackend::class, function (ContainerInterface $c): TrashBackend {
			$trashBackend = new TrashBackend(
				$c->get(FolderManager::class),
				$c->get(TrashManager::class),
				$c->get(ACLManagerFactory::class),
				$c->get(IRootFolder::class),
				$c->get(LoggerInterface::class),
				$c->get(IUserManager::class),
				$c->get(IUserSession::class),
				$c->get(MountProvider::class),
				$c->get(IMountManager::class),
				$c->get(IStorageFactory::class),
			);
			$hasVersionApp = interface_exists(\OCA\Files_Versions\Versions\IVersionBackend::class);
			if ($hasVersionApp) {
				$trashBackend->setVersionsBackend($c->get(VersionsBackend::class));
			}

			return $trashBackend;
		});

		$context->registerService(ExpireGroupBase::class, function (ContainerInterface $c): ExpireGroupBase {
			// Multiple implementation of this class exists depending on if the trash and versions
			// backends are enabled.

			$hasVersionApp = interface_exists(\OCA\Files_Versions\Versions\IVersionBackend::class);
			$hasTrashApp = interface_exists(\OCA\Files_Trashbin\Trash\ITrashBackend::class);

			if ($hasVersionApp && $hasTrashApp) {
				return new ExpireGroupVersionsTrash(
					$c->get(GroupVersionsExpireManager::class),
					$c->get(IEventDispatcher::class),
					$c->get(TrashBackend::class),
					$c->get(Expiration::class)
				);
			}

			if ($hasVersionApp) {
				return new ExpireGroupVersions(
					$c->get(GroupVersionsExpireManager::class),
					$c->get(IEventDispatcher::class),
				);
			}

			if ($hasTrashApp) {
				return new ExpireGroupTrash(
					$c->get(TrashBackend::class),
					$c->get(Expiration::class)
				);
			}

			return new ExpireGroupBase();
		});

		$context->registerService(\OCA\Repos\BackgroundJob\ExpireGroupVersions::class, function (ContainerInterface $c): TimedJob {
			if (interface_exists(\OCA\Files_Versions\Versions\IVersionBackend::class)) {
				return new ExpireGroupVersionsJob(
					$c->get(ITimeFactory::class),
					$c->get(GroupVersionsExpireManager::class),
					$c->get(IAppConfig::class),
					$c->get(FolderManager::class),
					$c->get(LoggerInterface::class),
				);
			}

			return new ExpireGroupPlaceholder($c->get(ITimeFactory::class));
		});

		$context->registerService(\OCA\Repos\BackgroundJob\ExpireGroupTrash::class, function (ContainerInterface $c): TimedJob {
			if (interface_exists(\OCA\Files_Trashbin\Trash\ITrashBackend::class)) {
				return new ExpireGroupTrashJob(
					$c->get(TrashBackend::class),
					$c->get(Expiration::class),
					$c->get(IAppConfig::class),
					$c->get(ITimeFactory::class)
				);
			}

			return new ExpireGroupPlaceholder($c->get(ITimeFactory::class));
		});

		$context->registerServiceAlias(IUserMappingManager::class, UserMappingManager::class);

		$context->registerMiddleware(AuthorizedAdminSettingMiddleware::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (IMountProviderCollection $mountProviderCollection, CacheListener $cacheListener, IEventDispatcher $eventDispatcher): void {
			$mountProviderCollection->registerProvider(Server::get(MountProvider::class));

			$eventDispatcher->addListener(GroupDeletedEvent::class, function (GroupDeletedEvent $event): void {
				Server::get(FolderManager::class)->deleteGroup($event->getGroup()->getGID());
			});
			$cacheListener->listen();
		});
	}
}
