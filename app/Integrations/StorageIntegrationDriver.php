<?php

namespace App\Integrations;

class StorageIntegrationDriver implements StorageIntegrationInterface
{
    public function storeFile(string $path, string $content): array
    {
        return [
            'storage_path' => $path,
            'url' => 'https://storage.icedu.org/'.$path,
        ];
    }
}
