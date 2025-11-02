<!--
ConversionsContent 
  - Affiche le contenu principal de l’app selon la section active (conversions | settings).
  - Utilise NcEmptyContent en attendant l’implémentation de la liste de jobs et des réglages.
  - Navigation et layout gérés par ConversionsNavigation/NcAppContent.
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent>
		<div class="conversions-content">
			<h1 class="conversions-content__heading">
				{{ pageTitle }}
			</h1>

			<!-- État vide pour les conversions -->
			<!-- TODO : Remplacer par la liste des jobs (en cours/terminés) -->
			<NcEmptyContent
				v-if="section === 'conversions'"
            	:name="t('video_converter_fm', 'Conversions')"
            	:description="t('video_converter_fm', 'Les conversions en cours seront affichées ici.')">
				<template #icon>
					<NcIconSvgWrapper :svg="convertIcon" />
				</template>
			</NcEmptyContent>

			<!-- État vide pour les paramètres -->
			<!-- TODO : Remplacer par le formulaire des réglages (se fier au prototype UI) -->
			<NcEmptyContent
				v-else-if="section === 'settings'"
            	:name="t('video_converter_fm', 'Paramètres')"
            	:description="t('video_converter_fm', 'Contenu des paramètres à définir.')">
				<template #icon>
					<img
						alt=""
						:src="coreSettingsIcon"
						class="empty-content-icon">
				</template>
			</NcEmptyContent>
		</div>
	</NcAppContent>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import { NcAppContent, NcEmptyContent, NcIconSvgWrapper } from '@nextcloud/vue'
import convertIcon from '../../img/convert_icon.svg?raw'

const route = useRoute()
const section = computed(() => route.params.section || 'conversions')

const pageTitle = computed(() => {
	if (section.value === 'settings') {
    	return t('video_converter_fm', 'Paramètres')
	}
	return t('video_converter_fm', 'Conversions en cours')
})

const coreSettingsIcon = generateUrl('/core/img/actions/settings.svg')
</script>

<style scoped>
.conversions-content {
	display: flex;
	flex-direction: column;
	height: 100%;
	width: min(100%, 924px);
	max-width: 924px;
	margin: 0 auto;
	padding-inline: 12px;
}

.conversions-content__heading {
	font-weight: bold;
	font-size: 20px;
	line-height: 44px;
	margin-top: 1px;
	margin-inline: calc(2 * var(--app-navigation-padding, 8px) + 44px) var(--app-navigation-padding, 8px);
}

.empty-content-icon {
	width: 64px;
	height: 64px;
	opacity: 0.5;
    filter: var(--background-invert-if-dark);
}
</style>
