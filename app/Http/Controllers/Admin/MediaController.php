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
     * Display Media Manager library workspace (Admin only).
     */
    public function index(): View
    {
        $mediaItems = $this->getSampleMediaItems();

        return view('admin.media.index', compact('mediaItems'));
    }

    /**
     * Get JSON list of reusable media items for modal selector.
     */
    public function list(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $items = $this->getSampleMediaItems();

        if ($type && $type !== 'all') {
            $items = array_values(array_filter($items, fn ($i) => $i['type'] === $type));
        }

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * Upload media file (Audio, Image, PDF, Passage).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:jpeg,png,webp,jpg,mp3,wav,m4a,pdf|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->store('question-media', 'public');

        return response()->json([
            'success' => true,
            'url' => Storage::url($path),
            'filename' => $file->getClientOriginalName(),
            'type' => $this->determineFileType($file->getClientOriginalExtension()),
            'size' => round($file->getSize() / 1024, 1).' KB',
        ]);
    }

    /**
     * Helper to return rich media dataset for modal selector.
     */
    private function getSampleMediaItems(): array
    {
        return [
            [
                'id' => 'MED-101',
                'name' => 'toeic_listening_part1_photographs.mp3',
                'url' => '/storage/question-media/toeic_listening_part1_photographs.mp3',
                'type' => 'audio',
                'size' => '2.4 MB',
                'uploaded_at' => '2026-07-27 10:15',
            ],
            [
                'id' => 'MED-102',
                'name' => 'toeic_listening_part2_conversation.wav',
                'url' => '/storage/question-media/toeic_listening_part2_conversation.wav',
                'type' => 'audio',
                'size' => '4.1 MB',
                'uploaded_at' => '2026-07-27 11:20',
            ],
            [
                'id' => 'MED-103',
                'name' => 'diagram_business_chart_sales.png',
                'url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=400&q=80',
                'type' => 'image',
                'size' => '850 KB',
                'uploaded_at' => '2026-07-27 11:30',
            ],
            [
                'id' => 'MED-104',
                'name' => 'reading_passage_environmental_sustainability.pdf',
                'url' => '/storage/question-media/reading_passage_environmental_sustainability.pdf',
                'type' => 'pdf',
                'size' => '1.1 MB',
                'uploaded_at' => '2026-07-27 14:20',
            ],
            [
                'id' => 'MED-105',
                'name' => 'Reading Passage 01: Global Economic Outlook 2026',
                'text' => 'Global economic activity is experiencing a subtle rebalancing. Inflationary pressures have abated across major developed markets, allowing central banks to pivot toward monetary easing. However, structural supply chain disruptions and geopolitical fragmentation remain prominent risk factors...',
                'type' => 'passage',
                'size' => '1.2 KB',
                'uploaded_at' => '2026-07-28 09:00',
            ],
        ];
    }

    private function determineFileType(string $ext): string
    {
        $ext = strtolower($ext);
        if (in_array($ext, ['mp3', 'wav', 'm4a'])) {
            return 'audio';
        }
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
            return 'image';
        }
        if ($ext === 'pdf') {
            return 'pdf';
        }

        return 'other';
    }
}
