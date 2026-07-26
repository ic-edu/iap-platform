<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;

interface StorageDriverInterface
{
    public function store(UploadedFile $file, string $directory): string;

    public function delete(string $path): bool;

    public function url(string $path): string;
}
