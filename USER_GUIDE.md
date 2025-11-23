# User Guide - Video Converter

This module allows you to transform raw video files (MP4, MOV, MKV) into optimized formats for web streaming (DASH and HLS).

## 1. Starting a Conversion

1.  Navigate to your Nextcloud **Files** app.
2.  **Right-click** on a video file (or click the **...** menu).
3.  Select **"Convert to adaptive profile"** (or "Convertir...").

A window will open offering two modes:

### Simple Mode (Recommended)
This is the default mode. It applies the best settings for maximum compatibility.
* **Summary:** Displays the profile that will be applied.
* **Estimations:** Provides an estimate of the required disk space and processing time.
* Click **"Start conversion"**.

### Advanced Mode
For expert users wishing to fine-tune the output.
* **Output Formats:** Choose to generate DASH, HLS, or both.
* **Renditions (Quality):** Check the desired resolutions (from 144p to 1080p). You can manually adjust the video and audio bitrate for each quality level.
* **Codecs:**
    * *H.264:* Best compatibility.
    * *H.265:* Better compression (smaller files), but less compatible.
    * *VP9:* High-performance open-source alternative.
* **Subtitles:** Check "Convert subtitles" to automatically transform accompanying `.srt` files.

## 2. Monitoring Conversions

Once the conversion has started, you do not need to stay on the page.

1.  Click the **"Conversions"** app icon in the Nextcloud top bar (or via the Apps menu).
2.  You will see your task list:
    * 🟠 **Pending:** The task is in the queue.
    * 🔵 **Processing:** The progress bar shows current status.
    * 🟢 **Completed:** The video is ready.
    * 🔴 **Failed:** An error occurred (hover over the status for details).

## 3. Result

Upon completion, a new folder is created next to your original video, named `VideoName_dash`. It contains:
* Playback manifests (`.mpd`, `.m3u8`).
* Optimized video segments.
* A thumbnail and converted subtitles.

You can now share this folder or use the integrated video player to watch the film.