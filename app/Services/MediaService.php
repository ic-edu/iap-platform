<?php

namespace App\Services;

use App\Services\Media\LocalStorageDriver;
use App\Services\Media\StorageDriverInterface;
use Illuminate\Http\UploadedFile;

class MediaService
{
    protected StorageDriverInterface $driver;

    public function __construct(?StorageDriverInterface $driver = null)
    {
        $this->driver = $driver ?? new LocalStorageDriver;
    }

    /**
     * Upload media file (Image, Audio, Video, PDF).
     */
    public function upload(UploadedFile $file, string $directory = 'media'): string
    {
        return $this->driver->store($file, $directory);
    }

    /**
     * Delete media file.
     */
    public function delete(string $path): bool
    {
        return $this->driver->delete($path);
    }

    /**
     * Get public URL for media path.
     */
    public function url(string $path): string
    {
        return $this->driver->url($path);
    }
}
