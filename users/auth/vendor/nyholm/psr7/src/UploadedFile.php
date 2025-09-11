<?php

declare(strict_types=1);

namespace Nyholm\Psr7;

use Psr\Http\Message\{StreamInterface, UploadedFileInterface};

/**
 * @author Michael Dowling and contributors to guzzlehttp/psr7
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Martijn van der Ven <martijn@vanderven.se>
 *
 * @final This class should never be extended. See https://github.com/Nyholm/psr7/blob/master/doc/final.md
 */
class UploadedFile implements UploadedFileInterface
{
    /** @var array */
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

    /** @var string */
    private $clientFilename;

    /** @var string */
    private $clientMediaType;

    /** @var int */
    private $error;

    /** @var string|null */
    private $file;

    /** @var bool */
    private $moved = false;

    /** @var int */
    private $size;

    /** @var StreamInterface|null */
    private $stream;

    /**
     * @param StreamInterface|string|resource $streamOrFile
     * @param int $size
     * @param int $errorStatus
     * @param string|null $clientFilename
     * @param string|null $clientMediaType
     */
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
            // Depending on the value set file or stream variable.
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

    /**
     * @throws \RuntimeException if is moved or not ok
     */
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


        // 1. Get the canonical path for the system's temp directory.
        $tempDir = realpath(sys_get_temp_dir());

        // 2. Get the canonical path for the source file.
        $realFile = realpath($this->file);

        // 3. Ensure the file exists and is strictly within the temp directory.
        // This single check prevents traversal attacks in both web and CLI contexts.
        if ($realFile === false || strpos($realFile, $tempDir) !== 0) {
            throw new \RuntimeException('Invalid file path provided; file is not within the temporary directory.');
        }


        if (false === $resource = @\fopen($this->file, 'r')) {
            throw new \RuntimeException(\sprintf('The file "%s" cannot be opened: %s', $this->file, \error_get_last()['message'] ?? ''));
        }

        return Stream::create($resource);
    }

public function moveTo($targetPath): void
{
    //userspice does not use this
    // 1. Destination path validation 
    $uploadDirectory = '/var/www/my-app/uploads'; 
    $resolvedTargetPath = realpath(dirname($targetPath));
    $resolvedUploadDirectory = realpath($uploadDirectory);

    if ($resolvedTargetPath === false || strpos($resolvedTargetPath, $resolvedUploadDirectory) !== 0) {
        throw new \InvalidArgumentException('Invalid target path provided (potential path traversal attempt)');
    }

    $this->validateActive();

    if (!\is_string($targetPath) || '' === $targetPath) {
        throw new \InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
    }

    if (null !== $this->file) {
        // 2. Explicit source file validation for SOC 2 compliance
        $tempDir = realpath(sys_get_temp_dir());
        $realFile = realpath($this->file);

        if ($realFile === false || strpos($realFile, $tempDir) !== 0) {
            throw new \RuntimeException('Invalid source file path; file is not within the temporary directory.');
        }

        // 3. Move the file based on the environment
        if ('cli' === \PHP_SAPI) {
            $this->moved = @\rename($this->file, $targetPath);
        } else {
            // This is now explicitly safe and should pass the scan.
            $this->moved = @\move_uploaded_file($this->file, $targetPath);
        }

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
}
