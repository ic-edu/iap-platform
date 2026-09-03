<?php

use App\Models\MediaAsset;
use App\Modules\QuestionBank\Models\Question;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // 1. Deterministic Backfill for TOEIC Part 1 (Protected Assessment Q1-Q3)
            $part1Mapping = [
                '01m11xyt6y9pagrbhc9b36kz6s' => [ // Q1
                    'image_id' => '01m11xt755mxjwqzb6bfncpsrd',
                    'audio_id' => '01m11xv7pyem0s9x1jmr97mg7e',
                ],
                '01m18gh3z4k70aamp42c3ej6bn' => [ // Q2
                    'image_id' => '01m18gg5ehsrsphc2f4vaaq621',
                    'audio_id' => '01m18gd00fnbsegh39jb45qtbb',
                ],
                '01m18ph7rx7rmejegnbnf6z058' => [ // Q3
                    'image_id' => '01m18pffts2d9kny6x30n9hszh',
                    'audio_id' => '01m18pg5drcbbxhv9ryefr84df',
                ],
            ];

            foreach ($part1Mapping as $qId => $map) {
                $q = Question::find($qId);
                if (!$q) continue;

                $imgAsset = MediaAsset::find($map['image_id']);
                $audAsset = MediaAsset::find($map['audio_id']);

                if ($imgAsset && $audAsset) {
                    $q->updateQuietly([
                        'image_media_asset_id' => $imgAsset->id,
                        'audio_media_asset_id' => $audAsset->id,
                    ]);
                }
            }

            // 2. Deterministic Backfill for TOEIC Part 2 (Q4-Q15)
            $part2AudioMap = [
                '01m18qdfx3j3a7bfe4663w28x3' => '01m18qcd1mgvzycd7m2ehyvqt5', // Q4
                '01m18qg7f5511b816j2b992160' => '01m18qfrd2f5wkyz0t9sw7z265', // Q5
                '01m18qkgq775hrcb76y7w59pms' => '01m18qjx2n9se4rs2k0w7tj413', // Q6
                '01m18qmjpk8q19q287q4s8s521' => '01m18qm34yctbskx0bhcbmhkgb', // Q7
                '01m18qpdq668f4h08ebh250t65' => '01m18qp4fqvh496r05qbj0e5a5', // Q8
                '01m18qr1bwsb3k8ffp98q9p57q' => '01m18qqratgda75m27a0nfr74n', // Q9
                '01m18qzfe4r6q0v1gq88yv78s7' => '01m18qyydakhb1abf1q6ynsyse', // Q10
                '01m197xvtr0k0g49f1j78y22q9' => '01m197xk8s53rzhhh30ga4skw8', // Q11
                '01m1980p5gcb4w9188x89g0071' => '01m1980b874w9g50038yg2nbp4', // Q12
                '01m1982s3q0g85888a7g883k3b' => '01m1982ej3vkd4t5yxvm84c3r3', // Q13
                '01m1984ge90v3kgg30y78m00q9' => '01m19845p1h9bqd1vkwcytdfdq', // Q14
                '01m1986h6qf6qffg85b7ffgq7b' => '01m1986acwyc93k0vgjw7af15m', // Q15
            ];

            foreach ($part2AudioMap as $qId => $audioAssetId) {
                $q = Question::find($qId);
                if (!$q) continue;

                $audAsset = MediaAsset::find($audioAssetId);
                if ($audAsset) {
                    $q->updateQuietly([
                        'audio_media_asset_id' => $audAsset->id,
                    ]);
                }
            }

            // 3. Conservative General Backfill for all other Questions
            $questions = Question::whereNull('image_media_asset_id')
                ->whereNull('audio_media_asset_id')
                ->get();

            foreach ($questions as $q) {
                $updates = [];

                // Check image_url
                if (!empty($q->image_url) && preg_match('#/media/([0-9a-z]+)#i', $q->image_url, $m)) {
                    $img = MediaAsset::find($m[1]);
                    if ($img && ($img->type === 'image' || str_starts_with($img->mime_type ?? '', 'image/'))) {
                        $updates['image_media_asset_id'] = $img->id;
                    }
                }

                // Check audio_url
                if (!empty($q->audio_url) && preg_match('#/media/([0-9a-z]+)#i', $q->audio_url, $m)) {
                    $aud = MediaAsset::find($m[1]);
                    if ($aud && ($aud->type === 'audio' || str_starts_with($aud->mime_type ?? '', 'audio/'))) {
                        $updates['audio_media_asset_id'] = $aud->id;
                    }
                }

                // Check legacy media_asset_id
                if (!empty($q->media_asset_id)) {
                    $asset = MediaAsset::find($q->media_asset_id);
                    if ($asset) {
                        if (($asset->type === 'image' || str_starts_with($asset->mime_type ?? '', 'image/')) && empty($updates['image_media_asset_id'])) {
                            $updates['image_media_asset_id'] = $asset->id;
                        } elseif (($asset->type === 'audio' || str_starts_with($asset->mime_type ?? '', 'audio/')) && empty($updates['audio_media_asset_id'])) {
                            $updates['audio_media_asset_id'] = $asset->id;
                        }
                    }
                }

                if (!empty($updates)) {
                    $q->updateQuietly($updates);
                }
            }
        });
    }

    public function down(): void
    {
        // Safe reversible down: clear populated columns
        Question::query()->update([
            'image_media_asset_id' => null,
            'audio_media_asset_id' => null,
        ]);
    }
};
