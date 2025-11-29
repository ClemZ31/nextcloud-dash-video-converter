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
        '480p' => ['width' => 854, 'height' => 480, 'label' => '480p'],  // will round to 852 if needed
        '360p' => ['width' => 640, 'height' => 360, 'label' => '360p'],
        '240p' => ['width' => 426, 'height' => 240, 'label' => '240p'],  // will round to 426 if needed
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
            echo "[video_converter_fm] execJob #{$job->getId()} file={$localFile}\n";

            // Préparer le dossier de sortie horodaté
            $pathInfo = pathinfo($localFile);
            $baseName = $pathInfo['filename'] ?? pathinfo($localFile, PATHINFO_FILENAME);
            $timestamp = date('Y_m_d_H_i_s');
            $folderName = $baseName . '_' . $timestamp;
            $outputDir = ($pathInfo['dirname'] ?? dirname($localFile)) . '/' . $folderName;

            $params['output_directory'] = $outputDir;
            $params['output_base_name'] = $baseName;
            $params['output_folder'] = $folderName;
            $params['output_timestamp'] = $timestamp;

            $inputParent = trim(dirname($inputPath), '/');
            $params['output_nc_path'] = ($inputParent === '' ? '' : '/' . $inputParent) . '/' . $folderName;

            // Persister les informations enrichies pour le suivi
            $job->setOutputFormats(json_encode($params));
            $this->mapper->update($job);

            // Construire et exécuter la commande FFmpeg
            $cmd = $this->buildFFmpegCommand($localFile, $params);
            $this->logger->info("Executing: {$cmd}", ['app' => 'video_converter_fm']);
            echo "[video_converter_fm] Executing: {$cmd}\n";

            // Exécuter FFmpeg avec suivi de progression
            $returnCode = $this->executeFFmpegWithProgress($cmd, $job);

            if ($returnCode !== 0) {
                $errorMsg = "FFmpeg failed with code {$returnCode}";
                $this->logger->error($errorMsg, ['app' => 'video_converter_fm']);
                echo "[video_converter_fm] {$errorMsg}\n";
                $this->mapper->updateStatus($job->getId(), 'failed', $errorMsg);
                // Increment retry count on failure
                $job->setRetryCount($job->getRetryCount() + 1);
                $this->mapper->update($job);
                return false;
            }

            // Copier les assets associés (affiche, sous-titres)
            $this->postProcessAssets($localFile, $params);

            // Re-scanner les fichiers
            $this->rescanFiles();

            // Déterminer formats/renditions à partir des paramètres pour vérification
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

            $renditions = $profile['renditions'] ?? $params['renditions'] ?? [];
            if (is_string($renditions)) {
                $decodedRenditions = json_decode($renditions, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $renditions = $decodedRenditions;
                }
            }
            if (!is_array($renditions)) {
                $renditions = [];
            }
            $enabledVariants = $this->extractEnabledRenditions($renditions);

            // Vérifier que les artefacts attendus ont été produits
            $wantsDash = in_array('dash', $formats, true);
            $wantsHls = in_array('hls', $formats, true);
            $ok = $this->verifyConversionArtifacts($params, $enabledVariants, $wantsDash, $wantsHls, $baseName);
            if (!$ok) {
                $errorMsg = 'Conversion errors: missing expected artifacts in output folder';
                $this->logger->error($errorMsg, ['app' => 'video_converter_fm']);
                $this->mapper->updateStatus($job->getId(), 'failed', $errorMsg);
                return false;
            }

            // Marquer le job comme terminé
            $this->mapper->updateStatus($job->getId(), 'completed');
            $this->mapper->updateProgress($job->getId(), 100);

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

        $outputDir = $params['output_directory'] ?? null;
        $outputBaseName = $params['output_base_name'] ?? pathinfo($file, PATHINFO_FILENAME);
        if ($outputDir === null) {
            $pathInfo = pathinfo($file);
            $outputDir = ($pathInfo['dirname'] ?? dirname($file)) . '/' . $outputBaseName;
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

        $hasAudio = $this->hasAudioStream($file);
        $this->logger->debug("Input has audio: " . ($hasAudio ? 'yes' : 'no'), ['app' => 'video_converter_fm']);

        // Build single filter graph for video (no audio filtering)
        $filterData = $this->buildFilterComplex($enabledVariants, false, 'none');
        $filterComplex = $filterData['graph'];
        $videoLabels = $filterData['videoLabels'];
        $this->logger->debug("Filter complex (video only): " . $filterComplex, ['app' => 'video_converter_fm']);

        // Calculate max audio bitrate once for all formats
        $maxAudioBitrate = 0;
        if ($hasAudio) {
            foreach ($enabledVariants as $variant) {
                if (isset($variant['audioBitrate']) && $variant['audioBitrate'] > $maxAudioBitrate) {
                    $maxAudioBitrate = $variant['audioBitrate'];
                }
            }
        }
        $audioBitrate = $maxAudioBitrate > 0 ? ($maxAudioBitrate . 'k') : '128k';

        $commands = [];

        // Build codec args without audio - adaptive command(s) decide audio mapping
        $codecArgs = $this->buildVideoCodecArgs($enabledVariants, $videoLabels, $videoCodec, $preset, $keyframeInterval);
        $this->logCodecArgs($codecArgs, 'cmaf-unified');

        $wantsDash = in_array('dash', $formats, true);
        $wantsHls = in_array('hls', $formats, true);

        if ($wantsDash && $wantsHls) {
            // Utilise une seule commande DASH avec génération HLS activée
            $commands[] = $this->buildDashCommand(
                $file,
                $filterComplex,
                $codecArgs,
                $enabledVariants,
                $segmentDuration,
                $profile['dash'] ?? [],
                $hasAudio,
                $audioBitrate,
                $audioCodec,
                true, // generateHlsPlaylist = TRUE
                $outputDir,
                $outputBaseName
            );
        } elseif ($wantsDash) {
            $commands[] = $this->buildDashCommand(
                $file,
                $filterComplex,
                $codecArgs,
                $enabledVariants,
                $segmentDuration,
                $profile['dash'] ?? [],
                $hasAudio,
                $audioBitrate,
                $audioCodec,
                false,
                $outputDir,
                $outputBaseName
            );
        } elseif ($wantsHls) {
            $commands[] = $this->buildHlsCommand(
                $file,
                $filterComplex,
                $codecArgs,
                $enabledVariants,
                $segmentDuration,
                $profile['hls'] ?? [],
                $hasAudio,
                $audioBitrate,
                $audioCodec,
                $outputDir,
                $outputBaseName
            );
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
                    default:
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

    private function buildFilterComplex(array $variants, bool $hasAudio, string $audioMode = 'per_variant'): array {
        if ($audioMode !== 'per_variant' && $audioMode !== 'shared') {
            $audioMode = 'per_variant';
        }

        $parts = [];
        $variantCount = count($variants);
        $videoLabels = [];
        $audioLabels = [];

        if ($variantCount === 1) {
            $variant = $variants[0];
            $parts[] = sprintf('[0:v]scale=w=%d:h=%d:force_original_aspect_ratio=decrease,scale=trunc(iw/2)*2:trunc(ih/2)*2[v0_out]', $variant['width'], $variant['height']);
            $videoLabels[] = '[v0_out]';

            if ($hasAudio) {
                if ($audioMode === 'shared') {
                    $parts[] = '[0:a:0]aformat=sample_fmts=fltp:sample_rates=48000:channel_layouts=stereo[a0_out]';
                    $audioLabels[] = '[a0_out]';
                } else {
                    // Always stick to the first source audio stream
                    $audioLabels[] = '0:a:0';
                }
            }

            return [
                'graph' => implode(';', $parts),
                'videoLabels' => $videoLabels,
                'audioLabels' => $audioLabels,
            ];
        }

        // Video splitting for multiple variants
        $splitOutputs = [];
        for ($i = 0; $i < $variantCount; $i++) {
            $splitOutputs[] = sprintf('[v%d]', $i);
        }

        $parts[] = sprintf('[0:v]split=%d%s', $variantCount, implode('', $splitOutputs));

        foreach ($variants as $index => $variant) {
            $parts[] = sprintf('[v%d]scale=w=%d:h=%d:force_original_aspect_ratio=decrease,scale=trunc(iw/2)*2:trunc(ih/2)*2[v%1$d_out]', $index, $variant['width'], $variant['height']);
            $videoLabels[] = sprintf('[v%d_out]', $index);
        }

        if ($hasAudio) {
            if ($audioMode === 'shared') {
                $parts[] = '[0:a:0]aformat=sample_fmts=fltp:sample_rates=48000:channel_layouts=stereo[a0_out]';
                $audioLabels[] = '[a0_out]';
            } else {
                $audioSplitOutputs = [];
                for ($i = 0; $i < $variantCount; $i++) {
                    $audioSplitOutputs[] = sprintf('[a%d]', $i);
                }

                $parts[] = sprintf('[0:a:0]asplit=%d%s', $variantCount, implode('', $audioSplitOutputs));

                foreach ($audioSplitOutputs as $index => $label) {
                    // Normalize audio to a common layout so each stream is independent
                    $parts[] = sprintf('%saformat=sample_fmts=fltp:sample_rates=48000:channel_layouts=stereo[a%d_out]', $label, $index);
                    $audioLabels[] = sprintf('[a%d_out]', $index);
                }
            }
        }

        return [
            'graph' => implode(';', $parts),
            'videoLabels' => $videoLabels,
            'audioLabels' => $audioLabels,
        ];
    }

    /**
     * Vérifie si le fichier source possède au moins une piste audio
     */
    private function hasAudioStream(string $filePath): bool {
        if (!$filePath || !file_exists($filePath)) {
            return false;
        }
        $cmd = 'ffprobe -v error -select_streams a:0 -show_entries stream=index -of csv=p=0 ' . escapeshellarg($filePath) . ' 2>&1';
        $output = shell_exec($cmd);
        return trim((string)$output) !== '';
    }

    /**
     * Build codec arguments for VIDEO streams only (no audio)
     */
    private function buildVideoCodecArgs(
        array $variants,
        array $videoLabels,
        string $videoCodec,
        string $preset,
        int $keyframeInterval
    ): array {
        $args = [];

        foreach ($variants as $index => $variant) {
            $videoLabel = $videoLabels[$index] ?? sprintf('[v%d_out]', $index);
            $videoBitrate = $variant['videoBitrate'] . 'k';
            $bufSize = ($variant['videoBitrate'] * 2) . 'k';
            $variantLabel = $variant['label'] ?? $variant['id'] ?? ('variant_' . $index);
            $variantId = sprintf('stream%d', $index);

            $videoMapTarget = escapeshellarg($videoLabel);

            $args[] = implode(' ', [
                sprintf('-map %s', $videoMapTarget),
                sprintf('-c:v:%d %s', $index, $videoCodec),
                sprintf('-preset %s', $preset),
                sprintf('-b:v:%d %s', $index, $videoBitrate),
                sprintf('-maxrate:v:%d %s', $index, $videoBitrate),
                sprintf('-bufsize:v:%d %s', $index, $bufSize),
                sprintf('-g %d', $keyframeInterval),
                sprintf('-keyint_min %d', $keyframeInterval),
                '-sc_threshold 0',
                sprintf('-metadata:s:v:%d variant_bitrate=%d', $index, $variant['videoBitrate'] * 1000),
                sprintf('-metadata:s:v:%d variant_id=%s', $index, $variantId),
                sprintf('-metadata:s:v:%d variant_label=%s', $index, escapeshellarg($variantLabel)),
            ]);
        }

        return $args;
    }

    private function logCodecArgs(array $codecArgs, string $context): void {
        $this->logger->debug(sprintf('Codec args (%s) count: %d', $context, count($codecArgs)), ['app' => 'video_converter_fm']);
        foreach ($codecArgs as $i => $arg) {
            $this->logger->debug(sprintf('  Codec arg (%s)[%d]: %s', $context, $i, $arg), ['app' => 'video_converter_fm']);
        }
    }

    private function buildDashCommand(
        string $file,
        string $filterComplex,
        array $codecArgs,
        array $variants,
        int $segmentDuration,
        array $options,
        bool $hasAudio,
        string $audioBitrate,
        string $audioCodec,
        bool $generateHlsPlaylist,
        string $outputDir,
        string $baseName
    ): string {
        $segmentsDir = rtrim($outputDir, '/') . '/segments';
        $dashSegmentsDir = $segmentsDir . '/dash';
        $hlsSegmentsDir = $segmentsDir . '/hls';
        $mpdPath = $outputDir . '/' . $baseName . '.mpd';

        // $dirCommand = sprintf(
        //     'mkdir -p %s && mkdir -p %s && mkdir -p %s',
        //     escapeshellarg($outputDir),
        //     escapeshellarg($dashSegmentsDir),
        //     escapeshellarg($hlsSegmentsDir)
        // );

        // Un seul sous-dossier segments/ (pas de séparation DASH/HLS pour éviter la duplication des segments)
        $dirCommand = sprintf('mkdir -p %s && mkdir -p %s', 
            escapeshellarg($outputDir),
            escapeshellarg($segmentsDir)
        );

        $useTemplate = ($options['useTemplate'] ?? true) ? 1 : 0;
        $useTimeline = ($options['useTimeline'] ?? true) ? 1 : 0;
        $adaptationSets = escapeshellarg('id=0,streams=v id=1,streams=a');

        $audioArgs = [];
        if ($hasAudio) {
            $audioArgs = [
                '-map 0:a:0',
                sprintf('-c:a:0 %s', $audioCodec),
                sprintf('-b:a:0 %s', $audioBitrate),
                '-ac 2',
            ];
        }

        $commandParts = [
            'ffmpeg -y',
            '-i ' . escapeshellarg($file),
        ];

        if ($filterComplex !== '') {
            $commandParts[] = '-filter_complex ' . escapeshellarg($filterComplex);
        }

        $commandParts = array_merge(
            $commandParts,
            $codecArgs,
            $audioArgs,
            [
                '-f dash',
                '-seg_duration ' . max(1, $segmentDuration),
                '-use_template ' . $useTemplate,
                '-use_timeline ' . $useTimeline,
                //'-init_seg_name ' . escapeshellarg('segments/dash/init-$RepresentationID$.m4s'),
                //'-media_seg_name ' . escapeshellarg('segments/dash/chunk-$RepresentationID$-$Number$.m4s'),
                '-init_seg_name ' . escapeshellarg('segments/init-$RepresentationID$.m4s'),
                '-media_seg_name ' . escapeshellarg('segments/chunk-$RepresentationID$-$Number$.m4s'),
                '-adaptation_sets ' . $adaptationSets,
            ]
        );

        if ($generateHlsPlaylist) {
            $commandParts[] = '-hls_playlist 1';
            $commandParts[] = '-hls_master_name ' . escapeshellarg($baseName . '.m3u8');
            $commandParts[] = '-hls_time ' . max(1, $segmentDuration);
            $commandParts[] = '-hls_segment_type fmp4';
            $commandParts[] = '-hls_flags independent_segments';
            
            // Le muxer DASH avec -hls_playlist 1 génère automatiquement les playlists HLS en référençant les segments DASH existants. 
            // Spécifier -hls_fmp4_init_filename et -hls_segment_filename crée une confusion.
            // $commandParts[] = '-hls_fmp4_init_filename ' . escapeshellarg('segments/hls/init-stream%v.m4s');
            // $commandParts[] = '-hls_segment_filename ' . escapeshellarg('segments/hls/chunk-stream%v-%05d.m4s');

            $varStreamMap = $this->buildHlsVarStreamMap($variants, $hasAudio, 'media_');
            if ($varStreamMap !== '') {
                $commandParts[] = '-var_stream_map ' . escapeshellarg($varStreamMap);
                $this->logger->debug('HLS var_stream_map: ' . $varStreamMap, ['app' => 'video_converter_fm']);
            }
        }

        return $dirCommand . ' && ' . implode(' ', array_filter($commandParts)) . ' ' . escapeshellarg($mpdPath);
    }

    private function buildHlsCommand(
        string $file,
        string $filterComplex,
        array $codecArgs,
        array $variants,
        int $segmentDuration,
        array $options,
        bool $hasAudio,
        string $audioBitrate,
        string $audioCodec,
        string $outputDir,
        string $baseName
    ): string {
        $segmentsDir = rtrim($outputDir, '/') . '/segments';

        $dirCommand = sprintf('mkdir -p %s && mkdir -p %s',
            escapeshellarg($outputDir),
            escapeshellarg($segmentsDir)
        );

        $commandParts = [
            'ffmpeg -y',
            '-i ' . escapeshellarg($file),
        ];

        if ($filterComplex !== '') {
            $commandParts[] = '-filter_complex ' . escapeshellarg($filterComplex);
        }

        // Encoder une seule piste audio partagée pour toutes les variantes
        if ($hasAudio) {
            $commandParts[] = '-map 0:a:0';
            $commandParts[] = sprintf('-c:a:0 %s', $audioCodec);
            $commandParts[] = sprintf('-b:a:0 %s', $audioBitrate);
            $commandParts[] = '-ac:a:0 2';
        }

        // Ajouter les mappings vidéo
        $commandParts = array_merge($commandParts, $codecArgs);

        $commandParts[] = '-f hls';
        $commandParts[] = '-hls_time ' . max(1, $segmentDuration);
        $commandParts[] = '-hls_playlist_type vod';
        $commandParts[] = '-hls_segment_type fmp4';
        $commandParts[] = '-master_pl_name ' . escapeshellarg($baseName . '.m3u8');

        // Chemins relatifs pour les segments
        $commandParts[] = '-hls_segment_filename ' . escapeshellarg('segments/chunk-stream%v-%05d.m4s');
        $commandParts[] = '-hls_fmp4_init_filename ' . escapeshellarg('segments/init-stream%v.m4s');

        $hlsFlags = [];
        if (($options['independentSegments'] ?? true) !== false) {
            $hlsFlags[] = 'independent_segments';
        }
        if (!empty($hlsFlags)) {
            $commandParts[] = '-hls_flags ' . implode('+', $hlsFlags);
        }

        // var_stream_map avec audio partagé (a:0)
        $varStreamMap = $this->buildHlsVarStreamMap($variants, $hasAudio, 'media_');
        if ($varStreamMap !== '') {
            $commandParts[] = '-var_stream_map ' . escapeshellarg($varStreamMap);
            $this->logger->debug('HLS var_stream_map: ' . $varStreamMap, ['app' => 'video_converter_fm']);
        }

        // Utiliser media_%v.m3u8 (cohérent avec name:media_X)
        $commandParts[] = escapeshellarg($outputDir . '/media_%v.m3u8');

        return $dirCommand . ' && ' . implode(' ', array_filter($commandParts));
    }
    /**
    * Build var_stream_map pour HLS avec audio multiplexé dans chaque variante
    * (chaque variante a sa propre copie de l'audio)
    */
    private function buildHlsVarStreamMapMultiplexed(array $variants, bool $hasAudio, string $namePrefix = 'media_'): string {
        if (empty($variants)) {
            return '';
        }

        $entries = [];
        foreach ($variants as $index => $variant) {
            if ($hasAudio) {
                // Chaque variante a son propre flux audio (a:INDEX)
                $entries[] = sprintf('v:%d,a:%d,name:%s%d', $index, $index, $namePrefix, $index);
            } else {
                $entries[] = sprintf('v:%d,name:%s%d', $index, $namePrefix, $index);
            }
        }

        return implode(' ', $entries);
    }

    private function buildHlsVarStreamMap(array $variants, bool $hasAudio, string $namePrefix = 'media_'): string {
        if (empty($variants)) {
            return '';
        }

        $entries = [];

        // D'abord toutes les vidéos, chaque variante utilise a:0
        foreach ($variants as $index => $variant) {
            if ($hasAudio) {
                $entries[] = sprintf('v:%d,a:0,name:%s%d', $index, $namePrefix, $index);
            } else {
                $entries[] = sprintf('v:%d,name:%s%d', $index, $namePrefix, $index);
            }
        }

        // // Ensuite seulement la piste audio (très important)
        // if ($hasAudio) {
        //     $entries[] = 'a:0,name:audio_0';
        // }

        return implode(' ', $entries);
    }

    /**
     * Re-scanne tous les fichiers
     */
    private function rescanFiles(): void {
        exec("php /var/www/nextcloud/occ files:scan --all > /dev/null 2>&1 &");
    }

    /**
     * Copy poster/subtitle assets to output folder and convert SRT to VTT if needed.
     */
    private function postProcessAssets(string $localFile, array $params): void {
        $outputDir = $params['output_directory'] ?? null;
        if (!$outputDir) {
            return;
        }

        $pathInfo = pathinfo($localFile);
        $baseName = $pathInfo['filename'] ?? pathinfo($localFile, PATHINFO_FILENAME);
        $inputDir = $pathInfo['dirname'] ?? dirname($localFile);

        // Ensure destination exists
        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0755, true);
        }

        // Copy poster image if exists (jpg/png/webp)
        $imageExts = ['jpg', 'jpeg', 'png', 'webp'];
        foreach ($imageExts as $ext) {
            $src = $inputDir . '/' . $baseName . '.' . $ext;
            if (is_file($src)) {
                $dst = $outputDir . '/' . $baseName . '.' . $ext;
                @copy($src, $dst);
                @chmod($dst, 0644);
                $this->logger->info("Copied poster asset: {$src} -> {$dst}", ['app' => 'video_converter_fm']);
                break;
            }
        }

        // Sous-titres : conversion de tous les SRT en VTT, copie de tous les VTT, gestion des suffixes de langue
        // Conversion SRT -> VTT
        foreach (glob($inputDir . '/' . $baseName . '*.srt') as $subsSrt) {
            $suffix = substr($subsSrt, strlen($inputDir . '/' . $baseName), -4); // e.g. _fr, _en, etc.
            $dstVtt = $outputDir . '/' . $baseName . $suffix . '.vtt';
            $cmd = sprintf('ffmpeg -y -i %s -f webvtt %s 2>&1', escapeshellarg($subsSrt), escapeshellarg($dstVtt));
            $this->logger->info("Converting SRT to VTT: {$subsSrt} -> {$dstVtt}", ['app' => 'video_converter_fm']);
            $output = null;
            $ret = 0;
            @exec($cmd, $output, $ret);
            if ($ret === 0) {
                @chmod($dstVtt, 0644);
                $this->logger->info("Subtitle converted and copied to output folder", ['app' => 'video_converter_fm']);
            } else {
                $this->logger->warning("Failed to convert SRT to VTT: cmd={$cmd}", ['app' => 'video_converter_fm']);
            }
        }
        // Copie de tous les VTT
        foreach (glob($inputDir . '/' . $baseName . '*.vtt') as $subsVtt) {
            $suffix = substr($subsVtt, strlen($inputDir . '/' . $baseName), -4);
            $dst = $outputDir . '/' . $baseName . $suffix . '.vtt';
            @copy($subsVtt, $dst);
            @chmod($dst, 0644);
            $this->logger->info("Copied VTT subtitle: {$subsVtt} -> {$dst}", ['app' => 'video_converter_fm']);
        }
    }

    /**
     * Verifie que les outputs attendus ont été produits par FFmpeg
     */
    private function verifyConversionArtifacts(array $params, array $variants, bool $wantsDash, bool $wantsHls, string $baseName): bool {
        $outputDir = $params['output_directory'] ?? null;
        if (!$outputDir || !is_dir($outputDir)) {
            $this->logger->error("Output directory missing: {$outputDir}", ['app' => 'video_converter_fm']);
            return false;
        }

        $ok = true;
        $segmentsDir = rtrim($outputDir, '/') . '/segments';
    
        // DASH
        if ($wantsDash) {
            $mpd = rtrim($outputDir, '/') . '/' . $baseName . '.mpd';
            if (!is_file($mpd)) {
                $this->logger->error("Missing MPD manifest: {$mpd}", ['app' => 'video_converter_fm']);
                $ok = false;
            }
        }

        // HLS
        if ($wantsHls) {
            $m3u8 = rtrim($outputDir, '/') . '/' . $baseName . '.m3u8';
            if (!is_file($m3u8)) {
                $this->logger->error("Missing HLS master playlist: {$m3u8}", ['app' => 'video_converter_fm']);
                $ok = false;
            }
        
            // Playlists variantes à la racine
            $hlsVariants = glob(rtrim($outputDir, '/') . '/media_*.m3u8');
            if (empty($hlsVariants)) {
                $this->logger->error("No HLS variant playlists found", ['app' => 'video_converter_fm']);
                $ok = false;
            }
        }

        // Vérifier les segments (partagés pour DASH+HLS, ou dédiés pour HLS seul)
        if (!is_dir($segmentsDir)) {
            $this->logger->error("Missing segments dir: {$segmentsDir}", ['app' => 'video_converter_fm']);
            $ok = false;
        } else {
            $init = glob($segmentsDir . '/init-*.m4s');
            $chunks = glob($segmentsDir . '/chunk-*.m4s');
            if (empty($init) || empty($chunks)) {
                $this->logger->error("Missing init/chunks in: {$segmentsDir}", ['app' => 'video_converter_fm']);
                $ok = false;
            }
        }

        return $ok;
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

        // Log FFmpeg output if there was an error
        if ($returnCode !== 0) {
            $tail = substr($output, -2000);
            $this->logger->error("FFmpeg failed with code {$returnCode}. Output: " . $tail, ['app' => 'video_converter_fm']);
            // Also echo to worker log for easier live debugging
            echo "[video_converter_fm] FFmpeg failed with code {$returnCode}. Tail stderr:\n" . $tail . "\n";
            // Persist detailed error to DB immediately
            $this->mapper->updateStatus($job->getId(), 'failed', "FFmpeg failed (code {$returnCode})\n" . $tail);
        }

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
