<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppNavigation :aria-label="t('video_converter_fm', 'Conversions')">
		<template #list>
			<NcAppNavigationItem
				:to="{ path: '/conversions' }"
				:name="t('video_converter_fm', 'Conversions en cours')"
				:aria-label="t('video_converter_fm', 'Conversions en cours')">
				<template #icon>
					<img
						alt=""
						:src="appIconUrl"
						class="navigation-icon"
						role="presentation">
				</template>
			</NcAppNavigationItem>

			<NcAppNavigationItem
				:to="{ path: '/settings' }"
				:name="t('video_converter_fm', 'Paramètres')"
				:aria-label="t('video_converter_fm', 'Paramètres')">
				<template #icon>
					<img
						alt=""
						:src="coreSettingsIcon"
						class="navigation-icon"
						role="presentation">
				</template>
			</NcAppNavigationItem>
		</template>
	</NcAppNavigation>
</template>

<script setup>
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import { NcAppNavigation, NcAppNavigationItem } from '@nextcloud/vue'

const appIconUrl = generateUrl('/apps/video_converter_fm/img/convert_icon.svg')
const coreSettingsIcon = generateUrl('/core/img/actions/settings.svg')
</script>

<style scoped>
.navigation-icon {
	height: 20px;
	width: 20px;
	filter: var(--background-invert-if-dark);
}

.active .navigation-icon {
	filter: var(--primary-invert-if-dark);
}
</style>
