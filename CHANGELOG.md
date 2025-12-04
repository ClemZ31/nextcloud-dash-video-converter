# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-12-04

**Major rewrite – PFE ÉTS Montreal × Funambules Médias**

### Added

#### Adaptive Streaming
- Unified DASH (.mpd) + HLS (.m3u8) generation in a single FFmpeg pass
- Multi-resolution: 1080p, 720p, 480p, 360p, 240p, 144p renditions
- 3 video codecs: H.264 (libx264), H.265/HEVC (libx265), VP9 (libvpx-vp9)
- 3 audio codecs: AAC, Opus, MP3
- Automatic SRT to WebVTT subtitle conversion

#### User Interface
- "Conversions" app: Vue 3 SPA with task dashboard
- "Convert to..." context menu integrated into Files interface
- Conversion modal with synchronized Simple/Advanced tabs
- Real-time disk space estimation via ffprobe
- Video metadata display in modal
- Real-time progress bar with polling

#### Task Management
- Asynchronous job system with `oc_video_jobs` table
- Dedicated systemd worker (`bin/worker.php`) for background processing
- Admin view: see all users' conversions
- Task deletion/cancellation with confirmation dialog
- Option to delete or keep output files when removing a task
- FFmpeg PID storage to allow cancellation of running tasks

#### Output Structure
- Timestamped folders: `video_YYYY_MM_DD_HH_MM_SS/`
- CDN-optimized nested structure: `timestamped_folder/video_name/segments/`
- Direct link to output folder in Files interface
- Server-side verification: link only appears if folder exists

#### Documentation & Deployment
- Multilingual READMEs: French and English
- Separate admin and user guides
- PowerShell deployment script for Windows
- Bash deployment script for macOS
- Docker Compose for containerized deployment
- Development guide DEVELOPMENT.md

### Changed

- **Compatibility**: Nextcloud 32+ only (not backwards compatible)
- **PHP Namespace**: Renamed to `video_converter_fm`
- **App ID**: `video_converter_fm`
- **Frontend**: Migration from Vue 2 to Vue 3 with @nextcloud/vue v9
- **Build system**: Webpack to Vite 5
- **Architecture**: Separation of ConversionService / ConversionController
- **Default FFmpeg preset**: "medium" to "slow" for better quality
- **VP9**: Generates DASH only (HLS disabled due to fMP4 incompatibility)
- **Output formats**: Always DASH + HLS together (selection removed)
- **Output tree structure**: Adapted for Bunny/CDN compatibility

### Fixed

- `undefined getAppName()` error in nextcloud.log
- Odd-width scaling issue (trunc filter for even dimensions)
- HLS audio duplication (buildHlsCodecArgs for single shared audio stream)
- Unsupported `strftime_mkdir` flag (sanitizeHlsFlags whitelist)
- Timezone display (UTC to local time)
- JSON vs $_POST parsing for job deletion
- Shell scripts encoding: UTF-8 without BOM, LF line endings
- Improved FFmpeg logging in worker

### Removed

- Nextcloud < 32 support (use v1.0.5 for NC 17-24)
- DASH/HLS selection checkboxes (always both)
- Legacy PHP templates (replaced by Vue SPA)
- Obsolete CSS and archive files

## [1.0.5]

- Support for NC 24
- Support copy codec

## [1.0.4]

- Support for NC 23

## [1.0.3]

- Fixed "faststart is not defined"

## [1.0.2]

- Support for NC 22
- Add more resolutions (thanks @CWBudde)
- Add faststart movflag for pseudo streaming (thanks @CWBudde)

## [1.0.1]

- Support for NC 21

## [1.0.0]

- Better handling of files
- Improved performances
- Support group folders
- Support all external storage methods

## [0.1.5]

- Support for NC 20

## [0.1.4]

- Support for NC 19
- Add scaling options
- Fix : https://github.com/PaulLereverend/NextcloudVideo_Converter/issues/35

## [0.1.3]

- Support for NC 18
- Fix security issue

## [0.1.2]

- Support for NC 17
- Fix issue on shared folders
- Fix incorrect stack trace

## [0.1.0]

- Fix : https://github.com/PaulLereverend/NextcloudVideo_Converter/issues/4
- Fix : https://github.com/PaulLereverend/NextcloudVideo_Converter/issues/6
- Fix : https://github.com/PaulLereverend/NextcloudVideo_Converter/issues/3
- Fix : Override on external local storage.
- Fix : Clarify a few error messages

## [0.0.2]    

- Initial release
