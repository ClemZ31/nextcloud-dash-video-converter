# Nextcloud Video Converter (PFE)

A Nextcloud application designed for asynchronous video transcoding into adaptive streaming formats (DASH and HLS). Built to be high-performance, non-blocking, and natively integrated into the Files interface.

## 🌟 Key Features

* **Adaptive Streaming:** Automatically generates **MPEG-DASH (.mpd)** and **HLS (.m3u8)** manifests.
* **Multi-Resolution:** Creates multiple renditions (1080p, 720p, 480p, etc.) to adapt to the client's bandwidth.
* **Asynchronous Architecture:** Uses background PHP workers to ensure the Nextcloud user interface never freezes during conversion.
* **Modern Interface:**
    * Integrated "Convert to..." action in the Files context menu.
    * Task monitoring dashboard (Vue.js).
    * Real-time storage space estimation.
* **Codec Support:** H.264, H.265 (HEVC), and VP9.
* **Subtitles:** Automatic conversion of `.srt` files to `.vtt` for web compatibility.

## 🛠️ Technical Architecture

* **Backend:** PHP (Nextcloud App Framework), FFmpeg.
* **Frontend:** Vue.js (via Vite), Vanilla JS for "Files" integration.
* **Database:** Dedicated `oc_video_jobs` table for task persistence.
* **Worker:** Dedicated `systemd` process (`bin/worker.php`) for processing the job queue.

## 📋 Prerequisites

* Nextcloud 25 to 33.
* **FFmpeg** installed on the server (`/usr/bin/ffmpeg` or available in PATH).
* SSH access to configure the Systemd worker.

## 👥 Authors (PFE Team)
* Nicolas Thibodeau
* Simon Bigonnesse
* Clément Deffes
* Abdessamad Cherifi