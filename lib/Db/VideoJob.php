<?php
namespace OCA\Video_Converter_Fm\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getFileId()
 * @method void setFileId(string $fileId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string getInputPath()
 * @method void setInputPath(string $path)
 * @method string getOutputFormats()
 * @method void setOutputFormats(string $formats)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $timestamp)
 * @method string|null getStartedAt()
 * @method void setStartedAt(?string $timestamp)
 * @method string|null getFinishedAt()
 * @method void setFinishedAt(?string $timestamp)
 * @method int getProgress()
 * @method void setProgress(int $progress)
 * @method int getRetryCount()
 * @method void setRetryCount(int $count)
 * @method string|null getErrorMessage()
 * @method void setErrorMessage(?string $message)
 * @method string|null getWorkerHost()
 * @method void setWorkerHost(?string $host)
 */
class VideoJob extends Entity {
    protected $fileId;
    protected $userId;
    protected $status = 'pending';
    protected $inputPath;
    protected $outputFormats;
    protected $createdAt;
    protected $startedAt;
    protected $finishedAt;
    protected $progress = 0;
    protected $retryCount = 0;
    protected $errorMessage;
    protected $workerHost;

    public function __construct() {
        $this->addType('fileId', 'string');
        $this->addType('userId', 'string');
        $this->addType('status', 'string');
        $this->addType('inputPath', 'string');
        $this->addType('outputFormats', 'string');
        $this->addType('createdAt', 'string');
        $this->addType('startedAt', 'string');
        $this->addType('finishedAt', 'string');
        $this->addType('progress', 'integer');
        $this->addType('retryCount', 'integer');
        $this->addType('errorMessage', 'string');
        $this->addType('workerHost', 'string');
    }
}