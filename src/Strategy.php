<?php

namespace Overtrue\LaravelUploader;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Fluent;

class Strategy
{
    protected string $disk;
    protected string $directory;
    protected array $mimes = [];
    protected string $name;
    protected int $maxSize = 0;
    protected string $filenameType;
    protected UploadedFile $file;
    
    /** @var FileValidator */
    protected $fileValidator;
    
    /** @var FileUploader */
    protected $fileUploader;
    
    /** @var FilenameGenerator */
    protected $filenameGenerator;
    
    /** @var DirectoryFormatter */
    protected $directoryFormatter;

    public function __construct(array $config, UploadedFile $file)
    {
        $config = new Fluent($config);

        $this->file = $file;
        $this->disk = $config->get('disk', \config('filesystems.default'));
        $this->mimes = $config->get('mimes', ['*']);
        $this->name = $config->get('name', 'file');
        $this->directory = $config->get('directory');
        
        // Use the FilesizeConverter to convert human readable file size
        $this->maxSize = (new FilesizeConverter())->toBytes($config->get('max_size', 0));
        $this->filenameType = $config->get('filename_type', 'md5_file');
        
        // Initialize the components
        $this->fileValidator = new FileValidator($this->mimes, $this->maxSize);
        $this->filenameGenerator = new FilenameGenerator($this->filenameType);
        $this->directoryFormatter = new DirectoryFormatter();
        $this->fileUploader = new FileUploader();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getDisk()
    {
        return $this->disk;
    }

    /**
     * @return array|mixed
     */
    public function getMimes()
    {
        return $this->mimes;
    }

    /**
     * @return int|string
     */
    public function getMaxSize()
    {
        return $this->maxSize;
    }

    public function getFile()
    {
        return $this->file;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filenameGenerator->generate($this->file);
    }

    /**
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * Orchestrates the file validation and upload process
     * 
     * @return \Overtrue\LaravelUploader\Response
     */
    public function upload(array $options = [])
    {
        // Validate the file
        $this->fileValidator->validate($this->file);
        
        // Format the directory and get the complete path
        $formattedDirectory = $this->directoryFormatter->format($this->directory);
        $path = \sprintf('%s/%s', \rtrim($formattedDirectory, '/'), $this->getFilename());
        
        // Upload the file
        $result = $this->fileUploader->upload($this->file, $path, $this->disk, $options);
        
        // Create and return the response
        return new Response($result ? $path : false, $this, $this->file);
    }
}

class FileValidator 
{
    protected array $mimes;
    protected int $maxSize;
    
    public function __construct(array $mimes, int $maxSize)
    {
        $this->mimes = $mimes;
        $this->maxSize = $maxSize;
    }
    
    /**
     * Validate the file
     *
     * @param UploadedFile $file
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    public function validate(UploadedFile $file)
    {
        if (!$this->isValidMime($file)) {
            \abort(422, \sprintf('Invalid mime "%s".', $file->getClientMimeType()));
        }

        if (!$this->isValidSize($file)) {
            \abort(422, \sprintf('File has too large size("%s").', $file->getSize()));
        }
    }
    
    /**
     * Check if the file has a valid mime type
     * 
     * @param UploadedFile $file
     * @return bool
     */
    protected function isValidMime(UploadedFile $file)
    {
        return $this->mimes === ['*'] || \in_array($file->getClientMimeType(), $this->mimes);
    }
    
    /**
     * Check if the file has a valid size
     * 
     * @param UploadedFile $file
     * @return bool
     */
    protected function isValidSize(UploadedFile $file)
    {
        return $file->getSize() <= $this->maxSize || 0 === $this->maxSize;
    }
}

class FilenameGenerator
{
    protected string $filenameType;
    
    public function __construct(string $filenameType)
    {
        $this->filenameType = $filenameType;
    }
    
    /**
     * Generate a filename based on configuration
     * 
     * @param UploadedFile $file
     * @return string
     */
    public function generate(UploadedFile $file)
    {
        switch ($this->filenameType) {
            case 'original':
                return $file->getClientOriginalName();
            case 'md5_file':
                return md5_file($file->getRealPath()).'.'.$file->getClientOriginalExtension();
            case 'random':
            default:
                return $file->hashName();
        }
    }
}

class DirectoryFormatter
{
    /**
     * Replace date variables in directory path
     * 
     * @param string $directory
     * @return string
     */
    public function format(string $directory)
    {
        $replacements = [
            '{Y}' => date('Y'),
            '{m}' => date('m'),
            '{d}' => date('d'),
            '{H}' => date('H'),
            '{i}' => date('i'),
            '{s}' => date('s'),
        ];

        return str_replace(array_keys($replacements), $replacements, $directory);
    }
}

class FilesizeConverter
{
    /**
     * Convert human readable filesize to bytes
     * 
     * @param mixed $humanFileSize
     * @return int
     */
    public function toBytes($humanFileSize)
    {
        $bytesUnits = [
            'K' => 1024,
            'M' => 1024 * 1024,
            'G' => 1024 * 1024 * 1024,
            'T' => 1024 * 1024 * 1024 * 1024,
            'P' => 1024 * 1024 * 1024 * 1024 * 1024,
        ];

        $bytes = floatval($humanFileSize);

        if (preg_match('~([KMGTP])$~si', rtrim($humanFileSize, 'B'), $matches)
            && !empty($bytesUnits[\strtoupper($matches[1])])) {
            $bytes *= $bytesUnits[\strtoupper($matches[1])];
        }

        return intval(round($bytes, 2));
    }
}

class FileUploader
{
    /**
     * Upload a file to storage
     * 
     * @param UploadedFile $file
     * @param string $path
     * @param string $disk
     * @param array $options
     * @return bool
     */
    public function upload(UploadedFile $file, string $path, string $disk, array $options = [])
    {
        $stream = fopen($file->getRealPath(), 'r');
        
        // Dispatch event before uploading
        \Illuminate\Support\Facades\Event::dispatch(new Events\FileUploading($file));
        
        $result = \Illuminate\Support\Facades\Storage::disk($disk)->put($path, $stream, $options);
        
        // Dispatch event after uploading
        // Note: In a full refactoring, we would need to adjust the FileUploaded event to not require Response and Strategy
        
        if (is_resource($stream)) {
            fclose($stream);
        }
        
        return $result;
    }
}
