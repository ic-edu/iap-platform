<?php

namespace App\Integrations;

interface StorageIntegrationInterface
{
    /**
     * Store file artifact in storage provider (S3, GCS, Local).
     *
     * @return array{storage_path: string, url: string}
     */
    public function storeFile(string $path, string $content): array;
}
