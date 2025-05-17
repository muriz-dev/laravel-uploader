<?php

namespace Overtrue\LaravelUploader;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Http\UploadedFile;

class Response implements Jsonable, Arrayable
{
    /**
     * @var FileInfo File information container
     */
    protected FileInfo $fileInfo;

    /**
     * @var UrlGenerator URL generator for the file
     */
    protected UrlGenerator $urlGenerator;

    /**
     * Constructor
     *
     * @param string $path
     * @param Strategy $strategy
     * @param UploadedFile $file
     */
    public function __construct(string $path, Strategy $strategy, UploadedFile $file)
    {
        // Create file info container
        $this->fileInfo = new FileInfo($path, $strategy, $file);

        // Create URL generator
        $this->urlGenerator = new UrlGenerator(
            $path,
            $strategy->getDisk(),
            rtrim(config('uploader.base_uri'), '/')
        );
    }

    /**
     * Get file path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->fileInfo->getPath();
    }

    /**
     * Get file URL
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->urlGenerator->getUrl();
    }

    /**
     * Get relative URL
     *
     * @return string
     */
    public function getRelativeUrl(): string
    {
        return $this->urlGenerator->getRelativeUrl();
    }

    /**
     * Get disk name
     *
     * @return string
     */
    public function getDisk(): string
    {
        return $this->fileInfo->getDisk();
    }

    /**
     * Get file mime type
     *
     * @return string|null
     */
    public function getMime(): ?string
    {
        return $this->fileInfo->getMime();
    }

    /**
     * Get file size
     *
     * @return int
     */
    public function getSize(): int
    {
        return $this->fileInfo->getSize();
    }

    /**
     * Get file name
     *
     * @return string
     */
    public function getFilename(): string
    {
        return $this->fileInfo->getFilename();
    }

    /**
     * Get file extension
     *
     * @return string
     */
    public function getExtension(): string
    {
        return $this->fileInfo->getExtension();
    }

    /**
     * Get original file name
     *
     * @return string
     */
    public function getOriginalName(): string
    {
        return $this->fileInfo->getOriginalName();
    }

    /**
     * Get strategy name
     *
     * @return string
     */
    public function getStrategyName(): string
    {
        return $this->fileInfo->getStrategyName();
    }

    /**
     * Convert to JSON
     *
     * @param int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return \json_encode($this->toArray(), $options);
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'mime' => $this->getMime(),
            'size' => $this->getSize(),
            'path' => $this->getPath(),
            'url' => $this->getUrl(),
            'disk' => $this->getDisk(),
            'filename' => $this->getFilename(),
            'extension' => $this->getExtension(),
            'relative_url' => $this->getRelativeUrl(),
            'location' => $this->getUrl(), // Legacy support
            'original_name' => $this->getOriginalName(),
            'strategy' => $this->getStrategyName(),
        ];
    }
}

class FileInfo
{
    /**
     * @var string File path
     */
    protected string $path;

    /**
     * @var string Disk name
     */
    protected string $disk;

    /**
     * @var string|null File mime type
     */
    protected ?string $mime;

    /**
     * @var int File size
     */
    protected int $size;

    /**
     * @var string File name
     */
    protected string $filename;

    /**
     * @var string File extension
     */
    protected string $extension;

    /**
     * @var string Original file name
     */
    protected string $originalName;

    /**
     * @var string Strategy name
     */
    protected string $strategyName;

    /**
     * Constructor
     *
     * @param string $path
     * @param Strategy $strategy
     * @param UploadedFile $file
     */
    public function __construct(string $path, Strategy $strategy, UploadedFile $file)
    {
        $this->path = $path;
        $this->disk = $strategy->getDisk();
        $this->filename = \basename($path);
        $this->extension = $file->getClientOriginalExtension();
        $this->originalName = $file->getClientOriginalName();
        $this->mime = $file->getClientMimeType();
        $this->size = $file->getSize();
        $this->strategyName = $strategy->getName();
    }

    /**
     * Get file path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get disk name
     *
     * @return string
     */
    public function getDisk(): string
    {
        return $this->disk;
    }

    /**
     * Get file mime type
     *
     * @return string|null
     */
    public function getMime(): ?string
    {
        return $this->mime;
    }

    /**
     * Get file size
     *
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get file name
     *
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Get file extension
     *
     * @return string
     */
    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * Get original file name
     *
     * @return string
     */
    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    /**
     * Get strategy name
     *
     * @return string
     */
    public function getStrategyName(): string
    {
        return $this->strategyName;
    }
}

class UrlGenerator
{
    /**
     * @var string File path
     */
    protected string $path;

    /**
     * @var string Disk name
     */
    protected string $disk;

    /**
     * @var string|null Base URI
     */
    protected ?string $baseUri;

    /**
     * @var string URL
     */
    protected string $url;

    /**
     * @var string Relative URL
     */
    protected string $relativeUrl;

    /**
     * Constructor
     *
     * @param string $path
     * @param string $disk
     * @param string|null $baseUri
     */
    public function __construct(string $path, string $disk, ?string $baseUri = null)
    {
        $this->path = $path;
        $this->disk = $disk;
        $this->baseUri = $baseUri;

        $this->generateUrls();
    }

    /**
     * Generate URLs
     */
    protected function generateUrls(): void
    {
        $driver = config('filesystems.disks.' . $this->disk . '.driver');
        $this->relativeUrl = \sprintf('/%s', \ltrim($this->path, '/'));

        // Generate URL based on driver and configuration
        if ($this->baseUri && 'local' !== $driver) {
            $this->url = \sprintf('%s/%s', $this->baseUri, $this->path);
        } else {
            $disk = \Illuminate\Support\Facades\Storage::disk($this->disk);

            if (method_exists($disk, 'url')) {
                $this->url = $disk->url($this->path);
            } else {
                $this->url = url($this->path);
            }
        }
    }

    /**
     * Get URL
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Get relative URL
     *
     * @return string
     */
    public function getRelativeUrl(): string
    {
        return $this->relativeUrl;
    }
}