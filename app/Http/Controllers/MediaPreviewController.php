<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaPreviewController extends Controller
{
    /**
     * Centralized media preview gateway for the entire platform (TASK 1 & TASK 2).
     * Serves all media types inline with authorization and streaming support.
     * Prevents raw storage path exposure and forced file downloads.
     */
    public function preview(Request $request, string $id)
    {
        $media = MediaAsset::findOrFail($id);

        // Authorize access (Authenticated user OR valid signed URL)
        if (!auth()->check() && !$request->hasValidSignature()) {
            abort(403, 'Unauthorized media access.');
        }

        // Passage media (Render text inline)
        if ($media->type === 'passage' || empty($media->path)) {
            $text = $media->content_text ?? $media->description ?? 'Reading passage text content.';
            return response($text, 200, [
                'Content-Type'        => 'text/plain; charset=UTF-8',
                'Content-Disposition' => 'inline',
                'Cache-Control'       => 'no-cache, private',
            ]);
        }

        // Resolve storage file path securely
        $filePath = storage_path('app/public/' . $media->path);
        if (!file_exists($filePath)) {
            $filePath = public_path($media->path);
        }

        if (file_exists($filePath)) {
            $mimeType = $media->mime_type ?? mime_content_type($filePath) ?: 'application/octet-stream';

            return response()->file($filePath, [
                'Content-Type'        => $mimeType,
                'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
                'Cache-Control'       => 'no-cache, private',
                'Accept-Ranges'       => 'bytes',
            ]);
        }

        // Fallback text if physical file missing
        $content = $media->content_text ?? $media->description ?? 'Media asset content.';
        return response($content, 200, [
            'Content-Type'        => $media->mime_type ?? 'text/plain',
            'Content-Disposition' => 'inline',
        ]);
    }
}
