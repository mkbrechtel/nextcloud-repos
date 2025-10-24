/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
/* eslint-disable */
import { t } from '@nextcloud/l10n'
import { View, getNavigation, registerFileAction } from '@nextcloud/files'

import { action as openGroupfolderAction} from './actions/openGroupfolderAction'
import { getContents } from './services/groupfolders'
import GroupFolderSvg from '../img/app-dark.svg?raw'

registerFileAction(openGroupfolderAction)

const Navigation = getNavigation()
Navigation.register(new View({
	id: appName,
	name: t('repos', 'Repositories'),
	caption: t('repos', 'List of Repositories.'),

	emptyTitle: t('repos', 'No Repositories yet'),
	emptyCaption: t('repos', 'Repositories will show up here'),

	icon: GroupFolderSvg,
	order: 20,

	columns: [],

	getContents,
}))
