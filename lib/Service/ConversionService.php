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
