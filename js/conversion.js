/**
 * Integration script for the video conversion action in Nextcloud Files.
 * Modal v2 with simple and advanced tabs aligned with the design mockup.
 *
 * Prepares future DASH and HLS features while staying compatible with the current backend.
 */

;(function () {
    'use strict'

    const STORAGE_KEY = 'video_converter_fm::modal_defaults'
    const DEFAULT_DURATION_SECONDS = 60 // Fallback conservative: 1 minute (ajustable selon durée réelle)
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
    let currentVideoDuration = null // Real video duration from probe (null = use fallback)
    let currentVideoMetadata = null // Full metadata from probe
    let activeTab = 'simple'
    let isSubmitting = false
    let escKeyListener = null
    let cachedDefaults = null
    let workingSettings = null

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
            .vc-overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); z-index: 10000; backdrop-filter: blur(2px); }
            .vc-modal { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; z-index: 10001; }
            .vc-modal__dialog { width: min(600px, calc(100vw - 32px)); max-height: calc(100vh - 32px); background: var(--color-main-background, #fff); border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); display: flex; flex-direction: column; overflow: hidden; color-scheme: var(--nextcloud-color-scheme, light dark); }
            .vc-modal__header { padding: 20px 24px; border-bottom: 1px solid var(--color-border, #e1e4e8); display: flex; align-items: center; justify-content: space-between; background: linear-gradient(135deg, var(--color-primary, #0082c9) 0%, #006eaa 100%); }
            .vc-modal__title { margin: 0; font-size: 18px; font-weight: 600; color: #fff; display: flex; align-items: center; gap: 10px; }
            .vc-modal__title::before { content: '🎬'; }
            .vc-close-btn { border: none; background: rgba(255,255,255,0.2); cursor: pointer; font-size: 18px; color: #fff; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
            .vc-close-btn:hover { background: rgba(255,255,255,0.3); }
            .vc-tabs { display: flex; border-bottom: 1px solid var(--color-border, #e1e4e8); background: var(--color-background-hover, #f6f8fa); }
            .vc-tab-btn { flex: 1; padding: 14px 16px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-weight: 600; font-size: 14px; color: var(--color-text-lighter, #6a737d); transition: all 0.2s; }
            .vc-tab-btn:hover { background: var(--color-background-dark, #eaecef); }
            .vc-tab-btn--active { color: var(--color-primary, #0082c9); border-bottom-color: var(--color-primary, #0082c9); background: var(--color-main-background, #fff); }
            .vc-modal__body { padding: 20px 24px; overflow-y: auto; display: flex; flex-direction: column; gap: 20px; max-height: 60vh; }
            .vc-tabpanel { display: none; flex-direction: column; gap: 20px; }
            .vc-tabpanel--active { display: flex; }
            .vc-section { display: flex; flex-direction: column; gap: 12px; padding: 16px; background: var(--color-background-hover, #f6f8fa); border-radius: 10px; border: 1px solid var(--color-border, #e1e4e8); }
            .vc-section__title { margin: 0; font-size: 13px; font-weight: 700; color: var(--color-text-maxcontrast, #1a2026); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px; }
            .vc-section__title::before { content: ''; width: 4px; height: 16px; background: var(--color-primary, #0082c9); border-radius: 2px; }
            .vc-summary-list { margin: 0; padding-left: 18px; display: flex; flex-direction: column; gap: 6px; font-size: 13px; }
            .vc-estimation-box { font-size: 13px; padding: 16px; border: 1px solid var(--color-border, #e1e4e8); border-radius: 10px; background: linear-gradient(135deg, var(--color-background-hover, #f6f8fa) 0%, var(--color-main-background, #fff) 100%); }
            .vc-warning { font-size: 13px; padding: 12px 14px; border-radius: 8px; border: 1px solid var(--color-warning, #f0a500); color: var(--color-warning-text, #b37400); background: rgba(240,165,0,0.1); display: flex; align-items: center; gap: 8px; }
            .vc-warning::before { content: '⚠️'; }
            .vc-button-row { display: flex; flex-wrap: wrap; gap: 10px; }
            .vc-button { padding: 10px 16px; border-radius: 8px; border: 1px solid var(--color-border, #e1e4e8); background: var(--color-main-background, #fff); cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s; }
            .vc-button:hover { background: var(--color-background-hover, #f6f8fa); transform: translateY(-1px); }
            .vc-button--primary { background: linear-gradient(135deg, var(--color-primary, #0082c9) 0%, #006eaa 100%); color: var(--color-primary-text, #fff); border-color: var(--color-primary, #0082c9); box-shadow: 0 2px 8px rgba(0,130,201,0.3); }
            .vc-button--primary:hover { box-shadow: 0 4px 12px rgba(0,130,201,0.4); transform: translateY(-1px); }
            .vc-button--small { padding: 6px 12px; font-size: 12px; }
            .vc-modal__footer { padding: 16px 24px; border-top: 1px solid var(--color-border, #e1e4e8); display: flex; justify-content: flex-end; gap: 10px; background: var(--color-background-hover, #f6f8fa); }
            .vc-form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
            .vc-form-field { display: flex; flex-direction: column; gap: 6px; font-size: 13px; }
            .vc-form-field label { font-weight: 500; color: var(--color-text-maxcontrast, #444); }
            .vc-form-field input[type="number"], .vc-form-field select { padding: 10px 12px; border-radius: 8px; border: 1px solid var(--color-border, #e1e4e8); background: var(--color-main-background, #fff); font-size: 13px; transition: border-color 0.2s, box-shadow 0.2s; }
            .vc-form-field input[type="number"]:focus, .vc-form-field select:focus { outline: none; border-color: var(--color-primary, #0082c9); box-shadow: 0 0 0 3px rgba(0,130,201,0.15); }
            .vc-format-toggle { display: flex; gap: 16px; }
            .vc-format-toggle label { display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: var(--color-main-background, #fff); border: 2px solid var(--color-border, #e1e4e8); border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s; flex: 1; }
            .vc-format-toggle label:hover { border-color: var(--color-primary, #0082c9); background: rgba(0,130,201,0.05); }
            .vc-format-toggle input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--color-primary, #0082c9); }
            .vc-format-toggle input[type="checkbox"]:checked + span { color: var(--color-primary, #0082c9); }
            .vc-rendition-list { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .vc-rendition-item { border: 2px solid var(--color-border, #e1e4e8); border-radius: 10px; padding: 14px; display: flex; flex-direction: column; gap: 12px; background: var(--color-main-background, #fff); transition: all 0.2s; }
            .vc-rendition-item:hover { border-color: var(--color-primary-element-light, #a8d4f0); }
            .vc-rendition-item--disabled { opacity: 0.5; }
            .vc-rendition-header { display: flex; justify-content: space-between; align-items: center; }
            .vc-rendition-header label { display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; cursor: pointer; }
            .vc-rendition-header input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--color-primary, #0082c9); }
            .vc-rendition-meta { font-size: 11px; color: var(--color-text-lighter, #6a737d); background: var(--color-background-dark, #eaecef); padding: 4px 8px; border-radius: 4px; }
            .vc-rendition-inputs { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12px; }
            .vc-rendition-inputs label { display: flex; flex-direction: column; gap: 4px; color: var(--color-text-lighter, #6a737d); }
            .vc-rendition-inputs input { padding: 8px 10px; border-radius: 6px; border: 1px solid var(--color-border, #e1e4e8); background: var(--color-background-hover, #f6f8fa); font-size: 12px; width: 100%; box-sizing: border-box; }
            .vc-rendition-inputs input:focus { outline: none; border-color: var(--color-primary, #0082c9); background: var(--color-main-background, #fff); }
            .vc-checkbox-field { display: flex; align-items: center; gap: 10px; padding: 12px; background: var(--color-main-background, #fff); border: 1px solid var(--color-border, #e1e4e8); border-radius: 8px; cursor: pointer; transition: all 0.2s; }
            .vc-checkbox-field:hover { border-color: var(--color-primary, #0082c9); }
            .vc-checkbox-field input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--color-primary, #0082c9); }
            .vc-section-row { display: flex; gap: 16px; }
            .vc-section-row > .vc-section { flex: 1; }
            @media (max-width: 600px) { 
                .vc-modal__dialog { width: calc(100vw - 24px); } 
                .vc-rendition-list { grid-template-columns: 1fr; }
                .vc-form-grid { grid-template-columns: 1fr; }
                .vc-format-toggle { flex-direction: column; }
            }
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

    // Improved estimation model factoring codec, preset & resolution cost + packaging overhead
    function calculateEstimates(settings, durationSeconds = null, opts = {}) {
        // Use real video duration if available, otherwise fallback to DEFAULT_DURATION_SECONDS
        if (durationSeconds === null) {
            durationSeconds = currentVideoDuration || DEFAULT_DURATION_SECONDS
        }
        
        const {
            reuseEncodingBetweenFormats = true, // if true, DASH + HLS share same encode, packaging adds a small overhead
            overheadPercent = 0.06,             // container + manifest + segmentation overhead
            avgBitrateEfficiency = 0.85,        // target bitrate vs. real average
            packagingOverheadPerExtraFormat = 0.08, // cost of additional format if reuseEncodingBetweenFormats
        } = opts

        const enabledRenditions = RENDITION_PRESETS
            .map((preset) => {
                const entry = settings.renditions?.[preset.id] || {}
                const enabled = entry.enabled !== undefined ? entry.enabled : preset.defaultEnabled
                return {
                    id: preset.id,
                    label: preset.label,
                    resolution: preset.resolution,
                    videoBitrate: Number(entry.videoBitrate ?? preset.defaultVideo) || 0,
                    audioBitrate: Number(entry.audioBitrate ?? preset.defaultAudio) || 0,
                    enabled,
                }
            })
            .filter(r => r.enabled)

        // Active formats
        const activeFormats = []
        if (settings.formats?.dash) activeFormats.push('dash')
        if (settings.formats?.hls) activeFormats.push('hls')
        const formatCount = activeFormats.length

        // --- Estimated output size (GB) ---
        const totalBitrateKbps = enabledRenditions.reduce((sum, r) => sum + r.videoBitrate + r.audioBitrate, 0)
        let estimatedSpaceGB = 0
        if (totalBitrateKbps > 0 && formatCount > 0) {
            estimatedSpaceGB = ((totalBitrateKbps * avgBitrateEfficiency * durationSeconds) / 8 / 1024 / 1024) * formatCount
            estimatedSpaceGB *= (1 + overheadPercent)
        }
        estimatedSpaceGB = Number(estimatedSpaceGB.toFixed(2))

        // --- Time estimation ---
        // Facteurs calibrés pour un ratio temps_encodage / durée_source
        // (temps réel observé: ~1x pour x264 slow, ~1.6x pour x265, ~2x pour VP9)
        const codecMult = { libx264: 1.0, libx265: 1.5, 'libvpx-vp9': 1.8 }[settings.videoCodec] ?? 1.2
        const presetMult = {
            ultrafast: 0.15,
            superfast: 0.25,
            veryfast: 0.35,
            fast: 0.5,
            medium: 0.7,
            slow: 1.0,
            slower: 1.3,
            veryslow: 1.6,
        }[settings.preset] ?? 1.0
        const resolutionMult = {
            '1920x1080': 1.0,
            '1280x720': 0.6,
            '854x480': 0.4,
            '640x360': 0.3,
            '426x240': 0.2,
            '256x144': 0.1,
        }
        const perRenditionFactors = enabledRenditions.map(r => {
            const resMult = resolutionMult[r.resolution] ?? 1.0
            return codecMult * presetMult * resMult
        })
        let encodeFactorSum = perRenditionFactors.reduce((a, b) => a + b, 0)

        let estimatedSeconds
        if (reuseEncodingBetweenFormats && formatCount > 1) {
            const baseEncode = durationSeconds * encodeFactorSum
            const packaging = baseEncode * packagingOverheadPerExtraFormat * (formatCount - 1)
            estimatedSeconds = baseEncode + packaging
        } else {
            estimatedSeconds = durationSeconds * encodeFactorSum * formatCount
        }
        const timeEstimateMin = Math.max(1, Math.round((estimatedSeconds / 60) * 0.85))
        const timeEstimateMax = Math.max(timeEstimateMin, Math.round((estimatedSeconds / 60) * 1.15))

        return {
            enabledRenditions,
            activeFormats,
            formatCount,
            estimatedSpaceGB,
            timeEstimateMin,
            timeEstimateMax,
            codecMult,
            presetMult,
            perRenditionFactors,
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
        // Extra quick stats
        // summary.push({ label: tnc('video_converter_fm', 'Est. size (GB)'), value: estimates.estimatedSpaceGB })
        // summary.push({ label: tnc('video_converter_fm', 'Est. time (min)'), value: `${estimates.timeEstimateMin}-${estimates.timeEstimateMax}` })
        return { summary, estimates }
    }

    function buildDialogHtml(filename, settings) {
        // Build video info line from metadata if available
        let videoInfoHtml = ''
        if (currentVideoMetadata) {
            const parts = []
            if (currentVideoMetadata.durationFormatted) {
                parts.push(`⏱️ ${currentVideoMetadata.durationFormatted}`)
            }
            if (currentVideoMetadata.width && currentVideoMetadata.height) {
                parts.push(`📐 ${currentVideoMetadata.width}×${currentVideoMetadata.height}`)
            }
            if (currentVideoMetadata.codec) {
                parts.push(`🎥 ${currentVideoMetadata.codec}`)
            }
            if (currentVideoMetadata.bitrate) {
                parts.push(`📊 ${currentVideoMetadata.bitrate} Kbps`)
            }
            if (currentVideoMetadata.fps) {
                parts.push(`🎬 ${currentVideoMetadata.fps} fps`)
            }
            
            if (parts.length > 0) {
                videoInfoHtml = `<li style="font-size: 12px; color: var(--color-text-lighter);">${parts.join(' • ')}</li>`
            }
        }

        const getResolutionIcon = (id) => {
            const icons = { '1080p': '🎬', '720p': '📺', '480p': '📱', '360p': '📲', '240p': '📟', '144p': '⌚' }
            return icons[id] || '🎥'
        }

        const renditionMarkup = RENDITION_PRESETS.map((preset) => `
            <div class="vc-rendition-item" data-rendition="${preset.id}">
                <div class="vc-rendition-header">
                    <label>
                        <input type="checkbox" class="vc-rendition-toggle" data-resolution="${preset.id}" />
                        <span>${getResolutionIcon(preset.id)} ${preset.label}</span>
                    </label>
                    <span class="vc-rendition-meta">${preset.resolution}</span>
                </div>
                <div class="vc-rendition-inputs">
                    <label>
                        ${tnc('video_converter_fm', 'Vidéo')} (Kbps)
                        <input type="number" min="100" data-role="video" data-resolution="${preset.id}" />
                    </label>
                    <label>
                        ${tnc('video_converter_fm', 'Audio')} (Kbps)
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
                        <h2 class="vc-modal__title" id="vc-modal-title">${tnc('video_converter_fm', 'Conversion DASH et HLS')}</h2>
                        <button type="button" class="vc-close-btn" data-vc-action="close" aria-label="${tnc('video_converter_fm', 'Close')}">&times;</button>
                    </div>
                    <div class="vc-tabs">
                        <button type="button" class="vc-tab-btn vc-tab-btn--active" data-vc-tab="simple">${tnc('video_converter_fm', 'Simple')}</button>
                        <button type="button" class="vc-tab-btn" data-vc-tab="advanced">${tnc('video_converter_fm', 'Avancé')}</button>
                    </div>
                    <div class="vc-modal__body">
                        <div class="vc-tabpanel vc-tabpanel--active" data-vc-panel="simple">
                            <div class="vc-section">
                                <h3 class="vc-section__title">${tnc('video_converter_fm', 'Fichier source')}</h3>
                                <ul class="vc-summary-list">
                                    <li><strong>📁 ${filename}</strong></li>
                                    ${videoInfoHtml}
                                </ul>
                            </div>
                            <div class="vc-section">
                                <h3 class="vc-section__title">${tnc('video_converter_fm', 'Profil de conversion')}</h3>
                                <ul class="vc-summary-list" id="vc-simple-summary"></ul>
                            </div>
                            <div class="vc-estimation-box" id="vc-simple-estimation"></div>
                            
                            
                            <div class="vc-button-row" style="justify-content: center;">
                                <button type="button" class="vc-button" data-vc-tab="advanced" style="padding: 12px 20px;">
                                    ⚙️ ${tnc('video_converter_fm', 'Personnaliser...')}
                                </button>
                            </div>
                        </div>
                        <div class="vc-tabpanel" data-vc-panel="advanced">
                            <!--
                                    <div class="vc-section">
                                        <h3 class="vc-section__title">${tnc('video_converter_fm', 'Formats de sortie')}</h3>
                                        <div class="vc-format-toggle">
                                            <label><input type="checkbox" id="vc-format-dash" /><span>📦 DASH (MPD)</span></label>
                                            <label><input type="checkbox" id="vc-format-hls" /><span>📺 HLS (M3U8)</span></label>
                                        </div>
                                        <div class="vc-warning vc-warning--error" id="vc-format-warning" hidden>
                                            ${tnc('video_converter_fm', 'Sélectionnez au moins un format de sortie.')}
                                        </div>
                                    </div>
                                    -->
                            <div class="vc-section">
                                <h3 class="vc-section__title">${tnc('video_converter_fm', 'Résolutions')}</h3>
                                <div class="vc-button-row" style="margin-bottom: 8px;">
                                    <button type="button" class="vc-button vc-button--small" data-vc-action="load-defaults">🔄 ${tnc('video_converter_fm', 'Réinitialiser')}</button>
                                    <button type="button" class="vc-button vc-button--small" data-vc-action="select-all-renditions">✅ ${tnc('video_converter_fm', 'Tout sélectionner')}</button>
                                    <button type="button" class="vc-button vc-button--small" data-vc-action="deselect-all-renditions">❌ ${tnc('video_converter_fm', 'Tout désélectionner')}</button>
                                </div>
                                <div class="vc-rendition-list">${renditionMarkup}</div>
                            </div>
                            <div class="vc-section">
                                <h3 class="vc-section__title">${tnc('video_converter_fm', 'Encodage')}</h3>
                                <div class="vc-form-grid">
                                    <div class="vc-form-field">
                                        <label for="vc-video-codec">🎥 ${tnc('video_converter_fm', 'Codec vidéo')}</label>
                                        <select id="vc-video-codec">
                                            <option value="libx264">H.264 (libx264)</option>
                                            <option value="libx265">H.265 (libx265)</option>
                                            <option value="libvpx-vp9">VP9 (libvpx-vp9)</option>
                                        </select>
                                    </div>
                                    <div class="vc-form-field">
                                        <label for="vc-audio-codec">🔊 ${tnc('video_converter_fm', 'Codec audio')}</label>
                                        <select id="vc-audio-codec">
                                            <option value="aac">AAC</option>
                                            <option value="opus">Opus</option>
                                            <option value="mp3">MP3</option>
                                        </select>
                                    </div>
                                    <div class="vc-form-field">
                                        <label for="vc-preset">⚡ ${tnc('video_converter_fm', 'Préréglage vitesse/qualité')}</label>
                                        <select id="vc-preset">
                                            <option value="ultrafast">🚀 Ultra rapide (qualité basse)</option>
                                            <option value="superfast">⚡ Super rapide</option>
                                            <option value="veryfast">💨 Très rapide</option>
                                            <option value="fast">🏃 Rapide</option>
                                            <option value="medium">⚖️ Équilibré</option>
                                            <option value="slow">🎯 Lent (bonne qualité)</option>
                                            <option value="slower">✨ Très lent</option>
                                            <option value="veryslow">💎 Ultra lent (meilleure qualité)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="vc-section">
                                <h3 class="vc-section__title">${tnc('video_converter_fm', 'Options')}</h3>
                                <label class="vc-checkbox-field">
                                    <input type="checkbox" id="vc-subtitles" />
                                    <span>📝 ${tnc('video_converter_fm', 'Convertir les sous-titres (SRT → WebVTT)')}</span>
                                </label>
                            </div>
                            <div class="vc-estimation-box" id="vc-advanced-estimation"></div>
                            <!--
                            <div class="vc-button-row" style="justify-content: center;">
                                <button type="button" class="vc-button vc-button--primary" data-vc-action="start-advanced" data-vc-disable-while-submitting style="padding: 12px 32px; font-size: 14px;">
                                    🚀 ${tnc('video_converter_fm', 'Démarrer la conversion')}
                                </button>
                            </div>
                            -->
                        </div>
                    </div>
                    <div class="vc-modal__footer">
                        <button type="button" class="vc-button" data-vc-action="cancel" data-vc-disable-while-submitting>${tnc('video_converter_fm', 'Annuler')}</button>
                        <button type="button" class="vc-button vc-button--primary" data-vc-action="start" data-vc-disable-while-submitting>
                            🚀 ${tnc('video_converter_fm', 'Démarrer la conversion')}
                        </button>
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
                estimationBox.textContent = tnc('video_converter_fm', 'Sélectionnez au moins un format et une déclinaison dans l\'onglet Avancé.')
            } else {
                // Show note only if using fallback duration (no real metadata)
                const durationNote = currentVideoDuration 
                    ? '' // Real duration detected, no note needed
                    : `<em style="font-size: 11px; color: var(--color-text-lighter);">(estimation basée sur ~${Math.round(DEFAULT_DURATION_SECONDS/60)} min par défaut)</em>`
                estimationBox.innerHTML = `
                    <strong>${tnc('video_converter_fm', 'Espace estimé requis')} :</strong> ~${estimates.estimatedSpaceGB} Go<br />
                    <strong>${tnc('video_converter_fm', 'Temps estimé')} :</strong> ~${estimates.timeEstimateMin}-${estimates.timeEstimateMax} minutes ${durationNote}
                `
            }
        }
    }

    function populateAdvancedForm(dialog, settings) {
        // Checkboxes removed from UI
        // dialog.querySelector('#vc-format-dash').checked = !!settings.formats.dash
        // dialog.querySelector('#vc-format-hls').checked = !!settings.formats.hls
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
    updateAdvancedEstimation(dialog, settings)
    }

    function collectAdvancedSettings(dialog) {
        const base = workingSettings ? deepClone(workingSettings) : loadDefaults()
        const settings = base

        settings.formats = settings.formats || { dash: true, hls: true }
        settings.renditions = settings.renditions || {}

        // settings.formats.dash = dialog.querySelector('#vc-format-dash').checked
        // settings.formats.hls = dialog.querySelector('#vc-format-hls').checked
        // Force both formats regardless of UI
        settings.formats.dash = true;
        settings.formats.hls = true;

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

        workingSettings = settings
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

    function updateAdvancedEstimation(dialog, providedSettings) {
        const settings = providedSettings || (workingSettings ? deepClone(workingSettings) : loadDefaults())
        const { estimates } = formatSummaryLines(settings)
        const estimationBox = dialog.querySelector('#vc-advanced-estimation')

        /*
        const warning = dialog.querySelector('#vc-format-warning')
        const formats = getSelectedFormats(settings)
        if (warning) {
            warning.hidden = formats.length > 0
        }
        */

        if (!estimationBox) {
            return
        }
        estimationBox.innerHTML = `
            <strong>${tnc('video_converter_fm', 'Espace estimé')} :</strong> ~${estimates.estimatedSpaceGB} Go<br />
            <strong>${tnc('video_converter_fm', 'Temps estimé')} :</strong> ~${estimates.timeEstimateMin}-${estimates.timeEstimateMax} minutes<br />
            <strong>${tnc('video_converter_fm', 'Renditions actives')} :</strong> ${estimates.enabledRenditions.length}
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

    function buildRequestData(filename, context, profile) {
        const selectedFormats = Array.isArray(profile.formats)
            ? profile.formats
            : Object.keys(profile.formats || {}).filter((key) => profile.formats[key])

        let legacyType = 'mp4'
        if (selectedFormats.length === 1) {
            const single = selectedFormats[0]
            if (single === 'dash') {
                legacyType = 'mpd'
            } else if (single === 'hls') {
                legacyType = 'm3u8'
            } else if (typeof single === 'string') {
                legacyType = single
            }
        } else if (selectedFormats.length > 1) {
            legacyType = 'adaptive'
        }

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

    function postConversion(filename, context, profile) {
        const ajaxUrl = buildAjaxUrl()
        const data = buildRequestData(filename, context, profile)
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

        try {
            await postConversion(filename, context, profile)
            let formatText = ''
            if (formats.length === 2) {
                formatText = 'DASH + HLS'
            } else if (formats.length === 1) {
                formatText = formats[0] === 'dash' ? 'DASH' : 'HLS'
            } else {
                formatText = 'inconnu'
            }
            notify(`Conversion de ${filename} en ${formatText}`)
            closeDialog()
        } catch (error) {
            notify(tnc('video_converter_fm', 'Conversion could not be started.'), 'error')
            console.error('[video_converter_fm] conversion error', error)
        } finally {
            setSubmitting(dialog, false)
            setFileBusy(context, false)
        }
    }

    function handleAdvancedStart(dialog) {
        const settings = collectAdvancedSettings(dialog)
        renderSimpleSummary(dialog, settings)
        updateAdvancedEstimation(dialog, settings)
        launchConversions(dialog, currentFile, currentContext, deepClone(settings))
    }

    function handleSimpleStart(dialog) {
        const settings = workingSettings ? deepClone(workingSettings) : loadDefaults()
        renderSimpleSummary(dialog, settings)
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
            case 'start':
                if (activeTab === 'advanced') {
                    handleAdvancedStart(dialog)
                } else {
                    handleSimpleStart(dialog)
                }
                break
            case 'load-defaults': {
                const defaults = loadDefaults()
                workingSettings = deepClone(defaults)
                populateAdvancedForm(dialog, workingSettings)
                renderSimpleSummary(dialog, workingSettings)
                notify(tnc('video_converter_fm', 'Default settings loaded.'))
                break
            }
            case 'select-all-renditions': {
                dialog.querySelectorAll('.vc-rendition-toggle').forEach((checkbox) => {
                    checkbox.checked = true
                })
                const settings = collectAdvancedSettings(dialog)
                renderSimpleSummary(dialog, settings)
                updateAdvancedEstimation(dialog, settings)
                break
            }
            case 'deselect-all-renditions': {
                dialog.querySelectorAll('.vc-rendition-toggle').forEach((checkbox) => {
                    checkbox.checked = false
                })
                const settings = collectAdvancedSettings(dialog)
                renderSimpleSummary(dialog, settings)
                updateAdvancedEstimation(dialog, settings)
                break
            }
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

        const updateRenditionVisualState = () => {
            dialog.querySelectorAll('.vc-rendition-item').forEach((item) => {
                const checkbox = item.querySelector('.vc-rendition-toggle')
                if (checkbox) {
                    item.classList.toggle('vc-rendition-item--disabled', !checkbox.checked)
                }
            })
        }

        const handleAdvancedChange = () => {
            const settings = collectAdvancedSettings(dialog)
            renderSimpleSummary(dialog, settings)
            updateAdvancedEstimation(dialog, settings)
            updateRenditionVisualState()
        }

        dialog.querySelectorAll('.vc-tabpanel[data-vc-panel="advanced"] input, .vc-tabpanel[data-vc-panel="advanced"] select').forEach((input) => {
            input.addEventListener('input', handleAdvancedChange)
            input.addEventListener('change', handleAdvancedChange)
        })

        // Initialize visual state
        updateRenditionVisualState()
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

    async function showConversionDialog(filename, context) {
        ensureStyles()
        closeDialog()

        const defaults = loadDefaults()
        workingSettings = deepClone(defaults)
        
        // === PROBE VIDEO TO GET REAL METADATA ===
        currentVideoDuration = null
        currentVideoMetadata = null
        
        try {
            const probeUrl = window?.OC?.generateUrl 
                ? window.OC.generateUrl('/apps/video_converter_fm/api/video/probe')
                : '/apps/video_converter_fm/api/video/probe'
            
            const videoPath = `${context.dir}/${filename}`
            console.log(`[video_converter_fm] Probing video: ${videoPath}`)
            
            const response = await fetch(probeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'requesttoken': window?.OC?.requestToken || ''
                },
                body: JSON.stringify({ path: videoPath })
            })
            
            if (response.ok) {
                currentVideoMetadata = await response.json()
                if (currentVideoMetadata.duration > 0) {
                    currentVideoDuration = Math.ceil(currentVideoMetadata.duration)
                    console.log(`[video_converter_fm] Detected video duration: ${currentVideoDuration}s (${currentVideoMetadata.durationFormatted})`)
                } else {
                    console.warn('[video_converter_fm] Probe returned zero duration, using fallback')
                }
            } else {
                console.warn('[video_converter_fm] Probe failed with status:', response.status)
            }
        } catch (error) {
            console.warn('[video_converter_fm] Failed to probe video, using fallback duration:', error)
        }
        // === END PROBE ===
        
        const markup = buildDialogHtml(filename, defaults)
        document.body.insertAdjacentHTML('beforeend', markup)

        currentDialog = document.getElementById('vc-modal')
        currentOverlay = document.getElementById('vc-overlay')
        activeTab = 'simple'
        currentFile = filename
        currentContext = context

        renderSimpleSummary(currentDialog, workingSettings)
        populateAdvancedForm(currentDialog, workingSettings)
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
        workingSettings = null
        currentFile = null
        currentContext = null
        currentVideoDuration = null
        currentVideoMetadata = null
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
