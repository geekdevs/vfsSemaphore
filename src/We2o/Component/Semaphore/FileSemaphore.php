<?php
namespace We2o\Component\Semaphore;

use Psr\Log\LoggerInterface;

/**
 * This class implements basic file semaphore functionality
 * @see http://en.wikipedia.org/wiki/Semaphore_(programming)
 *
 * @package We2o\Component\Semaphore
 */
class FileSemaphore
{
    /**
     * Root path for the semaphore keys
     * @var string
     */
    protected $path = null;

    /**
     * Timeout (in seconds) after which key is considered expired
     * @var int
     */
    protected $timeout = 3600;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger = null;

    /**
     * Prepares semaphore in the given root path
     *
     * @param $path
     * @param $timeout
     * @param LoggerInterface $logger
     *
     * @throws \RuntimeException
     */
    public function __construct($path, $timeout, LoggerInterface $logger)
    {
        $this->path = (string)$path;
        $this->timeout = (int)$timeout;
        $this->logger = $logger;

        try {
            if (file_exists($path)) {
                //If path exists, make sure it is a directory which is writable
                if (is_dir($path)) {
                    if (!is_writable($path)) {
                        throw new \RuntimeException('Directory is not writable');
                    }
                } else {
                    throw new \RuntimeException('Expected directory, got file on the given path');
                }
            } else {
                //If directory does not exist - try to create it recursively on the given path
                if (!mkdir($path, 0755, true)) {
                    throw new \RuntimeException('Failed to create directory on the given path');
                }
            }
        } catch (\RuntimeException $e) {
            //Failed - log it and throw exception
            $this->logger->critical($e->getMessage());
            throw $e;
        }
    }

    /**
     * Tries to acquire semaphore for the given key
     *
     * @param string $key   Key to be acquired
     * @throws \RuntimeException
     *
     * @return bool True if successful, false otherwise
     */
    public function acquire($key)
    {
        $success = false;
        $canAcquire = true;
        $filePath = $this->path . '/' . $key;

        if (file_exists($filePath)) {
            $expiresAt = (int)file_get_contents($filePath);

            if ($expiresAt > time()) { //if expires in future
                $canAcquire = false;
                $this->logger->info('Key "' . $key . '" exists and expires in future.');
            } else {
                $this->logger->info('Key "' . $key . '" exists, but expired.');
            }
        }

        if ($canAcquire) {
            $expiresAt = time() + $this->timeout;
            $success = file_put_contents($filePath, $expiresAt) !== false;

            if ($success) {
                $this->logger->info('Acquired new key "' . $key . '"');
            } else {
                $msg = 'Failed to acquire key "' . $key . '"';
                $this->logger->critical($msg);

                throw new \RuntimeException($msg);
            }
        }

        return $success;
    }

    /**
     * Releases semaphore for the given key
     *
     * @param $key
     *
     * @return bool True if successful, false otherwise
     */
    public function release($key)
    {
        $filePath = $this->path . '/' . $key;
        $success = unlink($filePath);

        if ($success) {
            $this->logger->info('Released key "' . $key . '"');
        } else {
            $this->logger->info('Failed to release key "' . $key . '"');
        }

        return $success;
    }
}