<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LocalStorageDriver implements StorageDriverInterface
{
    public function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, 'public');

        return $path ? $path : '';
    }

    public function delete(string $path): bool
    {
        return Storage::disk('public')->delete($path);
    }

    public function url(string $path): string
    {
        return Storage::disk('public')->url($path);
    }
}
