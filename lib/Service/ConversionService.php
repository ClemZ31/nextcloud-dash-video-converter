<?php
namespace OCA\Video_Converter_Fm\Service;

use OCA\Video_Converter_Fm\Db\VideoJob;
use OCA\Video_Converter_Fm\Db\VideoJobMapper;
use Psr\Log\LoggerInterface;
use OC\Files\Filesystem;

/**
 * Service centralisant la logique de conversion vidéo
 */
class ConversionService {
    private const RENDITION_PRESETS = [
        '1080p' => ['width' => 1920, 'height' => 1080, 'label' => '1080p'],
        '720p' => ['width' => 1280, 'height' => 720, 'label' => '720p'],
        '480p' => ['width' => 854, 'height' => 480, 'label' => '480p'],
        '360p' => ['width' => 640, 'height' => 360, 'label' => '360p'],
        '240p' => ['width' => 426, 'height' => 240, 'label' => '240p'],
        '144p' => ['width' => 256, 'height' => 144, 'label' => '144p'],
    ];

    private const SUPPORTED_VIDEO_CODECS = ['libx264', 'libx265', 'libvpx-vp9'];
    private const SUPPORTED_AUDIO_CODECS = ['aac', 'opus', 'mp3'];
    private const SUPPORTED_FFMPEG_PRESETS = ['ultrafast', 'superfast', 'veryfast', 'faster', 'fast', 'medium', 'slow', 'slower', 'veryslow'];

    private $mapper;
    private $logger;

    public function __construct(VideoJobMapper $mapper, LoggerInterface $logger) {
        $this->mapper = $mapper;
        $this->logger = $logger;
    }

    /**
     * Crée un nouveau job de conversion
     */
    public function createJob(
        string $userId,
        string $fileId,
        string $inputPath,
        array $conversionParams
    ): VideoJob {
        $job = new VideoJob();
        $job->setUserId($userId);
        $job->setFileId($fileId);
        $job->setInputPath($inputPath);
        $job->setOutputFormats(json_encode($conversionParams));
        $job->setStatus('pending');
        $job->setCreatedAt(date('Y-m-d H:i:s'));
        $job->setProgress(0);
        $job->setRetryCount(0);

        return $this->mapper->insert($job);
    }

    /**
     * Exécute un job de conversion
     */
    public function executeJob(VideoJob $job): bool {
        try {
            // Marquer comme en cours
            $this->mapper->updateStatus($job->getId(), 'processing');
            $job->setStatus('processing');
            $job->setWorkerHost(gethostname());
            $this->mapper->update($job);

            $params = json_decode($job->getOutputFormats(), true);
            $inputPath = $job->getInputPath();

            // Setup FS pour l'utilisateur
            \OC_Util::tearDownFS();
            \OC_Util::setupFS($job->getUserId());

            $localFile = Filesystem::getLocalFile($inputPath);
            
            if (!file_exists($localFile)) {
                throw new \Exception("File not found: {$localFile}");
            }

            $this->logger->info("Processing job {$job->getId()}: {$localFile}", ['app' => 'video_converter_fm']);

            // Construire et exécuter la commande FFmpeg
            $cmd = $this->buildFFmpegCommand($localFile, $params);
            $this->logger->info("Executing: {$cmd}", ['app' => 'video_converter_fm']);

            // Exécuter FFmpeg avec suivi de progression
            $returnCode = $this->executeFFmpegWithProgress($cmd, $job);

            if ($returnCode !== 0) {
                $errorMsg = "FFmpeg failed with code {$returnCode}";
                $this->logger->error($errorMsg, ['app' => 'video_converter_fm']);
                $this->mapper->updateStatus($job->getId(), 'failed', $errorMsg);
                return false;
            }

            // Marquer le job comme terminé
            $this->mapper->updateStatus($job->getId(), 'completed');
            $this->mapper->updateProgress($job->getId(), 100);

            // Re-scanner les fichiers
            $this->rescanFiles();

            $this->logger->info("Job {$job->getId()} completed successfully", ['app' => 'video_converter_fm']);
            return true;

        } catch (\Exception $e) {
            $errorMsg = "Job {$job->getId()} failed: " . $e->getMessage();
            $this->logger->error($errorMsg, ['app' => 'video_converter_fm']);
            $this->mapper->updateStatus($job->getId(), 'failed', $e->getMessage());
            
            // Incrémenter le compteur de retry
            $job->setRetryCount($job->getRetryCount() + 1);
            $this->mapper->update($job);

            return false;
        }
    }

    /**
     * Construit la commande FFmpeg à partir des paramètres
     */
    private function buildFFmpegCommand(string $file, array $params): string {
        $advanced = $this->buildAdaptiveStreamingCommand($file, $params);
        if ($advanced !== null) {
            return $advanced;
        }

        return $this->buildLegacyCommand($file, $params);
    }

    private function buildAdaptiveStreamingCommand(string $file, array $params): ?string {
        $profile = $params['profile'] ?? null;
        if (is_string($profile)) {
            $decodedProfile = json_decode($profile, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $profile = $decodedProfile;
            }
        }

        if (!is_array($profile)) {
            $profile = [];
        }

        $formats = $profile['formats'] ?? $params['selected_formats'] ?? [];
        if (is_string($formats)) {
            $decodedFormats = json_decode($formats, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $formats = $decodedFormats;
            }
        }

        if (!is_array($formats)) {
            $formats = [];
        }

        $formats = array_values(array_unique(array_filter(array_map(static function ($format) {
            return is_string($format) ? strtolower($format) : null;
        }, $formats))));
        $formats = array_values(array_intersect($formats, ['dash', 'hls']));

        if (empty($formats)) {
            return null;
        }

        $renditions = $profile['renditions'] ?? $params['renditions'] ?? [];
        if (is_string($renditions)) {
            $decodedRenditions = json_decode($renditions, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $renditions = $decodedRenditions;
            }
        }

        if (!is_array($renditions) || empty($renditions)) {
            return null;
        }

        $enabledVariants = $this->extractEnabledRenditions($renditions);
        if (empty($enabledVariants)) {
            return null;
        }

        $videoCodec = $this->sanitizeVideoCodec($profile['videoCodec'] ?? $params['codec'] ?? 'libx264');
        $audioCodec = $this->sanitizeAudioCodec($profile['audioCodec'] ?? $params['audio_codec'] ?? 'aac');
        $preset = $this->sanitizePreset($profile['preset'] ?? $params['preset'] ?? 'slow');
        $priority = $params['priority'] ?? $profile['priority'] ?? '0';
        $niceValue = null;
        if (is_numeric($priority)) {
            $intPriority = (int)$priority;
            if ($intPriority !== 0) {
                $niceValue = $intPriority;
            }
        }

        $segmentDuration = (int)($profile['segmentDuration'] ?? $params['segment_duration'] ?? 4);
        if ($segmentDuration <= 0) {
            $segmentDuration = 4;
        }

        $keyframeInterval = (int)($profile['keyframeInterval'] ?? $params['keyframe_interval'] ?? 48);
        if ($keyframeInterval <= 0) {
            $keyframeInterval = 48;
        }

        $filterComplex = $this->buildFilterComplex($enabledVariants);
        $codecArgs = $this->buildCodecArgs($enabledVariants, $videoCodec, $audioCodec, $preset, $keyframeInterval);

        $commands = [];
        foreach ($formats as $format) {
            if ($format === 'hls') {
                $commands[] = $this->buildHlsCommand($file, $filterComplex, $codecArgs, $enabledVariants, $segmentDuration, $profile['hls'] ?? []);
            } elseif ($format === 'dash') {
                $commands[] = $this->buildDashCommand($file, $filterComplex, $codecArgs, $enabledVariants, $segmentDuration, $profile['dash'] ?? []);
            }
        }

        $commands = array_values(array_filter($commands));
        if (empty($commands)) {
            return null;
        }

        if ($niceValue !== null) {
            $commands = array_map(static function ($cmd) use ($niceValue) {
                return preg_replace('/ffmpeg\s+-y/', 'nice -n ' . $niceValue . ' ffmpeg -y', $cmd, 1);
            }, $commands);
        }

        return implode(' && ', $commands);
    }

    private function buildLegacyCommand(string $file, array $params): string {
        $preset = $params['preset'] ?? 'slow';
        $output = $params['type'] ?? 'mp4';
        $priority = $params['priority'] ?? '0';
        $movflags = $params['movflags'] ?? false;
        $codec = $params['codec'] ?? null;
        $vbitrate = $params['vbitrate'] ?? null;
        $scale = $params['scale'] ?? null;

        $middleArgs = "";

        if ($output == "webm") {
            switch ($preset) {
                case 'faster':
                    $middleArgs = "-vcodec libvpx -cpu-used 1 -threads 16";
                    break;
                case 'veryfast':
                    $middleArgs = "-vcodec libvpx -cpu-used 2 -threads 16";
                    break;
                case 'superfast':
                    $middleArgs = "-vcodec libvpx -cpu-used 4 -threads 16";
                    break;
                case 'ultrafast':
                    $middleArgs = "-vcodec libvpx -cpu-used 5 -threads 16 -deadline realtime";
                    break;
                default:
                    break;
            }
        } else {
            if ($codec != null) {
                switch ($codec) {
                    case 'x264':
                        $middleArgs = "-vcodec libx264 -preset " . escapeshellarg($preset) . " -strict -2";
                        break;
                    case 'x265':
                        $middleArgs = "-vcodec libx265 -preset " . escapeshellarg($preset) . " -strict -2";
                        break;
                    case 'vp9':
                        $middleArgs = "-vcodec libvpx-vp9 -preset " . escapeshellarg($preset);
                        break;
                }
            } else {
                $middleArgs = "-preset " . escapeshellarg($preset) . " -strict -2";
            }

            if ($movflags) {
                $middleArgs .= " -movflags +faststart ";
            }

            if ($vbitrate != null) {
                $bitrateMap = [
                    '1' => '1000k', '2' => '2000k', '3' => '3000k',
                    '4' => '4000k', '5' => '5000k', '6' => '6000k', '7' => '7000k'
                ];
                $vbitrate = $bitrateMap[$vbitrate] ?? '2000k';
                $middleArgs .= " -b:v " . $vbitrate;
            }

            if ($scale != null) {
                $scaleMap = [
                    'vga' => '-vf scale=640:480',
                    'wxga' => '-vf scale=1280:720',
                    'hd' => '-vf scale=1368:768',
                    'fhd' => '-vf scale=1920:1080',
                    'uhd' => '-vf scale=3840:2160',
                    '320' => '-vf scale=-1:320',
                    '480' => '-vf scale=-1:480',
                    '600' => '-vf scale=-1:600',
                    '720' => '-vf scale=-1:720',
                    '1080' => '-vf scale=-1:1080',
                ];
                $scale = $scaleMap[$scale] ?? '';
                $middleArgs .= " " . $scale;
            }
        }

        if ($codec == "copy") {
            $middleArgs = "-codec copy";
        }

        $ffmpegPath = " "; // Prod: assumes ffmpeg in PATH
        $outputFile = dirname($file) . '/' . pathinfo($file, PATHINFO_FILENAME) . "." . $output;

        $cmd = $ffmpegPath . "ffmpeg -y -i " . escapeshellarg($file) . " " . $middleArgs . " " . escapeshellarg($outputFile);

        if ($priority != "0") {
            $cmd = "nice -n " . escapeshellarg($priority) . " " . $cmd;
        }

        return $cmd;
    }

    private function sanitizeVideoCodec($codec): string {
        if (!is_string($codec)) {
            return 'libx264';
        }
        $codec = strtolower($codec);
        return in_array($codec, self::SUPPORTED_VIDEO_CODECS, true) ? $codec : 'libx264';
    }

    private function sanitizeAudioCodec($codec): string {
        if (!is_string($codec)) {
            return 'aac';
        }
        $codec = strtolower($codec);
        return in_array($codec, self::SUPPORTED_AUDIO_CODECS, true) ? $codec : 'aac';
    }

    private function sanitizePreset($preset): string {
        if (!is_string($preset)) {
            return 'slow';
        }
        $preset = strtolower($preset);
        return in_array($preset, self::SUPPORTED_FFMPEG_PRESETS, true) ? $preset : 'slow';
    }

    private function extractEnabledRenditions(array $renditions): array {
        $variants = [];
        foreach ($renditions as $key => $definition) {
            if (!is_array($definition)) {
                continue;
            }
            $enabled = $definition['enabled'] ?? true;
            if (!$enabled) {
                continue;
            }
            $preset = self::RENDITION_PRESETS[$key] ?? null;
            if ($preset === null) {
                continue;
            }
            [$width, $height] = [$preset['width'], $preset['height']];
            $videoBitrate = (int)($definition['videoBitrate'] ?? 0);
            $audioBitrate = (int)($definition['audioBitrate'] ?? 0);
            if ($videoBitrate <= 0) {
                $videoBitrate = 1000;
            }
            if ($audioBitrate <= 0) {
                $audioBitrate = 128;
            }
            $variants[] = [
                'id' => is_string($key) ? $key : (string)$key,
                'label' => $definition['label'] ?? $preset['label'],
                'width' => $width,
                'height' => $height,
                'videoBitrate' => $videoBitrate,
                'audioBitrate' => $audioBitrate,
            ];
        }

        usort($variants, static function ($a, $b) {
            return $b['height'] <=> $a['height'];
        });

        return $variants;
    }

    private function buildFilterComplex(array $variants): string {
        $parts = [];
        $splitOutputs = [];
        $variantCount = count($variants);

        for ($i = 0; $i < $variantCount; $i++) {
            $splitOutputs[] = sprintf('[v%d]', $i);
        }

        $parts[] = sprintf('[0:v]split=%d%s', $variantCount, implode('', $splitOutputs));

        foreach ($variants as $index => $variant) {
            $parts[] = sprintf('[v%d]scale=w=%d:h=%d:force_original_aspect_ratio=decrease[v%1$d_out]', $index, $variant['width'], $variant['height']);
        }

        return implode(';', $parts);
    }

    private function buildCodecArgs(array $variants, string $videoCodec, string $audioCodec, string $preset, int $keyframeInterval): array {
        $args = [];
        foreach ($variants as $index => $variant) {
            $videoBitrate = $variant['videoBitrate'] . 'k';
            $audioBitrate = $variant['audioBitrate'] . 'k';
            $bufSize = ($variant['videoBitrate'] * 2) . 'k';
            $nameSuffix = preg_replace('/[^a-z0-9]+/i', '', strtolower($variant['id']));

            $args[] = implode(' ', [
                sprintf('-map "[v%d_out]"', $index),
                sprintf('-c:v:%d %s', $index, $videoCodec),
                sprintf('-preset %s', $preset),
                sprintf('-b:v:%d %s', $index, $videoBitrate),
                sprintf('-maxrate:v:%d %s', $index, $videoBitrate),
                sprintf('-bufsize:v:%d %s', $index, $bufSize),
                sprintf('-g %d', $keyframeInterval),
                sprintf('-keyint_min %d', $keyframeInterval),
                '-sc_threshold 0',
                sprintf('-metadata:s:v:%d variant_bitrate=%d', $index, $variant['videoBitrate'] * 1000),
                sprintf('-metadata:s:v:%d variant_id=%s', $index, $nameSuffix ?: ('v' . $index)),
            ]);

            $args[] = implode(' ', [
                '-map 0:a:0',
                sprintf('-c:a:%d %s', $index, $audioCodec),
                sprintf('-b:a:%d %s', $index, $audioBitrate),
                '-ac 2',
            ]);
        }

        return $args;
    }

    private function buildHlsCommand(
        string $file,
        string $filterComplex,
        array $codecArgs,
        array $variants,
        int $segmentDuration,
        array $options
    ): string {
        $basePath = dirname($file) . '/' . pathinfo($file, PATHINFO_FILENAME);
        $hlsDir = $basePath . '_hls';
        $segmentPattern = $hlsDir . '/%v/segment_%03d.ts';
        $playlistPattern = $hlsDir . '/%v/index.m3u8';

        $dirCommands = [sprintf('mkdir -p %s', escapeshellarg($hlsDir))];
        foreach ($variants as $variant) {
            $dirCommands[] = sprintf('mkdir -p %s', escapeshellarg($hlsDir . '/' . $variant['id']));
        }

        $flags = [];
        if (($options['independentSegments'] ?? ($options['independent_segments'] ?? true)) === true) {
            $flags[] = 'independent_segments';
        }
        if (($options['deleteSegments'] ?? false) === true) {
            $flags[] = 'delete_segments';
        }
        if (($options['strftimeMkdir'] ?? false) === true) {
            $flags[] = 'strftime_mkdir';
        }

        $varStreamMapParts = [];
        foreach ($variants as $index => $variant) {
            $name = preg_replace('/[^a-z0-9]+/i', '', strtolower($variant['id']));
            $varStreamMapParts[] = sprintf('v:%d,a:%d name:%s', $index, $index, $name ?: 'v' . $index);
        }
        $varStreamMap = implode(' ', $varStreamMapParts);

        $commandParts = array_merge(
            [
                'ffmpeg -y',
                '-i ' . escapeshellarg($file),
                '-filter_complex ' . escapeshellarg($filterComplex),
            ],
            $codecArgs,
            [
                '-f hls',
                '-hls_time ' . max(1, $segmentDuration),
                '-hls_playlist_type vod',
                '-hls_segment_filename ' . escapeshellarg($segmentPattern),
                '-master_pl_name master.m3u8',
                '-var_stream_map ' . escapeshellarg($varStreamMap),
            ]
        );

        if (!empty($flags)) {
            $commandParts[] = '-hls_flags ' . escapeshellarg(implode('+', $flags));
        }

        $commandParts[] = escapeshellarg($playlistPattern);

        return implode(' && ', $dirCommands) . ' && ' . implode(' ', array_filter($commandParts));
    }

    private function buildDashCommand(
        string $file,
        string $filterComplex,
        array $codecArgs,
        array $variants,
        int $segmentDuration,
        array $options
    ): string {
        $basePath = dirname($file) . '/' . pathinfo($file, PATHINFO_FILENAME);
        $dashDir = $basePath . '_dash';
        $manifestPath = $dashDir . '/manifest.mpd';

        $dirCommand = sprintf('mkdir -p %s', escapeshellarg($dashDir));

        $useTemplate = ($options['useTemplate'] ?? true) ? 1 : 0;
        $useTimeline = ($options['useTimeline'] ?? true) ? 1 : 0;

        $commandParts = array_merge(
            [
                'ffmpeg -y',
                '-i ' . escapeshellarg($file),
                '-filter_complex ' . escapeshellarg($filterComplex),
            ],
            $codecArgs,
            [
                '-f dash',
                '-seg_duration ' . max(1, $segmentDuration),
                '-use_template ' . $useTemplate,
                '-use_timeline ' . $useTimeline,
                '-init_seg_name ' . escapeshellarg('init_$RepresentationID$.m4s'),
                '-media_seg_name ' . escapeshellarg('chunk_$RepresentationID$_$Number$.m4s'),
                '-adaptation_sets "id=0,streams=v id=1,streams=a"',
            ]
        );

        return $dirCommand . ' && ' . implode(' ', array_filter($commandParts)) . ' ' . escapeshellarg($manifestPath);
    }

    /**
     * Re-scanne tous les fichiers
     */
    private function rescanFiles(): void {
        exec("php /var/www/nextcloud/occ files:scan --all > /dev/null 2>&1 &");
    }

    /**
     * Exécute FFmpeg avec suivi de progression en temps réel
     */
    private function executeFFmpegWithProgress(string $cmd, VideoJob $job): int {
        // D'abord, obtenir la durée totale de la vidéo
        $inputFile = null;
        if (preg_match('/-i\s+["\']([^"\']+)["\']/', $cmd, $matches)) {
            $inputFile = $matches[1];
        }

        $totalDuration = $this->getVideoDuration($inputFile);
        
        // Lancer FFmpeg avec proc_open pour capturer stderr en temps réel
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr (où FFmpeg affiche la progression)
        ];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            $this->logger->error("Failed to start FFmpeg process", ['app' => 'video_converter_fm']);
            return 1;
        }

        // Fermer stdin (pas besoin)
        fclose($pipes[0]);

        // Mettre stderr en mode non-bloquant pour lire ligne par ligne
        stream_set_blocking($pipes[2], false);

        $output = '';
        $lastUpdateTime = 0;

        while (!feof($pipes[2])) {
            $line = fgets($pipes[2]);
            
            if ($line === false) {
                usleep(100000); // 0.1 seconde
                continue;
            }

            $output .= $line;

            // Parser la progression FFmpeg (chercher "time=")
            // Exemple: frame= 1234 fps= 30 q=28.0 size=   12345kB time=00:01:23.45 bitrate= 123.4kbits/s speed=1.23x
            if (preg_match('/time=(\d{2}):(\d{2}):(\d{2}\.\d{2})/', $line, $matches)) {
                $hours = (int)$matches[1];
                $minutes = (int)$matches[2];
                $seconds = (float)$matches[3];
                $currentTime = $hours * 3600 + $minutes * 60 + $seconds;

                // Calculer le pourcentage
                if ($totalDuration > 0) {
                    $progress = min(99, (int)(($currentTime / $totalDuration) * 100));
                    
                    // Mettre à jour la BDD toutes les 2 secondes seulement
                    $now = time();
                    if ($now - $lastUpdateTime >= 2) {
                        $this->mapper->updateProgress($job->getId(), $progress);
                        $this->logger->debug("Job {$job->getId()} progress: {$progress}%", ['app' => 'video_converter_fm']);
                        $lastUpdateTime = $now;
                    }
                }
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $returnCode = proc_close($process);

        return $returnCode;
    }

    /**
     * Obtient la durée totale d'une vidéo avec ffprobe
     */
    private function getVideoDuration(string $filePath): float {
        if (!$filePath || !file_exists($filePath)) {
            return 0;
        }

        $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($filePath);
        $output = shell_exec($cmd);
        
        return $output ? (float)trim($output) : 0;
    }
}
