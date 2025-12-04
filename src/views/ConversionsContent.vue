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
				<!-- Loading state (spinner global si loading initial OU rechargement) -->
				<div v-if="loading" class="loading-container">
					<NcLoadingIcon :size="64" />
				</div>

				<!-- Empty state -->
				<NcEmptyContent
					v-else-if="jobs.length === 0"
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
							{{ formatLabel(job.output_formats) }}
						</div>
						<div v-if="getOutputPath(job.output_formats) && job.output_folder_exists" class="job-output-path">
							<span>{{ t('video_converter_fm', 'Sortie') }}: {{ getOutputPath(job.output_formats) }}</span>
							<a 
								:href="getOutputFolderUrl(job.output_formats)"
								class="open-folder-link"
								:title="t('video_converter_fm', 'Ouvrir le dossier')"
							>
								🔗
							</a>
						</div>
						<div class="job-meta">
							<span class="job-date">{{ formatDate(job.created_at) }}</span>
							<span v-if="job.completed_at" class="job-duration">
								· {{ formatDuration(job.created_at, job.completed_at) }}
							</span>
						</div>
					</div>					<div class="job-status">
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

						<!-- Actions -->
						<div class="job-actions">
							<!-- Show delete button only if job belongs to current user -->
							<NcButton
								v-if="canDeleteJob(job) && getActionButton(job)"
								type="tertiary"
								size="small"
								class="delete-btn"
								@click="onJobAction(job)">
								{{ getActionButton(job).label }}
							</NcButton>
							<!-- Show username badge if viewing all jobs and job belongs to someone else -->
							<span v-if="showAllJobs && job.user_id !== currentUserId" class="job-owner-badge">
								{{ job.user_id }}
							</span>
						</div>
					</div>
					</div>
				</div>
			</div>
			
			<!-- Dialog de confirmation suppression/annulation -->
			<NcDialog
				v-if="dialog.show"
				:name="dialog.title"
				:container="null"
				@close="dialog.cancel">
				<p>{{ dialog.message }}</p>
				<div v-show="dialog.case === 'output_exists'" class="delete-files-checkbox">
					<label class="native-checkbox">
						<input
							type="checkbox"
							:checked="deleteFilesChecked"
							@change="(e) => { deleteFilesChecked = e.target.checked; console.log('Checkbox changed:', deleteFilesChecked) }">
						{{ t('video_converter_fm', 'Supprimer aussi les fichiers de sortie') }}
					</label>
				</div>
				<template #actions>
					<NcButton type="secondary" @click="dialog.cancel">
						{{ t('video_converter_fm', 'Annuler') }}
					</NcButton>
					<NcButton type="primary" :loading="dialogLoading" @click="dialog.confirm">
						{{ t('video_converter_fm', 'Confirmer') }}
					</NcButton>
				</template>
			</NcDialog>

			<!-- Paramètres -->
			<div v-else-if="section === 'settings'" class="settings-section">
				<h2>{{ t('video_converter_fm', 'Paramètres') }}</h2>
				
				<div class="setting-item">
					<NcCheckboxRadioSwitch
						v-model="showAllJobs"
						@update:checked="onToggleShowAll">
						{{ t('video_converter_fm', 'Voir toutes les conversions') }}
					</NcCheckboxRadioSwitch>
					<p class="setting-description">
						{{ t('video_converter_fm', 'Affiche les conversions de tous les utilisateurs dans l\'onglet Conversions') }}
					</p>
				</div>
			</div>
		</div>
	</NcAppContent>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { NcAppContent, NcEmptyContent, NcIconSvgWrapper, NcLoadingIcon, NcProgressBar, NcButton, NcCheckboxRadioSwitch, NcDialog } from '@nextcloud/vue'
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
const currentUserId = ref(null)
const showAllJobs = ref(localStorage.getItem('video_converter_fm_show_all_jobs') === 'true')
let pollingInterval = null

// Fetch jobs from API
const fetchJobs = async () => {
	try {
		const endpoint = showAllJobs.value ? '/api/jobs/all' : '/api/jobs'
		const url = generateUrl('/apps/video_converter_fm') + endpoint
		console.log('Fetching jobs from:', url)
		const response = await axios.get(url)
		console.log('Jobs response:', response.data)
		jobs.value = response.data.jobs || []
		
		// Get current user from first call to /api/jobs (user-specific)
		if (!currentUserId.value && response.data.jobs && response.data.jobs.length > 0) {
			const userUrl = generateUrl('/apps/video_converter_fm') + '/api/jobs'
			const userResponse = await axios.get(userUrl)
			if (userResponse.data.jobs && userResponse.data.jobs.length > 0) {
				currentUserId.value = userResponse.data.jobs[0].user_id
			}
		}
		
		loading.value = false
	} catch (error) {
		console.error('Failed to fetch jobs:', error)
		loading.value = false
	}
}

// Toggle show all jobs
const onToggleShowAll = (value) => {
	showAllJobs.value = value
	localStorage.setItem('video_converter_fm_show_all_jobs', value.toString())
	// Afficher le loading pendant le fetch
	loading.value = true
	fetchJobs()
}

// Format helpers
const getFileName = (path) => {
	if (!path) return ''
	return path.split('/').pop()
}

const formatMap = {
	dash: () => t('video_converter_fm', 'DASH (MPD)'),
	hls: () => t('video_converter_fm', 'HLS (M3U8)'),
}

const normalizeFormat = (value) => {
	if (typeof value !== 'string') {
		return null
	}
	const lower = value.toLowerCase()
	if (lower === 'dash' || lower === 'mpd') {
		return 'dash'
	}
	if (lower === 'hls' || lower === 'm3u8') {
		return 'hls'
	}
	return null
}

const extractFormats = (payload) => {
	if (!payload) {
		return []
	}
	let parsed = payload
	if (typeof parsed === 'string') {
		try {
			parsed = JSON.parse(parsed)
		} catch (error) {
			return []
		}
	}
	const result = []
	const pushUnique = (format) => {
		if (format && !result.includes(format)) {
			result.push(format)
		}
	}
	if (Array.isArray(parsed)) {
		parsed.map(normalizeFormat).forEach(pushUnique)
	} else if (parsed && typeof parsed === 'object') {
		if (Array.isArray(parsed.selected_formats)) {
			parsed.selected_formats.map(normalizeFormat).forEach(pushUnique)
		}
		if (Array.isArray(parsed.formats)) {
			parsed.formats.map(normalizeFormat).forEach(pushUnique)
		}
		if (parsed.profile && Array.isArray(parsed.profile.formats)) {
			parsed.profile.formats.map(normalizeFormat).forEach(pushUnique)
		}
		if (parsed.profile && parsed.profile.selected_formats && typeof parsed.profile.selected_formats === 'object') {
			Object.keys(parsed.profile.selected_formats)
				.filter((key) => parsed.profile.selected_formats[key])
				.map(normalizeFormat)
				.forEach(pushUnique)
		}
		if (parsed.selected_formats && typeof parsed.selected_formats === 'object' && !Array.isArray(parsed.selected_formats)) {
			Object.keys(parsed.selected_formats)
				.filter((key) => parsed.selected_formats[key])
				.map(normalizeFormat)
				.forEach(pushUnique)
		}
		if (typeof parsed.type === 'string') {
			pushUnique(normalizeFormat(parsed.type))
		}
	}
	return result
}

const formatLabel = (outputFormats) => {
	const formats = extractFormats(outputFormats)
	if (formats.length === 0) {
		return t('video_converter_fm', 'Format: {format}', { format: t('video_converter_fm', 'Unknown') })
	}
	const readable = formats
		.map((format) => (formatMap[format] ? formatMap[format]() : format.toUpperCase()))
		.filter(Boolean)
	if (readable.length === 1) {
		return t('video_converter_fm', 'Format: {format}', { format: readable[0] })
	}
	return t('video_converter_fm', 'Formats: {formats}', { formats: readable.join(' + ') })
}

// Extraire le chemin de sortie Nextcloud depuis output_formats
const getOutputPath = (outputFormats) => {
	if (!outputFormats) return null
	let parsed = outputFormats
	if (typeof parsed === 'string') {
		try {
			parsed = JSON.parse(parsed)
		} catch (e) {
			return null
		}
	}
	// Retourner output_nc_path (chemin Nextcloud relatif)
	if (parsed.output_nc_path) {
		return parsed.output_nc_path
	}
	// Fallback sur output_folder si pas de chemin NC
	if (parsed.output_folder) {
		return parsed.output_folder
	}
	return null
}

// Générer l'URL pour ouvrir le dossier de sortie dans Nextcloud Files
const getOutputFolderUrl = (outputFormats) => {
	const path = getOutputPath(outputFormats)
	if (!path) return '#'
	// Encoder le chemin pour l'URL
	const encodedPath = encodeURIComponent(path)
	return `/apps/files/?dir=${encodedPath}`
}

const formatDate = (dateString) => {
	if (!dateString) return ''
	// Le serveur stocke en UTC, on ajoute 'Z' pour que JS l'interprète correctement
	const utcDateString = dateString.includes('Z') || dateString.includes('+') 
		? dateString 
		: dateString.replace(' ', 'T') + 'Z'
	const date = new Date(utcDateString)
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
	// Le serveur stocke en UTC
	const utcStart = startString.includes('Z') || startString.includes('+') 
		? startString 
		: startString.replace(' ', 'T') + 'Z'
	const utcEnd = endString.includes('Z') || endString.includes('+') 
		? endString 
		: endString.replace(' ', 'T') + 'Z'
	const start = new Date(utcStart)
	const end = new Date(utcEnd)
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

// Check if current user can delete a job
const canDeleteJob = (job) => {
	// User can only delete their own jobs
	return job.user_id === currentUserId.value
}

// Delete a job
const onDelete = async (job) => {
	try {
		if (!confirm(t('video_converter_fm', 'Supprimer ce job ?'))) return
		const url = generateUrl('/apps/video_converter_fm') + `/api/jobs/${job.id}`
		await axios.delete(url)
		// Remove from local state immediately
		jobs.value = jobs.value.filter(j => j.id !== job.id)
	} catch (e) {
		console.error('Failed to delete job', e)
		alert(t('video_converter_fm', 'Échec de la suppression du job'))
	}
}

// Dialog state
const dialog = ref({ show: false, title: '', message: '', case: '', confirm: null, cancel: null })
const dialogLoading = ref(false)
const deleteFilesChecked = ref(true)

function getActionButton(job) {
    if (job.status === 'completed' || job.status === 'failed') {
        return { label: t('video_converter_fm', 'Supprimer'), type: 'delete' }
    }
    if (job.status === 'processing' || job.status === 'pending') {
        return { label: t('video_converter_fm', 'Annuler'), type: 'cancel' }
    }
    return null
}

async function onJobAction(job) {
    const action = getActionButton(job)
    if (!action) return
    dialogLoading.value = true
    deleteFilesChecked.value = true // Reset checkbox
    try {
        const url = generateUrl(`/apps/video_converter_fm/api/jobs/${job.id}/action/${action.type}`)
        const res = await axios.get(url)
        dialog.value = {
            show: true,
            title: action.label,
            message: res.data.message,
            case: res.data.case,
            confirm: async () => {
                dialogLoading.value = true
                await axios.post(url, { deleteFiles: res.data.case === 'output_exists' && deleteFilesChecked.value })
                dialog.value.show = false
                dialogLoading.value = false
                fetchJobs()
            },
            cancel: () => {
                dialog.value.show = false
            }
        }
    } catch (e) {
        dialog.value = {
            show: true,
            title: t('video_converter_fm', 'Erreur'),
            message: t('video_converter_fm', 'Impossible de vérifier le statut du job.'),
            case: 'error',
            confirm: () => { dialog.value.show = false },
            cancel: () => { dialog.value.show = false }
        }
    }
    dialogLoading.value = false
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

.loading-container {
	display: flex;
	justify-content: center;
	align-items: center;
	padding: 48px;
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

.job-output-path {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	margin-bottom: 4px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	font-family: monospace;
	display: flex;
	align-items: center;
	gap: 6px;
}

.job-output-path span {
	overflow: hidden;
	text-overflow: ellipsis;
}

.open-folder-link {
	text-decoration: none;
	font-size: 14px;
	opacity: 0.7;
	transition: opacity 0.2s;
	flex-shrink: 0;
}

.open-folder-link:hover {
	opacity: 1;
}

.job-meta {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.job-creator {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	margin-top: 4px;
	font-style: italic;
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

.job-actions {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-top: 8px;
}

.job-owner-badge {
	font-size: 11px;
	padding: 2px 8px;
	background: var(--color-background-dark);
	border-radius: var(--border-radius-pill);
	color: var(--color-text-maxcontrast);
	font-weight: 500;
}

.settings-section {
	padding: 24px;
}

.settings-section h2 {
	font-size: 18px;
	font-weight: 600;
	margin-bottom: 24px;
}

.setting-item {
	margin-bottom: 24px;
	padding: 16px;
	background: var(--color-background-hover);
	border-radius: var(--border-radius-large);
}

.setting-description {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	margin-top: 8px;
	margin-bottom: 0;
}

.no-admin-notice {
	font-size: 14px;
	color: var(--color-text-maxcontrast);
	font-style: italic;
}

.delete-files-checkbox {
	margin-top: 12px;
	margin-bottom: 12px;
}

.native-checkbox {
	display: flex;
	align-items: center;
	gap: 8px;
	cursor: pointer;
	font-size: 14px;
}

.native-checkbox input[type="checkbox"] {
	width: 18px;
	height: 18px;
	cursor: pointer;
	accent-color: var(--color-primary-element);
}
</style>
