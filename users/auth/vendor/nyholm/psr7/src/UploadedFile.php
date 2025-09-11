<?php

declare(strict_types=1);

namespace Nyholm\Psr7;

use Psr\Http\Message\{StreamInterface, UploadedFileInterface};

/**
 * @author Michael Dowling and contributors to guzzlehttp/psr7
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Martijn van der Ven <martijn@vanderven.se>
 */
class UploadedFile implements UploadedFileInterface
{
    private const ERRORS = [
        \UPLOAD_ERR_OK => 1,
        \UPLOAD_ERR_INI_SIZE => 1,
        \UPLOAD_ERR_FORM_SIZE => 1,
        \UPLOAD_ERR_PARTIAL => 1,
        \UPLOAD_ERR_NO_FILE => 1,
        \UPLOAD_ERR_NO_TMP_DIR => 1,
        \UPLOAD_ERR_CANT_WRITE => 1,
        \UPLOAD_ERR_EXTENSION => 1,
    ];

    private $clientFilename;
    private $clientMediaType;
    private $error;
    private $file;
    private $moved = false;
    private $size;
    private $stream;

    public function __construct($streamOrFile, $size, $errorStatus, $clientFilename = null, $clientMediaType = null)
    {
        if (false === \is_int($errorStatus) || !isset(self::ERRORS[$errorStatus])) {
            throw new \InvalidArgumentException('Upload file error status must be an integer value and one of the "UPLOAD_ERR_*" constants');
        }

        if (false === \is_int($size)) {
            throw new \InvalidArgumentException('Upload file size must be an integer');
        }

        if (null !== $clientFilename && !\is_string($clientFilename)) {
            throw new \InvalidArgumentException('Upload file client filename must be a string or null');
        }

        if (null !== $clientMediaType && !\is_string($clientMediaType)) {
            throw new \InvalidArgumentException('Upload file client media type must be a string or null');
        }

        $this->error = $errorStatus;
        $this->size = $size;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;

        if (\UPLOAD_ERR_OK === $this->error) {
            if (\is_string($streamOrFile) && '' !== $streamOrFile) {
                $this->file = $streamOrFile;
            } elseif (\is_resource($streamOrFile)) {
                $this->stream = Stream::create($streamOrFile);
            } elseif ($streamOrFile instanceof StreamInterface) {
                $this->stream = $streamOrFile;
            } else {
                throw new \InvalidArgumentException('Invalid stream or file provided for UploadedFile');
            }
        }
    }

    private function validateActive(): void
    {
        if (\UPLOAD_ERR_OK !== $this->error) {
            throw new \RuntimeException('Cannot retrieve stream due to upload error');
        }

        if ($this->moved) {
            throw new \RuntimeException('Cannot retrieve stream after it has already been moved');
        }
    }

    public function getStream(): StreamInterface
    {
        $this->validateActive();

        if ($this->stream instanceof StreamInterface) {
            return $this->stream;
        }

        $safePath = self::sanitizePath(basename($this->file), sys_get_temp_dir());
        if ($safePath === false || $safePath !== realpath($this->file)) {
             throw new \RuntimeException('Invalid file path; file is not within the temporary directory.');
        }

        if (false === $resource = @\fopen($this->file, 'r')) {
            throw new \RuntimeException(\sprintf('The file "%s" cannot be opened: %s', $this->file, \error_get_last()['message'] ?? ''));
        }

        return Stream::create($resource);
    }

    public function moveTo($targetPath): void
    {
        $this->validateActive();

        if (!\is_string($targetPath) || '' === $targetPath) {
            throw new \InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
        }

        $uploadDirectory = '/var/www/my-app/uploads'; // Example path, configure as needed
        $safeTargetPath = self::sanitizePath(basename($targetPath), $uploadDirectory);

        if ($safeTargetPath === false || $safeTargetPath !== $targetPath) {
            throw new \InvalidArgumentException('Invalid target path provided (potential path traversal attempt)');
        }

        if (null !== $this->file) {
            $safeSourcePath = self::sanitizePath(basename($this->file), sys_get_temp_dir());
            if ($safeSourcePath === false || $safeSourcePath !== realpath($this->file)) {
                 throw new \RuntimeException('Invalid source file path; file is not within the temporary directory.');
            }
            
            $this->moved = 'cli' === \PHP_SAPI ? @\rename($this->file, $targetPath) : @\move_uploaded_file($this->file, $targetPath);

            if (false === $this->moved) {
                throw new \RuntimeException(\sprintf('Uploaded file could not be moved to "%s": %s', $targetPath, \error_get_last()['message'] ?? ''));
            }
        } else {
            $stream = $this->getStream();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            if (false === $resource = @\fopen($targetPath, 'w')) {
                throw new \RuntimeException(\sprintf('The file "%s" cannot be opened: %s', $targetPath, \error_get_last()['message'] ?? ''));
            }

            $dest = Stream::create($resource);
            while (!$stream->eof()) {
                if (!$dest->write($stream->read(1048576))) {
                    break;
                }
            }
            $this->moved = true;
        }
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }

    private static function sanitizePath(string $path, string $baseDir): string|false
    {
        if (preg_match('~^(?:[a-z][a-z0-9+-.]*:)?//~i', $path)) {
            return false;
        }
    
        $fullPath = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
        $realFullPath = realpath($fullPath);
        $realBaseDir = realpath($baseDir);
    
        if ($realFullPath === false || $realBaseDir === false || strpos($realFullPath, $realBaseDir) !== 0) {
            return false;
        }
    
        return $realFullPath;
    }
}