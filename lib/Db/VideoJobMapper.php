<?php
namespace OCA\Video_Converter_Fm\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;

class VideoJobMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'video_jobs', VideoJob::class);
    }

    /**
     * Récupère un job par son ID
     */
    public function findById(int $id): VideoJob {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        
        return $this->findEntity($qb);
    }

    /**
     * Récupère les jobs en attente (pending)
     */
    public function findPendingJobs(int $limit = 1): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('status', $qb->createNamedParameter('pending')))
           ->orderBy('created_at', 'ASC')
           ->setMaxResults($limit);
        
        return $this->findEntities($qb);
    }

    /**
     * Récupère tous les jobs d'un utilisateur
     */
    public function findByUserId(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
           ->orderBy('created_at', 'DESC');
        
        return $this->findEntities($qb);
    }

    /**
     * Récupère tous les jobs (sans filtre utilisateur)
     */
    public function findAll(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->orderBy('created_at', 'DESC');
        
        return $this->findEntities($qb);
    }

    /**
     * Récupère les jobs en cours (processing)
     */
    public function findProcessingJobs(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('status', $qb->createNamedParameter('processing')))
           ->orderBy('started_at', 'ASC');
        
        return $this->findEntities($qb);
    }

    /**
     * Met à jour le statut d'un job
     */
    public function updateStatus(int $id, string $status, ?string $errorMessage = null): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
           ->set('status', $qb->createNamedParameter($status))
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

        if ($status === 'processing') {
            $qb->set('started_at', $qb->createNamedParameter(date('Y-m-d H:i:s')));
        } elseif (in_array($status, ['completed', 'failed'])) {
            $qb->set('finished_at', $qb->createNamedParameter(date('Y-m-d H:i:s')));
        }

        if ($errorMessage !== null) {
            $qb->set('error_message', $qb->createNamedParameter($errorMessage));
        }

        $qb->execute();
    }

    /**
     * Met à jour la progression d'un job
     */
    public function updateProgress(int $id, int $progress): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
           ->set('progress', $qb->createNamedParameter($progress))
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
           ->execute();
    }

    /**
     * Nettoie les vieux jobs terminés (> 7 jours)
     */
    public function deleteOldJobs(int $daysOld = 7): int {
        $qb = $this->db->getQueryBuilder();
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));
        
        $qb->delete($this->getTableName())
           ->where($qb->expr()->in('status', $qb->createNamedParameter(['completed', 'failed'])))
           ->andWhere($qb->expr()->lt('finished_at', $qb->createNamedParameter($cutoffDate)));
        
        return $qb->execute();
    }

    /**
     * Supprime un job par son ID
     */
    public function deleteById(int $id): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
           ->execute();
    }
}