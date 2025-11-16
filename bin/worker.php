#!/usr/bin/env php
<?php
/**
 * Worker de traitement des jobs de conversion vidéo
 * 
 * Usage:
 *   php bin/worker.php
 * 
 * En production (daemon):
 *   nohup php bin/worker.php >> /var/log/nextcloud/video-worker.log 2>&1 &
 * 
 * Ou avec systemd (recommandé, voir bin/systemd/video-worker.service)
 */

// IMPORTANT: Define flags to bypass Nextcloud's headers_sent() check in CLI mode
define('OC_CONSOLE', 1);
define('PHPUNIT_RUN', 1);

// Bootstrap Nextcloud
require_once __DIR__ . '/../../../lib/base.php';

use OCA\Video_Converter_Fm\Db\VideoJobMapper;
use OCA\Video_Converter_Fm\Service\ConversionService;
use Psr\Log\LoggerInterface;

// Durée du sleep entre chaque vérification (secondes)
const SLEEP_INTERVAL = 5;
const MAX_RETRY = 3;

// Logger (Nextcloud 32+ uses PSR-3 LoggerInterface)
$logger = \OC::$server->get(LoggerInterface::class);
echo "[video_converter_fm] Worker starting...\n";
$logger->info("Video conversion worker started", ['app' => 'video_converter_fm']);
// Ensure we operate from a stable directory to avoid getcwd() issues if launch path deleted
@chdir('/');

// Récupérer les services directement
$db = \OC::$server->getDatabaseConnection();
$mapper = new VideoJobMapper($db);
$service = new ConversionService($mapper, $logger);

// Boucle principale
while (true) {
    try {
        // Récupérer un job en attente
        $pendingJobs = $mapper->findPendingJobs(1);

        if (count($pendingJobs) === 0) {
            $logger->debug("No pending jobs, sleeping for " . SLEEP_INTERVAL . "s", ['app' => 'video_converter_fm']);
            echo "[video_converter_fm] idle...\n";
            sleep(SLEEP_INTERVAL);
            continue;
        }

        $job = $pendingJobs[0];
    $logger->info("Processing job #{$job->getId()}: {$job->getInputPath()}", ['app' => 'video_converter_fm']);
    echo "[video_converter_fm] Processing job #{$job->getId()} {$job->getInputPath()}\n";

        // Vérifier le nombre de tentatives
        if ($job->getRetryCount() >= MAX_RETRY) {
            $logger->error("Job #{$job->getId()} exceeded max retries, marking as failed", ['app' => 'video_converter_fm']);
            $mapper->updateStatus($job->getId(), 'failed', 'Max retry count exceeded');
            continue;
        }

        // Exécuter le job
        $success = $service->executeJob($job);

        if ($success) {
            $logger->info("Job #{$job->getId()} completed successfully", ['app' => 'video_converter_fm']);
            echo "[video_converter_fm] Job #{$job->getId()} completed\n";
        } else {
            $logger->warning("Job #{$job->getId()} failed, will retry", ['app' => 'video_converter_fm']);
            echo "[video_converter_fm] Job #{$job->getId()} failed (retry=" . $job->getRetryCount() . ")\n";
            // Do not automatically requeue blindly; leave as failed to inspect logs
        }

    } catch (\Exception $e) {
        $logger->error("Worker error: " . $e->getMessage(), [
            'app' => 'video_converter_fm',
            'exception' => $e
        ]);
        echo "[video_converter_fm] Worker exception: " . $e->getMessage() . "\n";
        
        // Attendre un peu avant de continuer en cas d'erreur critique
        sleep(SLEEP_INTERVAL);
    }

    // Petit sleep pour éviter de surcharger le CPU
    usleep(500000); // 0.5 secondes
}
