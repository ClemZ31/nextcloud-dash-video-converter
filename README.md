# Video Converter FM for Nextcloud

**Languages:** [English](README_ENG.md) | [Français](README_FR.md)

---

A Nextcloud application for asynchronous video transcoding to adaptive streaming formats (DASH and HLS).

**Version:** 2.0.0

## Project Context

This application was developed as part of a **Final Year Project (PFE)** at **École de technologie supérieure (ÉTS)** in Montreal, in partnership with **[Funambules Médias](https://funambulesmedias.org/)**, a worker cooperative dedicated to documentary film programming and digital heritage preservation.

The goal is to build a fully **open-source video streaming system** that enables:
- One-click conversion of H.264 videos (2-20 GB) to HLS/MPEG-DASH within Nextcloud
- Scalable streaming for hundreds of concurrent viewers
- Digital sovereignty through self-hosted, open-source tools

## Quick Links

| Document | Description |
|----------|-------------|
| [README (English)](README_ENG.md) | Full documentation in English |
| [README (Français)](README_FR.md) | Documentation complète en français |
| [Admin Guide](GUIDE_ADMIN.md) | Server installation and configuration |
| [User Guide](GUIDE_UTILISATEUR.md) | How to use the application |

## Key Features

- **Adaptive Streaming**: MPEG-DASH (.mpd) + HLS (.m3u8)
- **Multi-Resolution**: 1080p, 720p, 480p, 360p, 240p, 144p
- **Codecs**: H.264, H.265 (HEVC), VP9
- **Async Processing**: Background worker, non-blocking UI
- **Subtitles**: SRT to WebVTT conversion

## Requirements

- **Nextcloud** 32+
- **FFmpeg** 5.x or higher
- **PHP** 8.1+

## Authors (PFE Team - ÉTS Montreal, Fall 2025)

- Nicolas Thibodeau
- Simon Bigonnesse  
- Clément Deffes
- Abdessamad Cherifi

*Supervised by Professor Stéphane Coulombe*

## Credits

This project is based on the work of:
- **[danielfigueroajps](https://github.com/Funambules-Medias)** - Original Funambules Médias repositories
- **[PaulLereverend](https://github.com/PaulLereverend/NextcloudVideo_Converter)** - Original Nextcloud Video Converter

## License

[AGPL-3.0](COPYING)
