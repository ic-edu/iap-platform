<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MediaController extends Controller
{
    /**
     * Display Media Manager library workspace.
     */
    public function index(): View
    {
        $mediaItems = [
            ['id' => 'MED-101', 'name' => 'toeic_listening_part1.mp3', 'type' => 'audio', 'size' => '2.4 MB', 'uploaded_at' => '2026-07-27 10:15'],
            ['id' => 'MED-102', 'name' => 'diagram_business_chart.png', 'type' => 'image', 'size' => '850 KB', 'uploaded_at' => '2026-07-27 11:30'],
            ['id' => 'MED-103', 'name' => 'reading_passage_environment.pdf', 'type' => 'pdf', 'size' => '1.1 MB', 'uploaded_at' => '2026-07-27 14:20'],
        ];

        return view('admin.media.index', compact('mediaItems'));
    }

    /**
     * Upload media file (Audio, Image, PDF, Passage).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:jpeg,png,webp,mp3,wav,pdf|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->store('question-media', 'public');

        return response()->json([
            'success' => true,
            'url' => Storage::url($path),
            'filename' => $file->getClientOriginalName(),
        ]);
    }
}
