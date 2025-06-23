<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Listeners;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<LoadAdditionalScriptsEvent|BeforeTemplateRenderedEvent>
 */
class LoadAdditionalScriptsListener implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalScriptsEvent && !$event instanceof BeforeTemplateRenderedEvent) {
			return;
		}

		\OCP\Util::addInitScript('groupfolders', 'groupfolders-init');
		\OCP\Util::addScript('groupfolders', 'groupfolders-files');
	}
}
