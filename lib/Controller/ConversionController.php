<?php

namespace OCA\Video_Converter_Fm\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use \OCP\IConfig;
use OCP\EventDispatcher\IEventDispatcher;
use OC\Files\Filesystem;
use OCA\Video_Converter_Fm\Service\ConversionService;
use OCA\Video_Converter_Fm\Db\VideoJobMapper;
use OCP\IGroupManager;
use OCP\Files\IRootFolder;


/**
 * Contrôleur pour la conversion de vidéos
 */
class ConversionController extends Controller
{

	private $userId;
	private $conversionService;
	private $jobMapper;
	/** @var IRequest */
	protected $request;
    private $logger;
	private $groupManager;
	private $rootFolder;

	/**
	 * @NoAdminRequired
	 */
	public function __construct(
		$AppName,
		IRequest $request,
		$userId,
		ConversionService $conversionService,
		VideoJobMapper $jobMapper,
        \Psr\Log\LoggerInterface $logger,
		IGroupManager $groupManager,
		IRootFolder $rootFolder
	) {
		parent::__construct($AppName, $request);
		$this->request = $request;
		$this->userId = $userId;
		$this->conversionService = $conversionService;
		$this->jobMapper = $jobMapper;
        $this->logger = $logger;
		$this->groupManager = $groupManager;
		$this->rootFolder = $rootFolder;
	}

	public function getFile($directory, $fileName)
	{
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($this->userId);
		return Filesystem::getLocalFile($directory . '/' . $fileName);
	}

	/**
	 * Vérifie si l'utilisateur actuel est admin
	 */
	private function isAdmin(): bool {
		return $this->groupManager->isAdmin($this->userId);
	}

	/**
	 * Vérifie si le dossier de sortie existe dans Nextcloud
	 * @param string $userId L'ID de l'utilisateur propriétaire du dossier
	 * @param string $outputPath Le chemin Nextcloud du dossier de sortie
	 * @return bool True si le dossier existe, false sinon
	 */
	private function outputFolderExists(string $userId, string $outputPath): bool {
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
			return $userFolder->nodeExists($outputPath);
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * @NoAdminRequired
	 */
	/**
	 * Convertit une vidéo en utilisant FFmpeg avec les paramètres spécifiés
	 * 
	 * @param string $nameOfFile Nom du fichier vidéo source
	 * @param string $directory Répertoire du fichier source
	 * @param bool $external Indique si le fichier est sur un stockage externe
	 * @param string $type Format de sortie (mp4/avi/webm/mpd/m3u8)
	 * @param string $preset Preset FFmpeg (fast/medium/slow)
	 * @param string $priority Priorité nice (0/5/10)
	 * @param bool $movflags Activer faststart pour MP4
	 * @param string|null $codec Codec vidéo (x264/x265/copy/null=auto)
	 * @param string|null $vbitrate Bitrate vidéo en kbps
	 * @param string|null $scale Résolution cible
	 * @param string|null $shareOwner Propriétaire du partage (si applicable)
	 * @param int $mtime Timestamp de modification (optionnel)
	 * @return string Réponse JSON avec le code et la description du résultat
	 * 
	 * @note Gère les fichiers sur stockage externe et la réindexation après conversion
	 */
	public function convertHere($nameOfFile, $directory, $external, $type, $preset, $priority, $movflags = false, $codec = null, $vbitrate = null, $scale = null, $shareOwner = null, $mtime = 0)
	{
		try {
			// Mode asynchrone : créer un job au lieu d'exécuter immédiatement
			$inputPath = $directory . '/' . $nameOfFile;
			
			// Vérifier que le fichier existe dans le FS Nextcloud
            // Vérifier que le fichier existe dans le FS Nextcloud
            \OC_Util::tearDownFS();
            \OC_Util::setupFS($this->userId);

            // D'ABORD, OBTENIR LA "VUE" DU SYSTÈME DE FICHIERS POUR L'UTILISATEUR
            $userView = \OC\Files\Filesystem::getView();

            // ENSUITE, VÉRIFIER SI LE FICHIER EXISTE DANS CETTE VUE
            if (!$userView || !$userView->file_exists($inputPath)) {
                return json_encode([
                    "code" => 0,
                    "desc" => "File not found or not readable: " . $inputPath
                ]);
            }

            // ENFIN, OBTENIR LE CHEMIN LOCAL DU FICHIER À PARTIR DE LA VUE
            $localFile = $userView->getLocalFile($inputPath);

            // Vérifier que le fichier physique existe sur le disque
            if (!file_exists($localFile)) {
                return json_encode([
                    "code" => 0,
                    "desc" => "File exists in Nextcloud but not found on local storage: " . $localFile
                ]);
            }

			/** @var IRequest $req */
			$req = $this->request;

			// Créer le job de conversion
			$conversionParams = [
				'type' => $type,
				'preset' => $preset,
				'priority' => (string) $priority,
				'movflags' => filter_var($movflags, FILTER_VALIDATE_BOOLEAN),
				'codec' => $codec,
				'vbitrate' => $vbitrate,
				'scale' => $scale,
				'external' => (int) $external,
			];

			$audioCodec = $req ? $req->getParam('audioCodec') : null;
			if (is_string($audioCodec) && $audioCodec !== '') {
				$conversionParams['audio_codec'] = $audioCodec;
			}

			$selectedFormats = $req ? $req->getParam('selectedFormats') : null;
			if (is_string($selectedFormats)) {
				$decoded = json_decode($selectedFormats, true);
				$selectedFormats = json_last_error() === JSON_ERROR_NONE ? $decoded : $selectedFormats;
			}
			if (!empty($selectedFormats)) {
				$conversionParams['selected_formats'] = $selectedFormats;
			}

			$renditions = $req ? $req->getParam('renditions') : null;
			if (is_string($renditions)) {
				$decoded = json_decode($renditions, true);
				$renditions = json_last_error() === JSON_ERROR_NONE ? $decoded : $renditions;
			}
			if (!empty($renditions)) {
				$conversionParams['renditions'] = $renditions;
			}

			$profile = $req ? $req->getParam('profile') : null;
			if (is_string($profile)) {
				$decodedProfile = json_decode($profile, true);
				$profile = json_last_error() === JSON_ERROR_NONE ? $decodedProfile : $profile;
			}
			if (!empty($profile)) {
				$conversionParams['profile'] = $profile;
				if (is_array($profile)) {
					$conversionParams['segment_duration'] = $profile['segmentDuration'] ?? null;
					$conversionParams['keyframe_interval'] = $profile['keyframeInterval'] ?? null;
				}
			}

			$job = $this->conversionService->createJob(
				$this->userId,
				$nameOfFile, // Utiliser le nom de fichier comme fileId pour le moment
				$inputPath,
				$conversionParams
			);

            $this->logger->info(
                "Conversion job #{$job->getId()} created for {$nameOfFile}",
                ['app' => 'video_converter_fm']
            );

			return json_encode([
				"code" => 1,
				"desc" => "Conversion job created successfully",
				"job_id" => $job->getId(),
				"status" => "pending"
			]);

		} catch (\Throwable $e) {
            $this->logger->error(
                'convertHere failed: ' . $e->getMessage(),
                ['app' => 'video_converter_fm', 'exception' => $e]
            );
			return json_encode([
				"code" => 0,
				"desc" => "Server error: " . $e->getMessage()
			]);
		}
	}

	/**
	 * Récupère le statut d'un job
	 * 
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @param int $jobId
	 * @return DataResponse
	 */
	public function getJobStatus(int $jobId): DataResponse {
		try {
			$job = $this->jobMapper->findById($jobId);
			
			// Vérifier que l'utilisateur a le droit de voir ce job
			if ($job->getUserId() !== $this->userId) {
				return new DataResponse(['error' => 'Unauthorized'], 403);
			}

			return new DataResponse([
				'id' => $job->getId(),
				'status' => $job->getStatus(),
				'progress' => $job->getProgress(),
				'created_at' => $job->getCreatedAt(),
				'started_at' => $job->getStartedAt(),
				'finished_at' => $job->getFinishedAt(),
				'error_message' => $job->getErrorMessage(),
			]);
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], 404);
		}
	}

	/**
	 * Liste tous les jobs de l'utilisateur
	 * 
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return DataResponse
	 */
	public function listJobs(): DataResponse {
		try {
			$jobs = $this->jobMapper->findByUserId($this->userId);
			
			$result = array_map(function($job) {
				// Extraire le chemin de sortie du JSON output_formats
				$outputFormats = $job->getOutputFormats();
				$formats = json_decode($outputFormats, true) ?: [];
				$outputNcPath = $formats['output_nc_path'] ?? null;
				
				// Vérifier si le dossier de sortie existe
				$outputFolderExists = false;
				if ($outputNcPath) {
					$outputFolderExists = $this->outputFolderExists($job->getUserId(), $outputNcPath);
				}
				
				return [
					'id' => $job->getId(),
					'file_id' => $job->getFileId(),
					'user_id' => $job->getUserId(),
					'input_path' => $job->getInputPath(),
					'output_formats' => $outputFormats,
					'output_folder_exists' => $outputFolderExists,
					'status' => $job->getStatus(),
					'progress' => $job->getProgress(),
					'created_at' => $job->getCreatedAt(),
					'started_at' => $job->getStartedAt(),
					'completed_at' => $job->getFinishedAt(), // Alias pour Vue.js
					'finished_at' => $job->getFinishedAt(),
					'error_message' => $job->getErrorMessage(),
				];
			}, $jobs);

			return new DataResponse(['jobs' => $result]);
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], 500);
		}
	}

	/**
	 * Liste TOUS les jobs (tous les utilisateurs)
	 * 
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return DataResponse
	 */
	public function listAllJobs(): DataResponse {
		try {
			$jobs = $this->jobMapper->findAll();
			
			$result = array_map(function($job) {
				// Extraire le chemin de sortie du JSON output_formats
				$outputFormats = $job->getOutputFormats();
				$formats = json_decode($outputFormats, true) ?: [];
				$outputNcPath = $formats['output_nc_path'] ?? null;
				
				// Vérifier si le dossier de sortie existe
				$outputFolderExists = false;
				if ($outputNcPath) {
					$outputFolderExists = $this->outputFolderExists($job->getUserId(), $outputNcPath);
				}
				
				return [
					'id' => $job->getId(),
					'file_id' => $job->getFileId(),
					'user_id' => $job->getUserId(),
					'input_path' => $job->getInputPath(),
					'output_formats' => $outputFormats,
					'output_folder_exists' => $outputFolderExists,
					'status' => $job->getStatus(),
					'progress' => $job->getProgress(),
					'created_at' => $job->getCreatedAt(),
					'started_at' => $job->getStartedAt(),
					'completed_at' => $job->getFinishedAt(),
					'finished_at' => $job->getFinishedAt(),
					'error_message' => $job->getErrorMessage(),
				];
			}, $jobs);

			return new DataResponse(['jobs' => $result]);
		} catch (\Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], 500);
		}
	}
	/**
	 * @NoAdminRequired
	 */

	/**
 	* Crée la commande FFmpeg pour la conversion
 	* 
 	* @param string $file Chemin complet du fichier source
 	* @param string $preset Preset FFmpeg (fast/medium/slow)
 	* @param string $output Format de sortie (mp4/avi/webm/mpd/m3u8)
 	* @param string $priority Nice priority (0/5/10)
 	* @param bool $movflags Activer faststart pour MP4
 	* @param string|null $codec Codec vidéo (x264/x265/copy/null=auto)
 	* @param string|null $vbitrate Bitrate vidéo en kbps
 	* @param string|null $scale Résolution cible
 	* @return string Commande shell complète
 	* 
 	* @note Cette méthode construit une commande shell complexe.
 	*       Pour DASH/HLS, elle génère une structure multi-résolution.
 	*/
    /**
     * Crée la commande FFmpeg pour la conversion
     */
    public function createCmd($file, $preset, $output, $priority, $movflags, $codec, $vbitrate, $scale)
    {
        $middleArgs = "";
        if ($output == "webm") {
            switch ($preset) {
                case 'faster': $middleArgs = "-vcodec libvpx -cpu-used 1 -threads 16"; break;
                case 'veryfast': $middleArgs = "-vcodec libvpx -cpu-used 2 -threads 16"; break;
                case 'superfast': $middleArgs = "-vcodec libvpx -cpu-used 4 -threads 16"; break;
                case 'ultrafast': $middleArgs = "-vcodec libvpx -cpu-used 5 -threads 16 -deadline realtime"; break;
                default: break;
            }
        } else {
            if ($codec != null) {
                switch ($codec) {
                    case 'x264': $middleArgs = "-vcodec libx264 -preset " . escapeshellarg($preset) . " -strict -2"; break;
                    case 'x265': $middleArgs = "-vcodec libx265 -preset " . escapeshellarg($preset) . " -strict -2"; break;
                }
            } else {
                $middleArgs = "-preset " . escapeshellarg($preset) . " -strict -2";
            }

            if ($movflags) { $middleArgs = $middleArgs . " -movflags +faststart "; }

            if ($vbitrate != null) {
                switch ($vbitrate) {
                    case '1': $vbitrate = '1000k'; break;
                    case '2': $vbitrate = '2000k'; break;
                    case '3': $vbitrate = '3000k'; break;
                    case '4': $vbitrate = '4000k'; break;
                    case '5': $vbitrate = '5000k'; break;
                    case '6': $vbitrate = '6000k'; break;
                    case '7': $vbitrate = '7000k'; break;
                    default: $vbitrate = '2000k'; break;
                }
                $middleArgs = $middleArgs . " -b:v " . $vbitrate;
            }

            if ($scale != null) {
                switch ($scale) {
                    case 'vga': $scale = " -vf scale=640:480"; break;
                    case 'wxga': $scale = " -vf scale=1280:720"; break;
                    case 'hd': $scale = " -vf scale=1368:768"; break;
                    case 'fhd': $scale = " -vf scale=1920:1080"; break;
                    case 'uhd': $scale = " -vf scale=3840:2160"; break;
                    case '320': $scale = " -vf scale=-1:320"; break;
                    case '480': $scale = " -vf scale=-1:480"; break;
                    case '600': $scale = " -vf scale=-1:600"; break;
                    case '720': $scale = " -vf scale=-1:720"; break;
                    case '1080': $scale = " -vf scale=-1:1080"; break;
                    default: $scale = ""; break;
                }
                $middleArgs = $middleArgs . $scale;
            }
        }

        if ($codec == "copy") { $middleArgs = "-codec copy"; }

        //$ffmepgPath = " /usr/local/bin/"; //uncomment for dev
        $ffmepgPath = " "; //uncomment for prod

        $subsInput = escapeshellarg(dirname($file) . '/' . pathinfo($file)['filename'] . ".srt");
        $refreshDirCmd = " php /var/www/nextcloud/occ files:scan --all";


        // --- BLOC 2 : DASH (MPD) ---
        if ($output == "mpd") {
            $currentTime = date("Ymdhis");
            $source_dir = dirname($file) . '/source' . $currentTime;
            mkdir($source_dir, 0700);
            $media_dir = "source" . $currentTime . "/chunk-stream\$RepresentationID\$-\$Number%05d\$.m4s";
            $segments_dir = "source" . $currentTime . "/init-stream\$RepresentationID\$.m4s";
            $output_mpd_file = escapeshellarg(dirname($file) . '/' . pathinfo($file)['filename'] . "." . $output);
            $master_pl_name = escapeshellarg(pathinfo($file)['filename'] . ".m3u8");
            $cmd = $ffmepgPath . "ffmpeg -re -y -i " . escapeshellarg($file) . " -preset slow -keyint_min 100 -g 100 -sc_threshold 0 -r 25 -c:v libx264 -pix_fmt yuv420p -c:a aac -c:s copy -map v:0 -s:0 256x144 -b:v:0 160k -maxrate:0 160k -bufsize:0 320k -map v:0 -s:1 426x240 -b:v:1 400k -maxrate:1 400k -bufsize:1 800k -map v:0 -s:2 640x360 -b:v:2 700k -maxrate:2 700k -bufsize:2 1.4M -map v:0 -s:3 854x480 -b:v:3 1.25M -maxrate:3 1.25M -bufsize:3 2.5M -map v:0 -s:4 1280x720 -b:v:4 3.2M -maxrate:4 3.2M -bufsize:4 6.4M -map v:0 -s:5 1920x1080 -b:v:5 5.3M -maxrate:5 5.3M -bufsize:5 10.6M  -map a:0 -b:a:0 128k -ac:a:0 1 -use_template 1 -hls_playlist 1 -use_timeline 1 -seg_duration 4 -media_seg_name '" . $media_dir . "' -init_seg_name '" . $segments_dir . "'  -f dash " . $output_mpd_file;

            $subsOutput = escapeshellarg(dirname($file) . '/' . pathinfo($file)['filename'] . ".vtt");
            $subTitlesConversionCmd = $ffmepgPath. "ffmpeg -i ". $subsInput." -f webvtt ". $subsOutput;

            $cmd .= " && ". $subTitlesConversionCmd;
            $cmd .= " && " . $refreshDirCmd;
        }

        // --- BLOC 3 : HLS (M3U8) ---
        elseif ($output == "m3u8") {

            // 1. Définition du dossier parent pour tout ranger proprement
            // On utilise le même format : NomDuFichier_Timestamp
            $currentTime = date("YmdHis");
            $baseName = pathinfo($file)['filename'];
            $outputDirName = $baseName . "_" . $currentTime;
            $outputDirPath = dirname($file) . '/' . $outputDirName;

            // 2. Création du dossier parent
            if (!file_exists($outputDirPath)) {
                mkdir($outputDirPath, 0755, true);
            }

            // 3. Création des sous-dossiers (stream_0 à stream_5) pour éviter le crash
            for ($i = 0; $i <= 5; $i++) {
                $streamDir = $outputDirPath . "/stream_" . $i;
                if (!file_exists($streamDir)) {
                    mkdir($streamDir, 0755);
                }
            }

            // 4. On adapte les chemins pour écrire DANS ce nouveau dossier
            $master_pl_name = escapeshellarg($outputDirPath . "/" . $baseName . ".m3u8");
            // Le chemin des chunks utilise le dossier parent
            $hls_segment_filename = $outputDirPath . "/stream_%v/data%02d.ts";
            // Le chemin des playlists variantes
            $var_stream_map_pattern = $outputDirPath . "/stream_%v.m3u8";

            // Gestion des sous-titres (on les met aussi dans le dossier)
            $subsOutput = escapeshellarg($outputDirPath . '/' . $baseName . ".vtt");
            $subTitlesConversionCmd = $ffmepgPath. "ffmpeg -i ". $subsInput." -f webvtt ". $subsOutput;

            // La commande HLS
            $changeDirCmd = "cd ".escapeshellarg(dirname($file))." && ";
            $cmd = $changeDirCmd . $ffmepgPath . "ffmpeg -re -y -err_detect ignore_err -i " . escapeshellarg($file) . " -preset slow -keyint_min 100 -sc_threshold 0 -c:v libx264 -map v:0 -s:0 256x144 -b:v:0 160k -maxrate:0 160k -bufsize:0 320k -map v:0 -s:1 426x240 -b:v:1 400k -maxrate:1 400k -bufsize:1 800k -map v:0 -s:2 640x360 -b:v:2 700k -maxrate:2 700k -bufsize:2 1.4M -map v:0 -s:3 854x480 -b:v:3 1.25M -maxrate:3 1.25M -bufsize:3 2.5M -map v:0 -s:4 1280x720 -b:v:4 3.2M -maxrate:4 3.2M -bufsize:4 6.4M -map v:0 -s:5 1920x1080 -b:v:5 5.3M -maxrate:5 5.3M -bufsize:5 10.6M -map a:0 -c:a:0 aac -b:a:0 64k -ac 2 -map a:0 -c:a:1 aac -b:a:1 64k -ac 2 -map a:0 -c:a:2 aac -b:a:2 64k -ac 2 -map a:0 -c:a:3 aac -b:a:3 128K -ac 2 -map a:0 -c:a:4 aac -b:a:4 128K -ac 2 -map a:0 -c:a:5 aac -b:a:5 128K -ac 2 -f hls -strftime_mkdir 1 -hls_flags independent_segments+delete_segments -hls_segment_type mpegts -hls_segment_filename '" . $hls_segment_filename . "' -master_pl_name " . $master_pl_name . " -var_stream_map 'v:0,a:0 v:1,a:1 v:2,a:2 v:3,a:3 v:4,a:4 v:5,a:5' '" . $var_stream_map_pattern . "'";

            $cmd .= " && " . $subTitlesConversionCmd;
            $cmd .= " && " . $refreshDirCmd;

        } else {
            // --- BLOC 4 : STANDARD (MP4, etc.) ---
            $subsOutput = escapeshellarg(dirname($file) . '/' . pathinfo($file)['filename'] . ".vtt");
            $subTitlesConversionCmd = $ffmepgPath. "ffmpeg -i ". $subsInput." -f webvtt ". $subsOutput;

            $cmd = $ffmepgPath . "ffmpeg -y -i " . escapeshellarg($file) . " " . $middleArgs . " " . escapeshellarg(dirname($file) . '/' . pathinfo($file)['filename'] . "." . $output);

            // J'ajoute juste les sous-titres pour les formats standards si ce n'était pas déjà fait
            $cmd .= " && " . $subTitlesConversionCmd;
        }

        if ($priority != "0") {
            $cmd = "nice -n " . escapeshellarg($priority) . $cmd;
        }
        return $cmd;
    }
	/**
	 * Supprime un job par son ID
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function deleteJob(int $jobId): DataResponse {
		try {
			$job = $this->jobMapper->findById($jobId);

			// Vérifier que l'utilisateur a le droit de supprimer ce job
			if ($job->getUserId() !== $this->userId) {
				return new DataResponse(['error' => 'Unauthorized'], 403);
			}

			// L'utilisateur peut supprimer son job même en cours de traitement
			$this->jobMapper->deleteById($jobId);
			$this->logger->info("Job #{$jobId} deleted by user {$this->userId}", ['app' => 'video_converter_fm']);
			return new DataResponse(['success' => true]);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], 404);
		}
	}

	/**
	 * Probe video file to extract metadata using ffprobe
	 * 
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function probeVideo(): DataResponse {
		try {
			$path = $this->request->getParam('path');
			if (!$path) {
				return new DataResponse(['error' => 'Missing path parameter'], 400);
			}

			// Setup filesystem for current user
			\OC_Util::tearDownFS();
			\OC_Util::setupFS($this->userId);

			// Get local file path
			$localPath = Filesystem::getLocalFile($path);
			if (!$localPath || !file_exists($localPath)) {
				return new DataResponse(['error' => 'File not found'], 404);
			}

			// Execute ffprobe to extract metadata
			$command = sprintf(
				'ffprobe -v quiet -print_format json -show_format -show_streams %s 2>&1',
				escapeshellarg($localPath)
			);

			exec($command, $output, $returnCode);

			if ($returnCode !== 0) {
				$this->logger->warning("ffprobe failed for file: {$path}", ['app' => 'video_converter_fm']);
				return new DataResponse(['error' => 'Failed to probe video file'], 500);
			}

			$metadata = json_decode(implode('', $output), true);
			if (!$metadata) {
				return new DataResponse(['error' => 'Invalid ffprobe output'], 500);
			}

			// Extract important information
			$duration = (float)($metadata['format']['duration'] ?? 0);
			$size = (int)($metadata['format']['size'] ?? 0);
			$bitrate = (int)($metadata['format']['bit_rate'] ?? 0);

			// Find video stream
			$videoStream = null;
			foreach ($metadata['streams'] ?? [] as $stream) {
				if ($stream['codec_type'] === 'video') {
					$videoStream = $stream;
					break;
				}
			}

			$result = [
				'duration' => round($duration, 2), // seconds
				'durationFormatted' => gmdate('H:i:s', (int)$duration),
				'size' => $size,
				'bitrate' => round($bitrate / 1000, 0), // Kbps
			];

			if ($videoStream) {
				$result['width'] = $videoStream['width'] ?? null;
				$result['height'] = $videoStream['height'] ?? null;
				$result['codec'] = $videoStream['codec_name'] ?? null;
				$result['fps'] = null;
				
				// Calculate FPS if available
				if (isset($videoStream['r_frame_rate'])) {
					$parts = explode('/', $videoStream['r_frame_rate']);
					if (count($parts) === 2 && $parts[1] > 0) {
						$result['fps'] = round($parts[0] / $parts[1], 2);
					}
				}
			}

			$this->logger->debug("Probed video: {$path}, duration: {$duration}s", ['app' => 'video_converter_fm']);
			return new DataResponse($result);

		} catch (\Exception $e) {
			$this->logger->error('Error probing video: ' . $e->getMessage(), ['app' => 'video_converter_fm']);
			return new DataResponse(['error' => $e->getMessage()], 500);
		}
	}

	/**
	 * Vérifie le statut du dossier output avant suppression/cancellation
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function checkDeleteOrCancel($id, $action) {
		$job = $this->jobMapper->findById($id);
		$formats = json_decode($job->getOutputFormats() ?? '', true);
		$outputDir = $formats['output_directory'] ?? null;
		if (!$outputDir) {
			return new DataResponse([
				'case' => 'no_output_path',
				'message' => 'Chemin de sortie introuvable dans la base. Voulez-vous quand même supprimer la ligne du job ?'
			]);
		}
		if (!is_dir($outputDir)) {
			return new DataResponse([
				'case' => 'output_missing',
				'message' => 'Le dossier de sortie n’existe plus à l’emplacement attendu. Voulez-vous quand même supprimer la ligne du job ?'
			]);
		}
		return new DataResponse([
			'case' => 'output_exists',
			'message' => 'Voulez-vous supprimer les fichiers de sortie en plus de la ligne du job ?'
		]);
	}
	
	/**
	 * Supprime ou annule un job, et ses fichiers output si demandé
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function deleteOrCancel($id, $action) {
		$job = $this->jobMapper->findById($id);
		$formats = json_decode($job->getOutputFormats() ?? '', true);
		$outputDir = $formats['output_directory'] ?? null;
		$outputNcPath = $formats['output_nc_path'] ?? null;
		
		// Lire le body JSON de la requête (Axios envoie du JSON, pas form-urlencoded)
		$input = file_get_contents('php://input');
		$data = json_decode($input, true) ?? [];
		$deleteFiles = isset($data['deleteFiles']) ? (bool)$data['deleteFiles'] : false;
		$errors = [];
		
		$this->logger->info("deleteOrCancel called: jobId={$id}, action={$action}, deleteFiles=" . ($deleteFiles ? 'true' : 'false') . ", outputNcPath={$outputNcPath}", ['app' => 'video_converter_fm']);
		
		if ($deleteFiles && $outputNcPath) {
			// Utiliser l'API Nextcloud pour supprimer le dossier (met à jour le cache)
			try {
				$userFolder = $this->rootFolder->getUserFolder($job->getUserId());
				if ($userFolder->nodeExists($outputNcPath)) {
					$node = $userFolder->get($outputNcPath);
					$node->delete();
					$this->logger->info("Deleted output folder via Nextcloud API: {$outputNcPath}", ['app' => 'video_converter_fm']);
				} else {
					$this->logger->warning("Output folder not found in Nextcloud: {$outputNcPath}", ['app' => 'video_converter_fm']);
					// Essayer avec le chemin filesystem direct en fallback
					if ($outputDir && is_dir($outputDir)) {
						$this->deleteDirectoryRecursive($outputDir);
						$this->logger->info("Deleted output folder via filesystem: {$outputDir}", ['app' => 'video_converter_fm']);
					} else {
						$errors[] = 'Le dossier output n\'existe plus.';
					}
				}
			} catch (\Exception $e) {
				$this->logger->error("Failed to delete output folder: " . $e->getMessage(), ['app' => 'video_converter_fm']);
				$errors[] = 'Erreur lors de la suppression du dossier: ' . $e->getMessage();
			}
		} elseif ($deleteFiles && !$outputNcPath && $outputDir) {
			// Fallback: supprimer via filesystem si pas de chemin NC
			if (is_dir($outputDir)) {
				$this->deleteDirectoryRecursive($outputDir);
				$this->logger->info("Deleted output folder via filesystem fallback: {$outputDir}", ['app' => 'video_converter_fm']);
			} else {
				$errors[] = 'Le dossier output n\'existe plus.';
			}
		}
		
		// Supprimer le job de la base de données
		$this->jobMapper->delete($job);
		$this->logger->info("Deleted job #{$id} from database", ['app' => 'video_converter_fm']);
		
		return new DataResponse([
			'success' => true,
			'errors' => $errors
		]);
	}
	
	/**
	 * Supprime un dossier de manière récursive
	 */
	private function deleteDirectoryRecursive($dir) {
		if (!is_dir($dir)) {
			return;
		}
		$it = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
		$files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($files as $file) {
			if ($file->isDir()) {
				@rmdir($file->getRealPath());
			} else {
				@unlink($file->getRealPath());
			}
		}
		@rmdir($dir);
	}
}