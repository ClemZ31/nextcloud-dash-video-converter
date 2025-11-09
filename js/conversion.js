/**
 * Integration script for the video conversion action in Nextcloud Files.
 * Modal v2 with simple and advanced tabs aligned with the design mockup.
 *
 * Prepares future DASH and HLS features while staying compatible with the current backend.
 */

;(function () {
    'use strict'

    const STORAGE_KEY = 'video_converter_fm::modal_defaults'
    const DEFAULT_DURATION_SECONDS = 900
    const DEFAULT_SETTINGS = {
        formats: {
            dash: true,
            hls: true,
        },
        renditions: {
            '1080p': { label: '1080p', enabled: true, videoBitrate: 5300, audioBitrate: 128 },
            '720p': { label: '720p', enabled: true, videoBitrate: 3200, audioBitrate: 128 },
            '480p': { label: '480p', enabled: true, videoBitrate: 1250, audioBitrate: 96 },
            '360p': { label: '360p', enabled: true, videoBitrate: 700, audioBitrate: 96 },
            '240p': { label: '240p', enabled: true, videoBitrate: 400, audioBitrate: 64 },
            '144p': { label: '144p', enabled: true, videoBitrate: 160, audioBitrate: 64 },
        },
        videoCodec: 'libx264',
        audioCodec: 'aac',
        preset: 'slow',
        keyframeInterval: 100,
        segmentDuration: 4,
        subtitles: true,
        priority: '0',
        dash: {
            useTimeline: true,
            useTemplate: true,
        },
        hls: {
            version: 7,
            independentSegments: true,
            deleteSegments: false,
            strftimeMkdir: true,
        },
    }

    const RENDITION_PRESETS = [
        { id: '1080p', label: '1080p', resolution: '1920x1080', defaultVideo: 5300, defaultAudio: 128, defaultEnabled: true },
        { id: '720p', label: '720p', resolution: '1280x720', defaultVideo: 3200, defaultAudio: 128, defaultEnabled: true },
        { id: '480p', label: '480p', resolution: '854x480', defaultVideo: 1250, defaultAudio: 96, defaultEnabled: true },
        { id: '360p', label: '360p', resolution: '640x360', defaultVideo: 700, defaultAudio: 96, defaultEnabled: true },
        { id: '240p', label: '240p', resolution: '426x240', defaultVideo: 400, defaultAudio: 64, defaultEnabled: true },
        { id: '144p', label: '144p', resolution: '256x144', defaultVideo: 160, defaultAudio: 64, defaultEnabled: true },
    ]

    let currentDialog = null
    let currentOverlay = null
    let currentFile = null
    let currentContext = null
    let activeTab = 'simple'
    let isSubmitting = false
    let escKeyListener = null
    let cachedDefaults = null

    console.log('[video_converter_fm] conversion integration script (modal v2) loaded')

    function tnc(app, s) {
        try {
            if (typeof t === 'function') {
                return t(app, s)
            }
        } catch (e) {
            // ignore fallback to plain string
        }
        return s
    }

    function ensureStyles() {
        if (document.getElementById('video-converter-modal-styles')) {
            return
        }
        const style = document.createElement('style')
        style.id = 'video-converter-modal-styles'
        style.type = 'text/css'
        style.textContent = `
            .vc-overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.35); z-index: 10000; }
            .vc-modal { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; z-index: 10001; }
            .vc-modal__dialog { width: min(520px, calc(100vw - 32px)); max-height: calc(100vh - 32px); background: var(--color-main-background, #fff); border-radius: 8px; box-shadow: 0 12px 32px rgba(0,0,0,0.25); display: flex; flex-direction: column; overflow: hidden; color-scheme: var(--nextcloud-color-scheme, light dark); }
            .vc-modal__header { padding: 16px; border-bottom: 1px solid var(--color-border, #d1d9e0); display: flex; align-items: center; justify-content: space-between; }
            .vc-modal__title { margin: 0; font-size: 16px; font-weight: 600; }
            .vc-close-btn { border: none; background: transparent; cursor: pointer; font-size: 18px; color: var(--color-text-lighter, #6a737d); }
            .vc-tabs { display: flex; border-bottom: 1px solid var(--color-border, #d1d9e0); }
            .vc-tab-btn { flex: 1; padding: 10px 12px; background: none; border: none; border-bottom: 2px solid transparent; cursor: pointer; font-weight: 600; color: var(--color-text-lighter, #6a737d); }
            .vc-tab-btn--active { color: var(--color-primary, #0082c9); border-bottom-color: var(--color-primary, #0082c9); }
            .vc-modal__body { padding: 16px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; }
            .vc-tabpanel { display: none; flex-direction: column; gap: 12px; }
            .vc-tabpanel--active { display: flex; }
            .vc-section { display: flex; flex-direction: column; gap: 8px; }
            .vc-section__title { margin: 0; font-size: 14px; font-weight: 600; color: var(--color-text-maxcontrast, #1a2026); }
            .vc-summary-list { margin: 0; padding-left: 18px; display: flex; flex-direction: column; gap: 4px; font-size: 13px; }
            .vc-estimation-box { font-size: 13px; padding: 12px; border: 1px solid var(--color-border, #d1d9e0); border-radius: 6px; background: var(--color-background-hover, #f5f6f7); }
            .vc-warning { font-size: 13px; padding: 8px 10px; border-radius: 6px; border: 1px solid var(--color-warning, #f0a500); color: var(--color-warning, #f0a500); background: rgba(240,165,0,0.08); }
            .vc-button-row { display: flex; flex-wrap: wrap; gap: 8px; }
            .vc-button { padding: 8px 12px; border-radius: 6px; border: 1px solid var(--color-border, #d1d9e0); background: var(--color-main-background, #fff); cursor: pointer; font-size: 13px; }
            .vc-button--primary { background: var(--color-primary, #0082c9); color: var(--color-primary-text, #fff); border-color: var(--color-primary, #0082c9); }
            .vc-modal__footer { padding: 12px 16px; border-top: 1px solid var(--color-border, #d1d9e0); display: flex; justify-content: flex-end; gap: 8px; }
            .vc-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
            .vc-form-field { display: flex; flex-direction: column; gap: 4px; font-size: 13px; }
            .vc-form-field input[type="number"], .vc-form-field select { padding: 6px 8px; border-radius: 6px; border: 1px solid var(--color-border, #d1d9e0); background: var(--color-main-background, #fff); }
            .vc-format-toggle { display: flex; flex-direction: column; gap: 6px; font-size: 13px; }
            .vc-rendition-list { display: flex; flex-direction: column; gap: 8px; }
            .vc-rendition-item { border: 1px solid var(--color-border, #d1d9e0); border-radius: 6px; padding: 10px; display: flex; flex-direction: column; gap: 8px; }
            .vc-rendition-header { display: flex; justify-content: space-between; align-items: center; }
            .vc-rendition-meta { font-size: 12px; color: var(--color-text-lighter, #6a737d); }
            .vc-rendition-inputs { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 8px; font-size: 12px; }
            @media (max-width: 520px) { .vc-modal__dialog { width: calc(100vw - 24px); } }
        `
        document.head.appendChild(style)
    }

    function deepClone(obj) {
        return JSON.parse(JSON.stringify(obj))
    }

    function mergeSettings(base, overrides) {
        const result = deepClone(base)
        if (!overrides || typeof overrides !== 'object') {
            return result
        }
        Object.keys(overrides).forEach((key) => {
            const value = overrides[key]
            if (value && typeof value === 'object' && !Array.isArray(value)) {
                result[key] = mergeSettings(base[key] || {}, value)
            } else {
                result[key] = value
            }
        })
        return result
    }

    function loadDefaults() {
        if (cachedDefaults) {
            return deepClone(cachedDefaults)
        }
        let parsed = null
        try {
            if (typeof window !== 'undefined' && window.localStorage) {
                const raw = window.localStorage.getItem(STORAGE_KEY)
                if (raw) {
                    parsed = JSON.parse(raw)
                }
            }
        } catch (error) {
            console.warn('[video_converter_fm] failed to parse saved defaults', error)
        }
        cachedDefaults = mergeSettings(DEFAULT_SETTINGS, parsed || {})
        return deepClone(cachedDefaults)
    }

    function saveDefaults(settings) {
        try {
            if (typeof window !== 'undefined' && window.localStorage) {
                window.localStorage.setItem(STORAGE_KEY, JSON.stringify(settings))
                cachedDefaults = deepClone(settings)
            }
        } catch (error) {
            console.warn('[video_converter_fm] cannot persist defaults', error)
        }
    }

    function calculateEstimates(settings, durationSeconds = DEFAULT_DURATION_SECONDS) {
        const enabledRenditions = RENDITION_PRESETS
            .map((preset) => {
                const entry = settings.renditions?.[preset.id] || {}
                const enabled = entry.enabled !== undefined ? entry.enabled : preset.defaultEnabled
                return {
                    id: preset.id,
                    label: preset.label,
                    videoBitrate: Number(entry.videoBitrate ?? preset.defaultVideo) || 0,
                    audioBitrate: Number(entry.audioBitrate ?? preset.defaultAudio) || 0,
                    enabled,
                }
            })
            .filter((rendition) => rendition.enabled)

        const totalBitrate = enabledRenditions.reduce((acc, rendition) => {
            return acc + rendition.videoBitrate + rendition.audioBitrate
        }, 0)

        const activeFormats = []
        if (settings.formats?.dash) {
            activeFormats.push('dash')
        }
        if (settings.formats?.hls) {
            activeFormats.push('hls')
        }

        const formatCount = activeFormats.length
        let estimatedSpace = 0
        if (totalBitrate > 0 && formatCount > 0) {
            estimatedSpace = (totalBitrate * durationSeconds) / 8 / 1024 / 1024
            estimatedSpace *= formatCount
        }

        const timePerFormat = 45
        const timeEstimateMin = timePerFormat * formatCount
        const timeEstimateMax = timeEstimateMin + (formatCount > 0 ? 30 : 0)

        return {
            enabledRenditions,
            activeFormats,
            formatCount,
            estimatedSpace: Number(estimatedSpace.toFixed(1)),
            timeEstimateMin,
            timeEstimateMax,
        }
    }

    function formatSummaryLines(settings) {
        const estimates = calculateEstimates(settings)
        const renditionNames = estimates.enabledRenditions
            .map((r) => `${r.label || ''}`)
            .filter(Boolean)
        const summary = []
        summary.push({
            label: tnc('video_converter_fm', 'Active renditions'),
            value: renditionNames.length > 0 ? `${estimates.enabledRenditions.length} - ${renditionNames.join(', ')}` : tnc('video_converter_fm', 'None'),
        })
        const formatsText = estimates.activeFormats.length > 0
            ? estimates.activeFormats.map((f) => (f === 'dash' ? 'DASH (MPD)' : 'HLS (M3U8)')).join(' + ')
            : tnc('video_converter_fm', 'No format selected')
        summary.push({ label: tnc('video_converter_fm', 'Output formats'), value: formatsText })
        summary.push({ label: tnc('video_converter_fm', 'Video codec'), value: settings.videoCodec })
        summary.push({ label: tnc('video_converter_fm', 'Audio codec'), value: settings.audioCodec })
    summary.push({ label: tnc('video_converter_fm', 'FFmpeg preset'), value: settings.preset })
        summary.push({ label: tnc('video_converter_fm', 'Subtitles'), value: settings.subtitles ? tnc('video_converter_fm', 'SRT to WebVTT') : tnc('video_converter_fm', 'Disabled') })
        return { summary, estimates }
    }

    function buildDialogHtml(filename, settings) {
        const renditionMarkup = RENDITION_PRESETS.map((preset) => `
            <div class="vc-rendition-item" data-rendition="${preset.id}">
                <div class="vc-rendition-header">
                    <label>
                        <input type="checkbox" class="vc-rendition-toggle" data-resolution="${preset.id}" />
                        <span>${preset.label}</span>
                    </label>
                    <span class="vc-rendition-meta">${preset.resolution}</span>
                </div>
                <div class="vc-rendition-inputs">
                    <label>
                        ${tnc('video_converter_fm', 'Video bitrate (Kbps)')}
                        <input type="number" min="100" data-role="video" data-resolution="${preset.id}" />
                    </label>
                    <label>
                        ${tnc('video_converter_fm', 'Audio bitrate (Kbps)')}
                        <input type="number" min="32" data-role="audio" data-resolution="${preset.id}" />
                    </label>
                </div>
            </div>
        `).join('')

        return `
            <div class="vc-overlay" id="vc-overlay"></div>
            <div class="vc-modal" id="vc-modal">
                <div class="vc-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="vc-modal-title">
                    <div class="vc-modal__header">
                        <h2 class="vc-modal__title" id="vc-modal-title">${tnc('video_converter_fm', 'Conversion DASH + HLS')}</h2>
                        <button type="button" class="vc-close-btn" data-vc-action="close" aria-label="${tnc('video_converter_fm', 'Close')}">&times;</button>
                    </div>
                    <div class="vc-tabs">
                        <button type="button" class="vc-tab-btn vc-tab-btn--active" data-vc-tab="simple">${tnc('video_converter_fm', 'Simple')}</button>
                        <button type="button" class="vc-tab-btn" data-vc-tab="advanced">${tnc('video_converter_fm', 'Advanced')}</button>
                    </div>
                    <div class="vc-modal__body">
                        <div class="vc-tabpanel vc-tabpanel--active" data-vc-panel="simple">
                            <div class="vc-section">
                                <h3 class="vc-section__title">${tnc('video_converter_fm', 'File')}</h3>
                                <ul class="vc-summary-list">
                                    <li><strong>${filename}</strong></li>
                                </ul>
                            </div>
                            <div class="vc-section">
                                <h3 class="vc-section__title">${tnc('video_converter_fm', 'Default profile')}</h3>
                                <ul class="vc-summary-list" id="vc-simple-summary"></ul>
                            </div>
                            <div class="vc-estimation-box" id="vc-simple-estimation"></div>
                            <div class="vc-button-row">
                                <button type="button" class="vc-button vc-button--primary" data-vc-action="start-simple" data-vc-disable-while-submitting>
                                    ${tnc('video_converter_fm', 'Start default conversion')}
                                </button>
                                <button type="button" class="vc-button" data-vc-tab="advanced">
                                    ${tnc('video_converter_fm', 'Customize...')}
                                </button>
                            </div>
                        </div>
                        <div class="vc-tabpanel" data-vc-panel="advanced">
                            <div class="vc-section">
                                <h3 class="vc-section__title">${tnc('video_converter_fm', 'Output formats')}</h3>
                                <div class="vc-format-toggle">
                                    <label><input type="checkbox" id="vc-format-dash" /> DASH (MPD)</label>
                                    <label><input type="checkbox" id="vc-format-hls" /> HLS (M3U8)</label>
                                </div>
                                <div class="vc-warning vc-warning--error" id="vc-format-warning" hidden>
                                    ${tnc('video_converter_fm', 'Select at least one output format.')}
                                </div>
                            </div>
                            <div class="vc-section">
                                <h3 class="vc-section__title">${tnc('video_converter_fm', 'Renditions')}</h3>
                                <div class="vc-button-row">
                                    <button type="button" class="vc-button" data-vc-action="load-defaults">${tnc('video_converter_fm', 'Load defaults')}</button>
                                    <button type="button" class="vc-button" data-vc-action="save-defaults">${tnc('video_converter_fm', 'Save as default')}</button>
                                </div>
                                <div class="vc-rendition-list">${renditionMarkup}</div>
                            </div>
                            <div class="vc-section">
                                <h3 class="vc-section__title">${tnc('video_converter_fm', 'FFmpeg settings')}</h3>
                                <div class="vc-form-grid">
                                    <div class="vc-form-field">
                                        <label for="vc-video-codec">${tnc('video_converter_fm', 'Video codec')}</label>
                                        <select id="vc-video-codec">
                                            <option value="libx264">H.264 (libx264)</option>
                                            <option value="libx265">H.265 (libx265)</option>
                                            <option value="libvpx-vp9">VP9 (libvpx-vp9)</option>
                                        </select>
                                    </div>
                                    <div class="vc-form-field">
                                        <label for="vc-audio-codec">${tnc('video_converter_fm', 'Audio codec')}</label>
                                        <select id="vc-audio-codec">
                                            <option value="aac">AAC</option>
                                            <option value="opus">Opus</option>
                                            <option value="mp3">MP3</option>
                                        </select>
                                    </div>
                                    <div class="vc-form-field">
                                        <label for="vc-preset">${tnc('video_converter_fm', 'FFmpeg preset')}</label>
                                        <select id="vc-preset">
                                            <option value="ultrafast">ultrafast</option>
                                            <option value="superfast">superfast</option>
                                            <option value="veryfast">veryfast</option>
                                            <option value="fast">fast</option>
                                            <option value="medium">medium</option>
                                            <option value="slow">slow</option>
                                            <option value="slower">slower</option>
                                            <option value="veryslow">veryslow</option>
                                        </select>
                                    </div>
                                    <div class="vc-form-field">
                                        <label>
                                            <input type="checkbox" id="vc-subtitles" /> ${tnc('video_converter_fm', 'Convert subtitles (SRT to WebVTT)')}
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="vc-estimation-box vc-estimation-box--warning" id="vc-advanced-estimation"></div>
                            <div class="vc-button-row">
                                <button type="button" class="vc-button vc-button--primary" data-vc-action="start-advanced" data-vc-disable-while-submitting>
                                    ${tnc('video_converter_fm', 'Start custom conversion')}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="vc-modal__footer">
                        <button type="button" class="vc-button" data-vc-action="cancel" data-vc-disable-while-submitting>${tnc('video_converter_fm', 'Cancel')}</button>
                    </div>
                </div>
            </div>
        `
    }

    function renderSimpleSummary(dialog, settings) {
        const { summary, estimates } = formatSummaryLines(settings)
        const list = dialog.querySelector('#vc-simple-summary')
        if (list) {
            list.innerHTML = summary.map((item) => `<li><strong>${item.label}</strong>: ${item.value}</li>`).join('')
        }
        const estimationBox = dialog.querySelector('#vc-simple-estimation')
        if (estimationBox) {
            if (estimates.formatCount === 0 || estimates.enabledRenditions.length === 0) {
                estimationBox.textContent = tnc('video_converter_fm', 'Select at least one format and rendition in the Advanced tab.')
            } else {
                estimationBox.innerHTML = `
                    ${tnc('video_converter_fm', 'Estimated space required: ~{n} GB', { n: estimates.estimatedSpace })}<br />
                    ${tnc('video_converter_fm', 'Estimated time: ~{min}-{max} minutes', { min: estimates.timeEstimateMin, max: estimates.timeEstimateMax })}
                `
            }
        }
    }

    function populateAdvancedForm(dialog, settings) {
        dialog.querySelector('#vc-format-dash').checked = !!settings.formats.dash
        dialog.querySelector('#vc-format-hls').checked = !!settings.formats.hls
        RENDITION_PRESETS.forEach((preset) => {
            const data = settings.renditions[preset.id] || {}
            const enabled = data.enabled !== undefined ? data.enabled : preset.defaultEnabled
            dialog.querySelector(`.vc-rendition-toggle[data-resolution="${preset.id}"]`).checked = !!enabled
            const videoInput = dialog.querySelector(`input[data-role="video"][data-resolution="${preset.id}"]`)
            if (videoInput) {
                videoInput.value = data.videoBitrate ?? preset.defaultVideo
            }
            const audioInput = dialog.querySelector(`input[data-role="audio"][data-resolution="${preset.id}"]`)
            if (audioInput) {
                audioInput.value = data.audioBitrate ?? preset.defaultAudio
            }
        })
        dialog.querySelector('#vc-video-codec').value = settings.videoCodec
        dialog.querySelector('#vc-audio-codec').value = settings.audioCodec
        dialog.querySelector('#vc-preset').value = settings.preset
        dialog.querySelector('#vc-subtitles').checked = !!settings.subtitles
        updateAdvancedEstimation(dialog)
    }

    function collectAdvancedSettings(dialog) {
        const settings = loadDefaults()
        settings.formats.dash = dialog.querySelector('#vc-format-dash').checked
        settings.formats.hls = dialog.querySelector('#vc-format-hls').checked

        RENDITION_PRESETS.forEach((preset) => {
            const enabled = dialog.querySelector(`.vc-rendition-toggle[data-resolution="${preset.id}"]`).checked
            const video = toNumber(dialog.querySelector(`input[data-role="video"][data-resolution="${preset.id}"]`).value, preset.defaultVideo)
            const audio = toNumber(dialog.querySelector(`input[data-role="audio"][data-resolution="${preset.id}"]`).value, preset.defaultAudio)
            settings.renditions[preset.id] = {
                label: preset.label,
                enabled,
                videoBitrate: video,
                audioBitrate: audio,
            }
        })

        settings.videoCodec = dialog.querySelector('#vc-video-codec').value
        settings.audioCodec = dialog.querySelector('#vc-audio-codec').value
        settings.preset = dialog.querySelector('#vc-preset').value
        settings.subtitles = dialog.querySelector('#vc-subtitles').checked
        return settings
    }

    function getSelectedFormats(settings) {
        const result = []
        if (settings.formats?.dash) {
            result.push('dash')
        }
        if (settings.formats?.hls) {
            result.push('hls')
        }
        return result
    }

    function buildProfilePayload(settings, formats) {
        return {
            formats,
            renditions: settings.renditions,
            videoCodec: settings.videoCodec,
            audioCodec: settings.audioCodec,
            preset: settings.preset,
            keyframeInterval: settings.keyframeInterval,
            segmentDuration: settings.segmentDuration,
            subtitles: settings.subtitles,
            priority: settings.priority,
            dash: settings.dash,
            hls: settings.hls,
        }
    }

    function updateAdvancedEstimation(dialog) {
        const settings = collectAdvancedSettings(dialog)
        const { estimates } = formatSummaryLines(settings)
        const estimationBox = dialog.querySelector('#vc-advanced-estimation')
        const warning = dialog.querySelector('#vc-format-warning')
        const formats = getSelectedFormats(settings)
        if (warning) {
            warning.hidden = formats.length > 0
        }
        if (!estimationBox) {
            return
        }
        estimationBox.innerHTML = `
            ${tnc('video_converter_fm', 'Estimated space: ~{n} GB', { n: estimates.estimatedSpace })}<br />
            ${tnc('video_converter_fm', 'Estimated time: ~{min}-{max} minutes', { min: estimates.timeEstimateMin, max: estimates.timeEstimateMax })}<br />
            ${tnc('video_converter_fm', 'Renditions actives: {n}', { n: estimates.enabledRenditions.length })}
        `
    }

    function switchTab(dialog, tabName) {
        if (!dialog || activeTab === tabName) {
            return
        }
        activeTab = tabName
        dialog.querySelectorAll('.vc-tab-btn').forEach((btn) => {
            const isActive = btn.dataset.vcTab === tabName
            btn.classList.toggle('vc-tab-btn--active', isActive)
        })
        dialog.querySelectorAll('.vc-tabpanel').forEach((panel) => {
            panel.classList.toggle('vc-tabpanel--active', panel.dataset.vcPanel === tabName)
        })
    }

    function toNumber(value, fallback) {
        const parsed = Number(value)
        if (Number.isFinite(parsed) && parsed > 0) {
            return parsed
        }
        return fallback
    }

    function notify(message, type = 'info') {
        try {
            if (window?.OC?.Notification?.showTemporary) {
                window.OC.Notification.showTemporary(message)
                return
            }
        } catch (error) {
            // fallback to alert below
        }
        if (type === 'error') {
            console.error(message)
        } else {
            console.log(message)
        }
        if (typeof window !== 'undefined') {
            window.alert(message)
        }
    }

    function setSubmitting(dialog, submitting) {
        isSubmitting = submitting
        dialog.querySelectorAll('[data-vc-disable-while-submitting]').forEach((btn) => {
            btn.disabled = submitting
        })
    }

    function setFileBusy(context, busy) {
        if (!context || !context.fileList) {
            return
        }
        try {
            const row = context.fileList.findFileEl ? context.fileList.findFileEl(currentFile) : null
            if (row && typeof context.fileList.showFileBusyState === 'function') {
                context.fileList.showFileBusyState(row, busy)
            }
        } catch (error) {
            console.warn('[video_converter_fm] failed to toggle busy state', error)
        }
    }

    function buildAjaxUrl() {
        if (window?.OC?.generateUrl) {
            return window.OC.generateUrl('/apps/video_converter_fm/ajax/convertHere.php')
        }
        if (window?.OC?.filePath) {
            return window.OC.filePath('video_converter_fm', 'ajax', 'convertHere.php')
        }
        return '/apps/video_converter_fm/ajax/convertHere.php'
    }

    function mapCodec(videoCodec) {
        switch (videoCodec) {
        case 'libx264':
            return 'x264'
        case 'libx265':
            return 'x265'
        case 'libvpx-vp9':
            return 'vp9'
        default:
            return null
        }
    }

    function buildRequestData(filename, context, profile, format) {
        const legacyType = format === 'dash' ? 'mpd' : format === 'hls' ? 'm3u8' : format
        const codec = mapCodec(profile.videoCodec)
        const directory = context?.dir || '/'
        const external = context?.external ? 1 : 0
        const data = {
            nameOfFile: filename,
            directory,
            external,
            type: legacyType,
            preset: profile.preset,
            priority: profile.priority ?? '0',
            movflags: legacyType === 'mp4' ? 1 : 0,
            codec,
            vbitrate: null,
            scale: null,
            mtime: context?.mtime || 0,
            shareOwner: context?.shareOwner || null,
            audioCodec: profile.audioCodec,
            renditions: JSON.stringify(profile.renditions),
            selectedFormats: JSON.stringify(profile.formats),
            profile: JSON.stringify(profile),
        }
        return data
    }

    function postConversion(filename, context, profile, format) {
        const ajaxUrl = buildAjaxUrl()
        const data = buildRequestData(filename, context, profile, format)
        return new Promise((resolve, reject) => {
            $.ajax({
                type: 'POST',
                async: true,
                url: ajaxUrl,
                data,
                dataType: 'json',
                success(resp) {
                    try {
                        if (typeof resp === 'string') {
                            resp = JSON.parse(resp)
                        }
                    } catch (error) {
                        reject(new Error('Malformed response'))
                        return
                    }
                    if (resp && resp.code === 1) {
                        resolve(resp)
                    } else {
                        reject(resp || {})
                    }
                },
                error(xhr) {
                    reject({ error: xhr?.responseText || xhr?.statusText || 'Request failed' })
                },
            })
        })
    }

    async function launchConversions(dialog, filename, context, settings) {
        if (isSubmitting) {
            return
        }
        const formats = getSelectedFormats(settings)
        const enabledRenditions = Object.values(settings.renditions || {}).filter((r) => r && r.enabled)
        if (formats.length === 0) {
            notify(tnc('video_converter_fm', 'Select at least one format (DASH or HLS).'), 'error')
            switchTab(dialog, 'advanced')
            dialog.querySelector('#vc-format-warning').hidden = false
            return
        }
        if (enabledRenditions.length === 0) {
            notify(tnc('video_converter_fm', 'Select at least one rendition to start the conversion.'), 'error')
            switchTab(dialog, 'advanced')
            return
        }

        const profile = buildProfilePayload(settings, formats)
        setSubmitting(dialog, true)
        setFileBusy(context, true)

        const results = await Promise.allSettled(formats.map((format) => postConversion(filename, context, profile, format)))
        setSubmitting(dialog, false)
        setFileBusy(context, false)

        const failures = results.filter((result) => result.status === 'rejected')
        if (failures.length === 0) {
            notify(tnc('video_converter_fm', 'Conversion started: {formats}', { formats: formats.join(' + ').toUpperCase() }))
            closeDialog()
        } else {
            notify(tnc('video_converter_fm', 'Some conversions could not be started.'), 'error')
            console.error('[video_converter_fm] conversion errors', failures)
        }
    }

    function handleAdvancedStart(dialog) {
        const settings = collectAdvancedSettings(dialog)
        launchConversions(dialog, currentFile, currentContext, settings)
    }

    function handleSimpleStart(dialog) {
        const settings = loadDefaults()
        launchConversions(dialog, currentFile, currentContext, settings)
    }

    function bindDialogEvents(dialog) {
        dialog.addEventListener('click', (event) => {
            const action = event.target.closest('[data-vc-action]')?.dataset?.vcAction
            if (!action) {
                return
            }
            event.preventDefault()
            switch (action) {
            case 'close':
            case 'cancel':
                if (!isSubmitting) {
                    closeDialog()
                }
                break
            case 'start-simple':
                handleSimpleStart(dialog)
                break
            case 'start-advanced':
                handleAdvancedStart(dialog)
                break
            case 'load-defaults':
                populateAdvancedForm(dialog, loadDefaults())
                notify(tnc('video_converter_fm', 'Default settings loaded.'))
                break
            case 'save-defaults':
                saveDefaults(collectAdvancedSettings(dialog))
                notify(tnc('video_converter_fm', 'New settings saved as defaults.'))
                break
            }
        })

        dialog.addEventListener('click', (event) => {
            const tabButton = event.target.closest('[data-vc-tab]')
            if (!tabButton) {
                return
            }
            event.preventDefault()
            switchTab(dialog, tabButton.dataset.vcTab)
        })

        dialog.querySelectorAll('.vc-rendition-toggle, .vc-section input, .vc-section select').forEach((input) => {
            input.addEventListener('input', () => updateAdvancedEstimation(dialog))
            input.addEventListener('change', () => updateAdvancedEstimation(dialog))
        })
    }

    function parseContext(node) {
        const context = {
            dir: node?.dirname || '/',
            mtime: node?.mtime || 0,
            external: node?.attributes?.mountType === 'external',
            shareOwner: node?.attributes?.['owner-id'] || null,
            fileList: {
                dirInfo: node?.fileList?.dirInfo || {},
                findFileEl: node?.fileList?.findFileEl || (() => null),
                showFileBusyState: node?.fileList?.showFileBusyState || (() => {}),
            },
        }
        return context
    }

    function showConversionDialog(filename, context) {
        ensureStyles()
        closeDialog()

        const defaults = loadDefaults()
        const markup = buildDialogHtml(filename, defaults)
        document.body.insertAdjacentHTML('beforeend', markup)

        currentDialog = document.getElementById('vc-modal')
        currentOverlay = document.getElementById('vc-overlay')
        activeTab = 'simple'
        currentFile = filename
        currentContext = context

        renderSimpleSummary(currentDialog, defaults)
        populateAdvancedForm(currentDialog, defaults)
        bindDialogEvents(currentDialog)

        escKeyListener = (event) => {
            if (event.key === 'Escape') {
                closeDialog()
            }
        }
        document.addEventListener('keydown', escKeyListener)

        const overlayClickListener = (event) => {
            if (event.target === currentOverlay && !isSubmitting) {
                closeDialog()
            }
        }
        currentOverlay?.addEventListener('click', overlayClickListener)
    }

    function closeDialog() {
        if (escKeyListener) {
            document.removeEventListener('keydown', escKeyListener)
            escKeyListener = null
        }
        if (currentDialog) {
            currentDialog.remove()
            currentDialog = null
        }
        if (currentOverlay) {
            currentOverlay.remove()
            currentOverlay = null
        }
        currentFile = null
        currentContext = null
        isSubmitting = false
    }

    function registerNC32Action() {
        if (!window._nc_fileactions) {
            console.log('[video_converter_fm] _nc_fileactions not available yet')
            return false
        }
        try {
            const actionDef = {
                id: 'video-convert',
                displayName(nodes) {
                    return tnc('video_converter_fm', 'Convertir en profil adaptatif')
                },
                iconSvgInline() {
                    return '<svg width="16" height="16" viewBox="0 0 16 16"><path d="M8 2a6 6 0 1 0 0 12A6 6 0 0 0 8 2zm0 1a5 5 0 1 1 0 10A5 5 0 0 1 8 3zm-.5 2v3.5H5l3 3 3-3H8.5V5h-1z"/></svg>'
                },
                enabled(nodes) {
                    if (!Array.isArray(nodes) || nodes.length !== 1) {
                        return false
                    }
                    const node = nodes[0]
                    return node?.mime?.startsWith('video/')
                },
                exec(node) {
                    const context = {
                        dir: node?.dirname || '/',
                        mtime: node?.mtime || 0,
                        external: node?.attributes?.mountType === 'external',
                        shareOwner: node?.attributes?.['owner-id'] || null,
                        fileList: node?.fileList || {
                            dirInfo: node?.fileList?.dirInfo || {},
                            findFileEl: () => null,
                            showFileBusyState: () => {},
                        },
                    }
                    currentContext = context
                    showConversionDialog(node.basename, context)
                },
                order: 50,
            }

            if (typeof window._nc_fileactions === 'function') {
                window._nc_fileactions(actionDef)
            } else if (Array.isArray(window._nc_fileactions)) {
                window._nc_fileactions.push(actionDef)
            } else if (typeof window._nc_fileactions.registerAction === 'function') {
                window._nc_fileactions.registerAction(actionDef)
            } else {
                console.warn('[video_converter_fm] unknown _nc_fileactions structure', typeof window._nc_fileactions)
                return false
            }
            console.log('[video_converter_fm] NC32 file action registered (modal v2)')
            return true
        } catch (error) {
            console.error('[video_converter_fm] failed to register NC32 action', error)
            return false
        }
    }

    function tryRegister() {
        if (!registerNC32Action()) {
            setTimeout(tryRegister, 500)
        }
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        tryRegister()
    } else {
        document.addEventListener('DOMContentLoaded', tryRegister)
    }
})()
