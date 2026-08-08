<?php

namespace Database\Seeders;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class MediaInstitutionalRepositorySeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::where('email', 'teacher@icedu.org')->first()
            ?? User::role('teacher')->first();

        if (!$teacher) {
            $teacher = User::create([
                'name'     => 'Teacher Instructor',
                'email'    => 'teacher@icedu.org',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'status'   => 'active',
            ]);
            $teacher->assignRole('teacher');
        }

        // Ensure physical storage directories exist
        $mediaDir = storage_path('app/public/media');
        $imageDir = storage_path('app/public/images');
        File::makeDirectory($mediaDir, 0755, true, true);
        File::makeDirectory($imageDir, 0755, true, true);

        // Define helper file generators
        $this->ensureSampleFilesExist($mediaDir, $imageDir);

        $samples = [];

        // 1. IMAGES (31 items >= 25 requirement)
        $imageSpecs = [
            // TOEFL (6)
            ['title' => 'TOEFL Diagram: Hydrothermal Vent Ecosystem Cross-Section', 'exam' => 'toefl', 'cat' => 'Reading Images', 'file' => 'images/toefl_diagram_vent.jpg', 'color' => [15, 23, 42]],
            ['title' => 'TOEFL Map: Ancient Mesopotamian Irrigation Canal Network', 'exam' => 'toefl', 'cat' => 'Reading Images', 'file' => 'images/toefl_map_mesopotamia.jpg', 'color' => [30, 27, 75]],
            ['title' => 'TOEFL Chart: Earth Orbital Eccentricity Cycle Diagram', 'exam' => 'toefl', 'cat' => 'Reading Images', 'file' => 'images/toefl_chart_orbit.jpg', 'color' => [6, 78, 59]],
            ['title' => 'TOEFL Diagram: Solar Radiation Photosynthesis Mechanism', 'exam' => 'toefl', 'cat' => 'Reading Images', 'file' => 'images/toefl_diagram_photo.jpg', 'color' => [120, 53, 15]],
            ['title' => 'TOEFL Chart: Industrial Automation Employment Trend Chart', 'exam' => 'toefl', 'cat' => 'Reading Images', 'file' => 'images/toefl_chart_labor.jpg', 'color' => [88, 28, 135]],
            ['title' => 'TOEFL Diagram: Atmospheric Pressure & Wind Current Vectors', 'exam' => 'toefl', 'cat' => 'Structure Images', 'file' => 'images/toefl_diagram_vectors.jpg', 'color' => [12, 74, 110]],

            // TOEIC (12)
            ['title' => 'TOEIC Part 1 Photo: Executive Office Desk Document Review', 'exam' => 'toeic', 'cat' => 'Part 1 Images', 'file' => 'images/toeic_part1_office.jpg', 'color' => [15, 23, 42]],
            ['title' => 'TOEIC Part 1 Photo: Corporate Conference Room Discussion', 'exam' => 'toeic', 'cat' => 'Part 1 Images', 'file' => 'images/toeic_part1_meeting.jpg', 'color' => [30, 27, 75]],
            ['title' => 'TOEIC Part 1 Photo: Commercial Shipping Warehouse Logistics', 'exam' => 'toeic', 'cat' => 'Part 1 Images', 'file' => 'images/toeic_part1_warehouse.jpg', 'color' => [6, 78, 59]],
            ['title' => 'TOEIC Part 1 Photo: Urban Pedestrian Sidewalk Street Scene', 'exam' => 'toeic', 'cat' => 'Part 1 Images', 'file' => 'images/toeic_part1_street.jpg', 'color' => [120, 53, 15]],
            ['title' => 'TOEIC Part 1 Photo: Chemical Laboratory Equipment Bench', 'exam' => 'toeic', 'cat' => 'Part 1 Images', 'file' => 'images/toeic_part1_lab.jpg', 'color' => [88, 28, 135]],
            ['title' => 'TOEIC Part 1 Photo: Airport Terminal Gate Seating Area', 'exam' => 'toeic', 'cat' => 'Part 1 Images', 'file' => 'images/toeic_part1_airport.jpg', 'color' => [12, 74, 110]],
            ['title' => 'TOEIC Part 1 Photo: Retail Department Store Display Shelf', 'exam' => 'toeic', 'cat' => 'Part 1 Images', 'file' => 'images/toeic_part1_retail.jpg', 'color' => [15, 23, 42]],
            ['title' => 'TOEIC Part 1 Photo: Industrial Construction Site Crane', 'exam' => 'toeic', 'cat' => 'Part 1 Images', 'file' => 'images/toeic_part1_construction.jpg', 'color' => [30, 27, 75]],
            ['title' => 'TOEIC Part 1 Photo: Restaurant Dining Room Table Setting', 'exam' => 'toeic', 'cat' => 'Part 1 Images', 'file' => 'images/toeic_part1_restaurant.jpg', 'color' => [6, 78, 59]],
            ['title' => 'TOEIC Part 1 Photo: IT Server Room Network Cable Cabinet', 'exam' => 'toeic', 'cat' => 'Part 1 Images', 'file' => 'images/toeic_part1_server.jpg', 'color' => [120, 53, 15]],
            ['title' => 'TOEIC Part 1 Photo: Cargo Port Container Terminal Crane', 'exam' => 'toeic', 'cat' => 'Part 1 Images', 'file' => 'images/toeic_part1_cargoport.jpg', 'color' => [88, 28, 135]],
            ['title' => 'TOEIC Part 1 Photo: Hotel Reception Desk Check-in Counter', 'exam' => 'toeic', 'cat' => 'Part 1 Images', 'file' => 'images/toeic_part1_hotelreception.jpg', 'color' => [12, 74, 110]],

            // IELTS (6)
            ['title' => 'IELTS Task 1 Line Graph: Renewable Energy Share (2010–2020)', 'exam' => 'ielts', 'cat' => 'Writing Images', 'file' => 'images/ielts_task1_linegraph.jpg', 'color' => [15, 23, 42]],
            ['title' => 'IELTS Task 1 Diagram: Seawater Desalination Industrial Process', 'exam' => 'ielts', 'cat' => 'Writing Images', 'file' => 'images/ielts_task1_desalination.jpg', 'color' => [30, 27, 75]],
            ['title' => 'IELTS Task 1 Bar Chart: University Graduate Employment Rates', 'exam' => 'ielts', 'cat' => 'Writing Images', 'file' => 'images/ielts_task1_barchart.jpg', 'color' => [6, 78, 59]],
            ['title' => 'IELTS Task 1 Pie Chart: Household Energy Usage Breakdown', 'exam' => 'ielts', 'cat' => 'Writing Images', 'file' => 'images/ielts_task1_piechart.jpg', 'color' => [120, 53, 15]],
            ['title' => 'IELTS Task 1 Flowchart: Paper Recycling & De-inking Stages', 'exam' => 'ielts', 'cat' => 'Writing Images', 'file' => 'images/ielts_task1_recycling.jpg', 'color' => [88, 28, 135]],
            ['title' => 'IELTS Task 1 Map: City Infrastructure Development Comparison', 'exam' => 'ielts', 'cat' => 'Writing Images', 'file' => 'images/ielts_task1_citymap.jpg', 'color' => [12, 74, 110]],

            // Placement (1), Grammar (3), Vocabulary (3)
            ['title' => 'Placement Diagnostic Diagram: Level Streaming CEFR Chart', 'exam' => 'placement', 'cat' => 'Images', 'file' => 'images/placement_cefr_chart.jpg', 'color' => [15, 23, 42]],
            ['title' => 'Grammar Infographic: Passive Voice Sentence Construction Chart', 'exam' => 'grammar', 'cat' => 'Images', 'file' => 'images/grammar_passive_chart.jpg', 'color' => [30, 27, 75]],
            ['title' => 'Grammar Infographic: Third Conditional If-Clause Formula', 'exam' => 'grammar', 'cat' => 'Images', 'file' => 'images/grammar_conditional_chart.jpg', 'color' => [6, 78, 59]],
            ['title' => 'Grammar Infographic: Inversion Rules in Formal Sentences', 'exam' => 'grammar', 'cat' => 'Images', 'file' => 'images/grammar_inversion_chart.jpg', 'color' => [120, 53, 15]],
            ['title' => 'Vocabulary Infographic: Academic Word List (AWL) Collocations', 'exam' => 'vocabulary', 'cat' => 'Images', 'file' => 'images/vocab_awl_collocations.jpg', 'color' => [88, 28, 135]],
            ['title' => 'Vocabulary Infographic: Formal Academic Synonyms & Roots', 'exam' => 'vocabulary', 'cat' => 'Images', 'file' => 'images/vocab_synonyms_chart.jpg', 'color' => [12, 74, 110]],
            ['title' => 'Vocabulary Infographic: Business English Idiomatic Expressions', 'exam' => 'vocabulary', 'cat' => 'Images', 'file' => 'images/vocab_idioms_chart.jpg', 'color' => [15, 23, 42]],
        ];

        foreach ($imageSpecs as $spec) {
            $samples[] = [
                'title'        => $spec['title'],
                'type'         => 'image',
                'exam_type'    => $spec['exam'],
                'category'     => $spec['cat'],
                'sub_category' => 'Visual Diagram',
                'path'         => $spec['file'],
                'mime'         => 'image/jpeg',
                'size'         => 350000,
                'desc'         => 'High-resolution educational visual asset for student assessment.',
            ];
        }

        // 2. AUDIO (30 items >= 25 requirement)
        for ($i = 1; $i <= 6; $i++) {
            $samples[] = [
                'title'        => "TOEFL Listening Audio Track {$i}: Academic Dialogue & Lecture",
                'type'         => 'audio',
                'exam_type'    => 'toefl',
                'category'     => 'Listening Audio',
                'sub_category' => 'Academic Talk',
                'path'         => "media/toefl_audio_{$i}.wav",
                'mime'         => 'audio/wav',
                'size'         => 180000,
                'content_text' => "Speaker A: Welcome to the oceanography laboratory. Today we explore chemosynthetic organisms.\nSpeaker B: Are hydrothermal vent nutrients reliant on solar radiation?",
                'desc'         => 'Official TOEFL iBT listening dialogue audio transcript.',
            ];
        }

        for ($i = 1; $i <= 18; $i++) {
            $catName = ($i <= 6) ? 'Part 2 Audio' : (($i <= 12) ? 'Part 3 Conversations' : 'Part 4 Talks');
            $samples[] = [
                'title'        => "TOEIC Listening Audio Track {$i}: Business Conversation",
                'type'         => 'audio',
                'exam_type'    => 'toeic',
                'category'     => $catName,
                'sub_category' => 'Business Audio',
                'path'         => "media/toeic_audio_{$i}.wav",
                'mime'         => 'audio/wav',
                'size'         => 180000,
                'content_text' => "Question {$i}: When will the revised logistics schedule be submitted to department leads? Answer A: By Friday afternoon.",
                'desc'         => 'Official TOEIC listening question audio audio clip.',
            ];
        }

        for ($i = 1; $i <= 6; $i++) {
            $samples[] = [
                'title'        => "IELTS Listening Audio Track {$i}: Social & Academic Dialogue",
                'type'         => 'audio',
                'exam_type'    => 'ielts',
                'category'     => 'Listening Audio',
                'sub_category' => 'Academic Dialogue',
                'path'         => "media/ielts_audio_{$i}.wav",
                'mime'         => 'audio/wav',
                'size'         => 180000,
                'content_text' => "Student: Good morning. I am inquiring about student housing reservations.\nOfficer: Please complete Form 4B by Thursday.",
                'desc'         => 'IELTS listening examination audio transcript clip.',
            ];
        }

        // 3. VIDEO (11 items >= 10 requirement)
        for ($i = 1; $i <= 11; $i++) {
            $ex = ($i <= 3) ? 'toefl' : (($i <= 6) ? 'toeic' : (($i <= 8) ? 'ielts' : 'general'));
            $samples[] = [
                'title'        => "Institutional Instructional Video {$i}: Masterclass Strategy",
                'type'         => 'video',
                'exam_type'    => $ex,
                'category'     => 'Video Masterclass',
                'sub_category' => 'Video Lesson',
                'path'         => "media/sample_video_{$i}.mp4",
                'mime'         => 'video/mp4',
                'size'         => 195,
                'content_text' => "Instructor: In this video tutorial, we review high-scoring response structures and academic vocabulary strategies.",
                'desc'         => 'Interactive video masterclass tutorial for institutional student preparation.',
            ];
        }

        // 4. PDF (14 items >= 13 requirement)
        for ($i = 1; $i <= 14; $i++) {
            $ex = ($i <= 5) ? 'toefl' : (($i <= 9) ? 'toeic' : (($i <= 11) ? 'grammar' : 'vocabulary'));
            $samples[] = [
                'title'        => "Institutional Reference PDF Document {$i}: Academic Guide",
                'type'         => 'pdf',
                'exam_type'    => $ex,
                'category'     => 'Reference PDF',
                'sub_category' => 'PDF Document',
                'path'         => "media/sample_doc_{$i}.pdf",
                'mime'         => 'application/pdf',
                'size'         => 733,
                'desc'         => 'Comprehensive PDF reference booklet for teachers and assessment authoring.',
            ];
        }

        // 5. PASSAGES (31 items >= 21 requirement)
        for ($i = 1; $i <= 31; $i++) {
            $ex = ($i <= 8) ? 'toefl' : (($i <= 18) ? 'toeic' : (($i <= 26) ? 'ielts' : 'placement'));
            $samples[] = [
                'title'        => "Academic Reading Passage {$i}: Educational Research Text",
                'type'         => 'passage',
                'exam_type'    => $ex,
                'category'     => 'Reading Passage',
                'sub_category' => 'Text Passage',
                'path'         => "media/sample_passage_{$i}.txt",
                'mime'         => 'text/plain',
                'size'         => 1400,
                'content_text' => "Paragraph A: Geothermal energy systems extract steam from sub-surface volcanic reservoirs. While carbon emissions remain significantly lower than coal-fired facilities, environmental impact evaluations must account for atmospheric trace gas venting.",
                'desc'         => 'Rich text academic reading passage content.',
            ];
        }

        // Save records to database
        foreach ($samples as $s) {
            MediaAsset::updateOrCreate(
                ['title' => $s['title']],
                [
                    'filename'        => basename($s['path']),
                    'original_name'   => $s['title'] . '.' . pathinfo($s['path'], PATHINFO_EXTENSION),
                    'mime_type'       => $s['mime'],
                    'type'            => $s['type'],
                    'category'        => $s['category'],
                    'sub_category'    => $s['sub_category'],
                    'exam_type'       => $s['exam_type'],
                    'difficulty'      => 'medium',
                    'tags'            => ['institutional', 'starter', $s['exam_type'], strtolower(str_replace(' ', '_', $s['category']))],
                    'approval_status' => 'approved',
                    'version'         => '1.0',
                    'path'            => $s['path'],
                    'size'            => $s['size'],
                    'status'          => 'active',
                    'description'     => $s['desc'],
                    'content_text'    => $s['content_text'] ?? null,
                    'uploaded_by'     => $teacher->id,
                ]
            );
        }
    }

    private function ensureSampleFilesExist(string $mediaDir, string $imageDir): void
    {
        // 1. Generate sample JPEGs
        for ($i = 1; $i <= 35; $i++) {
            $file = "{$imageDir}/sample_img_{$i}.jpg";
            if (!File::exists($file)) {
                $im = imagecreatetruecolor(800, 600);
                $bg = imagecolorallocate($im, rand(15, 40), rand(20, 60), rand(50, 100));
                $fg = imagecolorallocate($im, 240, 245, 255);
                imagefill($im, 0, 0, $bg);
                imagestring($im, 5, 250, 280, "iC.edu Media Asset #{$i}", $fg);
                imagejpeg($im, $file);
                imagedestroy($im);
            }
        }

        // Also ensure specific named images exist
        $specificImages = [
            'toefl_diagram_vent.jpg', 'toefl_map_mesopotamia.jpg', 'toefl_chart_orbit.jpg',
            'toefl_diagram_photo.jpg', 'toefl_chart_labor.jpg', 'toefl_diagram_vectors.jpg',
            'toeic_part1_office.jpg', 'toeic_part1_meeting.jpg', 'toeic_part1_warehouse.jpg',
            'toeic_part1_street.jpg', 'toeic_part1_lab.jpg', 'toeic_part1_airport.jpg',
            'toeic_part1_retail.jpg', 'toeic_part1_construction.jpg', 'toeic_part1_restaurant.jpg',
            'toeic_part1_server.jpg', 'toeic_part1_cargoport.jpg', 'toeic_part1_hotelreception.jpg',
            'ielts_task1_linegraph.jpg', 'ielts_task1_desalination.jpg', 'ielts_task1_barchart.jpg',
            'ielts_task1_piechart.jpg', 'ielts_task1_recycling.jpg', 'ielts_task1_citymap.jpg',
            'placement_cefr_chart.jpg', 'grammar_passive_chart.jpg', 'grammar_conditional_chart.jpg',
            'grammar_inversion_chart.jpg', 'vocab_awl_collocations.jpg', 'vocab_synonyms_chart.jpg',
            'vocab_idioms_chart.jpg',
        ];
        foreach ($specificImages as $img) {
            $filePath = "{$imageDir}/{$img}";
            if (!File::exists($filePath)) {
                $im = imagecreatetruecolor(800, 600);
                $bg = imagecolorallocate($im, 15, 23, 42);
                $fg = imagecolorallocate($im, 52, 211, 153);
                imagefill($im, 0, 0, $bg);
                imagestring($im, 5, 200, 280, "iC.edu: {$img}", $fg);
                imagejpeg($im, $filePath);
                imagedestroy($im);
            }
        }

        // 2. Generate sample WAV Audio files
        $sampleRate = 44100;
        $duration = 2.0;
        $numSamples = (int)($sampleRate * $duration);
        $audioData = '';
        for ($k = 0; $k < $numSamples; $k++) {
            $val = (int)(sin(2 * M_PI * 440 * ($k / $sampleRate)) * 16000);
            $audioData .= pack('v', $val);
        }
        $audioSize = strlen($audioData);
        $wavHeader = 'RIFF' . pack('V', 36 + $audioSize) . 'WAVEfmt ' . pack('V', 16) . pack('v', 1) . pack('v', 1) . pack('V', $sampleRate) . pack('V', $sampleRate * 2) . pack('v', 2) . pack('v', 16) . 'data' . pack('V', $audioSize);
        $wavContent = $wavHeader . $audioData;

        for ($i = 1; $i <= 30; $i++) {
            File::put("{$mediaDir}/toefl_audio_{$i}.wav", $wavContent);
            File::put("{$mediaDir}/toeic_audio_{$i}.wav", $wavContent);
            File::put("{$mediaDir}/ielts_audio_{$i}.wav", $wavContent);
        }

        // 3. Generate sample MP4 Video files
        $mp4B64 = 'AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAAAABtZGF0AAAAGE1ldGEAAAAAAAAAAAAAAAAAAAAAAG1vb3YAAABsbXZoZAAAAAAAAAAAAAAAAAAAAAEAAAEAAAAAAAB0cmFjawAAAFR0a2hkAAAAAQAAAAAAAAAAAAAAAAEAAAAAAAEAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAAAEAAAAAAAAAAAAAAAAAAE1EAE1E';
        $mp4Data = base64_decode($mp4B64);
        for ($i = 1; $i <= 15; $i++) {
            File::put("{$mediaDir}/sample_video_{$i}.mp4", $mp4Data);
        }

        // 4. Generate sample PDF files
        $pdfTitle = 'iC.edu Institutional Media PDF Document';
        $pdfBody = 'This is a valid institutional academic reference document for student assessment.';
        $pdfContent = "%PDF-1.4\n1 0 obj <</Type /Catalog /Pages 2 0 R>> endobj\n2 0 obj <</Type /Pages /Kids [3 0 R] /Count 1>> endobj\n3 0 obj <</Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources <</Font <</F1 5 0 R>>>> >> endobj\n4 0 obj <</Length " . strlen("BT /F1 18 Tf 50 720 Td ({$pdfTitle}) Tj ET\nBT /F1 12 Tf 50 680 Td ({$pdfBody}) Tj ET\n") . ">> stream\nBT /F1 18 Tf 50 720 Td ({$pdfTitle}) Tj ET\nBT /F1 12 Tf 50 680 Td ({$pdfBody}) Tj ET\nendstream\nendobj\n5 0 obj <</Type /Font /Subtype /Type1 /BaseFont /Helvetica>> endobj\nxref\n0 6\n0000000000 65535 f \n0000000009 00000 n \n0000000056 00000 n \n0000000111 00000 n \n0000000244 00000 n \n0000000340 00000 n \ntrailer <</Size 6 /Root 1 0 R>>\nstartxref\n425\n%%EOF";
        for ($i = 1; $i <= 20; $i++) {
            File::put("{$mediaDir}/sample_doc_{$i}.pdf", $pdfContent);
        }
    }
}
