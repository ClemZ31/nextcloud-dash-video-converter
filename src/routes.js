/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import ConversionsContent from './views/ConversionsContent.vue'
import ConversionsNavigation from './views/ConversionsNavigation.vue'

export const routes = [
	{
		path: '/',
		redirect: '/conversions',
	},
	{
		path: '/:section',
		components: {
			default: ConversionsContent,
			navigation: ConversionsNavigation,
		},
		props: {
			default: true,
		},
	},
]
