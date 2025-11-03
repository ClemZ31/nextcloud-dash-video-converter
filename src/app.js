/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateUrl } from '@nextcloud/router'
import { getLoggerBuilder } from '@nextcloud/logger'
import { createApp } from 'vue'
import { createRouter, createWebHashHistory } from 'vue-router'
import ConversionsApp from './views/ConversionsApp.vue'
import { routes } from './routes.js'

// Configure logger for this app
const logger = getLoggerBuilder()
	.setApp('video_converter_fm')
	.build()

const router = createRouter({
	history: createWebHashHistory(generateUrl('/apps/video_converter_fm')),
	linkActiveClass: 'active',
	routes,
})

const app = createApp(ConversionsApp)
app.use(router)
app.mount('#content')
