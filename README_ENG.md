# Nextcloud Video Converter (PFE)

A Nextcloud application designed for asynchronous video transcoding into adaptive streaming formats (DASH and HLS). Built to be high-performance, non-blocking, and natively integrated into the Files interface.

## Project Context

This application was developed as part of a **Projet de Fin d'Études (PFE)** at **École de Technologie Supérieure (ÉTS)** in Montreal, in partnership with **[Funambules Médias](https://funambulesmedias.org/)**, a worker cooperative dedicated to documentary film programming and digital heritage preservation.

The goal is to build a fully **open-source video streaming system** that enables:
- One-click conversion of H.264 videos (2-20 GB) to HLS/MPEG-DASH within Nextcloud
- Scalable streaming for hundreds of concurrent viewers
- Digital sovereignty through self-hosted, open-source tools

## Key Features

* **Adaptive Streaming:** Automatically generates **MPEG-DASH (.mpd)** and **HLS (.m3u8)** manifests.
* **Multi-Resolution:** Creates multiple renditions (1080p, 720p, 480p, etc.) to adapt to the client's bandwidth.
* **Asynchronous Architecture:** Uses background PHP workers to ensure the Nextcloud user interface never freezes during conversion.
* **Modern Interface:**
    * Integrated "Convert to..." action in the Files context menu.
    * Task monitoring dashboard (Vue.js).
    * Real-time storage space estimation.
* **Codec Support:** H.264, H.265 (HEVC), and VP9 (DASH-only).
* **Subtitles:** Automatic conversion of `.srt` files to `.vtt` for web compatibility.

## Technical Architecture

* **Backend:** PHP (Nextcloud App Framework), FFmpeg.
* **Frontend:** Vue.js (via Vite), Vanilla JS for "Files" integration.
* **Database:** Dedicated `oc_video_jobs` table for task persistence.
* **Worker:** Dedicated `systemd` process (`bin/worker.php`) for processing the job queue.

## Prerequisites

* **Nextcloud 32+** (not backwards compatible with earlier versions – for NC < 32, use [v1.0.6 from Funambules-Medias](https://github.com/Funambules-Medias/nextcloud-dash-video-converter))
* **FFmpeg 5.x** or higher installed on the server
* **PHP 8.1+**
* SSH access to configure the systemd worker

## Authors (PFE Team - ÉTS Montreal, Fall 2025)
* Nicolas Thibodeau
* Simon Bigonnesse
* Clément Deffes
* Abdessamad Cherifi

*Supervised by Professor Stéphane Coulombe*

## Credits

This project is based on the work of:
- **[danielfigueroajps](https://github.com/Funambules-Medias)** - Original Funambules Médias repositories
- **[PaulLereverend](https://github.com/PaulLereverend/NextcloudVideo_Converter)** - Original Nextcloud Video Converter

## License

[AGPL-3.0](COPYING)