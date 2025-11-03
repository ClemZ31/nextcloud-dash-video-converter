<!--
ConversionsContent 
  - Affiche le contenu principal de l’app selon la section active (conversions et paramètres).
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

			<!-- Liste des conversions -->
			<div v-if="section === 'conversions'" class="conversions-list">
				<!-- Loading state -->
				<NcLoadingIcon v-if="loading && jobs.length === 0" :size="64" />

				<!-- Empty state -->
				<NcEmptyContent
					v-else-if="!loading && jobs.length === 0"
					:name="t('video_converter_fm', 'Aucune conversion')"
					:description="t('video_converter_fm', 'Les conversions lancées depuis l\'explorateur de fichiers apparaîtront ici.')">
					<template #icon>
						<NcIconSvgWrapper :svg="convertIcon" />
					</template>
				</NcEmptyContent>

				<!-- Jobs list -->
				<div v-else class="jobs-container">
					<div v-for="job in jobs" :key="job.id" class="job-item">
						<div class="job-icon">
							<NcIconSvgWrapper :svg="convertIcon" :size="32" />
						</div>
						
						<div class="job-info">
							<div class="job-title">{{ getFileName(job.input_path) }}</div>
							<div class="job-formats">
								{{ t('video_converter_fm', 'Format: {format}', { format: getFormats(job.output_formats) }) }}
							</div>
							<div class="job-meta">
								<span class="job-date">{{ formatDate(job.created_at) }}</span>
								<span v-if="job.completed_at" class="job-duration">
									· {{ formatDuration(job.created_at, job.completed_at) }}
								</span>
							</div>
						</div>

					<div class="job-status">
						<!-- Barre de progression pour tous les jobs en cours (pending ou processing avec progress) -->
						<div v-if="job.status === 'pending' || (job.status === 'processing' && job.progress > 0 && job.progress < 100)" class="progress-container">
							<NcProgressBar
								:value="job.progress"
								:error="false"
								size="medium" />
							<span class="progress-text">{{ job.progress }}%</span>
						</div>
						
						<!-- Spinner uniquement si processing SANS progression (début de traitement) -->
						<NcLoadingIcon v-else-if="job.status === 'processing' && job.progress === 0" :size="20" class="job-spinner" />
						
						<div class="status-badge" :class="'status-' + job.status">
							{{ getStatusLabel(job.status) }}
						</div>
						<div v-if="job.error_message" class="job-error">
							{{ job.error_message }}
						</div>
					</div>
					</div>
				</div>
			</div>

			<!-- État vide pour les paramètres -->
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
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { NcAppContent, NcEmptyContent, NcIconSvgWrapper, NcLoadingIcon, NcProgressBar } from '@nextcloud/vue'
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

// Jobs state
const jobs = ref([])
const loading = ref(true)
let pollingInterval = null

// Fetch jobs from API
const fetchJobs = async () => {
	try {
		const url = generateUrl('/apps/video_converter_fm') + '/api/jobs'
		console.log('Fetching jobs from:', url)
		const response = await axios.get(url)
		console.log('Jobs response:', response.data)
		jobs.value = response.data.jobs || []
		loading.value = false
	} catch (error) {
		console.error('Failed to fetch jobs:', error)
		loading.value = false
	}
}

// Format helpers
const getFileName = (path) => {
	if (!path) return ''
	return path.split('/').pop()
}

const getFormats = (outputFormats) => {
	if (!outputFormats) return ''
	try {
		const formats = typeof outputFormats === 'string' ? JSON.parse(outputFormats) : outputFormats
		if (Array.isArray(formats)) {
			return formats.map(f => f.type?.toUpperCase() || '').filter(Boolean).join(', ')
		}
		return formats.type?.toUpperCase() || ''
	} catch (e) {
		return outputFormats
	}
}

const formatDate = (dateString) => {
	if (!dateString) return ''
	const date = new Date(dateString)
	const now = new Date()
	const diffMs = now - date
	const diffMins = Math.floor(diffMs / 60000)
	const diffHours = Math.floor(diffMs / 3600000)
	const diffDays = Math.floor(diffMs / 86400000)

	if (diffMins < 1) return t('video_converter_fm', 'À l\'instant')
	if (diffMins < 60) return t('video_converter_fm', 'Il y a {n} min', { n: diffMins })
	if (diffHours < 24) return t('video_converter_fm', 'Il y a {n} h', { n: diffHours })
	if (diffDays < 7) return t('video_converter_fm', 'Il y a {n} j', { n: diffDays })
	
	return date.toLocaleDateString()
}

const formatDuration = (startString, endString) => {
	if (!startString || !endString) return ''
	const start = new Date(startString)
	const end = new Date(endString)
	const diffSeconds = Math.floor((end - start) / 1000)
	
	if (diffSeconds < 60) return t('video_converter_fm', '{n} sec', { n: diffSeconds })
	const minutes = Math.floor(diffSeconds / 60)
	const seconds = diffSeconds % 60
	return t('video_converter_fm', '{m} min {s} sec', { m: minutes, s: seconds })
}

const getStatusLabel = (status) => {
	const labels = {
		pending: t('video_converter_fm', 'En attente'),
		processing: t('video_converter_fm', 'En cours'),
		completed: t('video_converter_fm', 'Terminé'),
		failed: t('video_converter_fm', 'Échoué'),
	}
	return labels[status] || status
}

// Lifecycle
onMounted(() => {
	fetchJobs()
	// Poll every 5 seconds
	pollingInterval = setInterval(fetchJobs, 5000)
})

onUnmounted(() => {
	if (pollingInterval) {
		clearInterval(pollingInterval)
	}
})
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

.conversions-list {
	flex: 1;
	overflow-y: auto;
	padding: 12px 0;
}

.jobs-container {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.job-item {
	display: flex;
	align-items: flex-start;
	gap: 16px;
	padding: 16px;
	background: var(--color-background-hover);
	border-radius: var(--border-radius-large);
	transition: background-color 0.2s ease;
}

.job-item:hover {
	background: var(--color-primary-element-light);
}

.job-icon {
	flex-shrink: 0;
	width: 32px;
	height: 32px;
	display: flex;
	align-items: center;
	justify-content: center;
	opacity: 0.7;
}

.job-info {
	flex: 1;
	min-width: 0;
}

.job-title {
	font-weight: bold;
	font-size: 16px;
	margin-bottom: 4px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.job-formats {
	font-size: 14px;
	color: var(--color-text-maxcontrast);
	margin-bottom: 4px;
}

.job-meta {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.job-duration {
	margin-left: 4px;
}

.job-status {
	flex-shrink: 0;
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	gap: 8px;
	min-width: 150px;
}

.job-spinner {
	align-self: center;
	opacity: 0.8;
}

.progress-container {
	display: flex;
	align-items: center;
	gap: 8px;
	width: 100%;
	margin-bottom: 8px;
}

.progress-text {
	font-size: 13px;
	font-weight: 600;
	color: var(--color-primary-element);
	min-width: 40px;
	text-align: right;
}

.status-badge {
	padding: 4px 12px;
	border-radius: var(--border-radius-pill);
	font-size: 12px;
	font-weight: 600;
	text-align: center;
	white-space: nowrap;
}

.status-pending {
	background: var(--color-warning);
	color: var(--color-primary-element-text);
}

.status-processing {
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
}

.status-completed {
	background: var(--color-success);
	color: white;
}

.status-failed {
	background: var(--color-error);
	color: white;
}

.job-error {
	font-size: 12px;
	color: var(--color-error);
	text-align: right;
	margin-top: 4px;
}
</style>
