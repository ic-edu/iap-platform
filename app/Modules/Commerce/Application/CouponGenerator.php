<?php

namespace App\Modules\Commerce\Application;

use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\CouponCampaign;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class CouponGenerator
{
    /**
     * Unambiguous, human-readable character set excluding 0, O, 1, I, L.
     */
    public const SAFE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /**
     * Get default prefix for an assessment family.
     */
    public static function getDefaultPrefix(string $family): string
    {
        return match (strtolower(trim($family))) {
            'toeic'      => 'TOEIC',
            'toefl'      => 'TOEFL',
            'ielts'      => 'IELTS',
            'general'    => 'GE',
            'vocational' => 'VOC',
            default      => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $family), 0, 5)) ?: 'PROMO',
        };
    }

    /**
     * Generate a single random suffix using safe alphabet.
     */
    public function generateSuffix(int $length = 6): string
    {
        $alphabet = self::SAFE_ALPHABET;
        $alphabetLength = strlen($alphabet);
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $randomIndex = random_int(0, $alphabetLength - 1);
            $result .= $alphabet[$randomIndex];
        }

        return $result;
    }

    /**
     * Generate a single formatted code (e.g. TOEIC-K7M4PX).
     */
    public function generateCodeString(string $prefix, int $suffixLength = 6): string
    {
        $cleanedPrefix = rtrim(strtoupper(trim($prefix)), '-');
        $suffix = $this->generateSuffix($suffixLength);

        return !empty($cleanedPrefix) ? "{$cleanedPrefix}-{$suffix}" : $suffix;
    }

    /**
     * Generate and persist coupon codes for a given campaign.
     *
     * @return Collection<int, Coupon>
     */
    public function generateForCampaign(CouponCampaign $campaign): Collection
    {
        $count = $campaign->generation_mode === 'shared' ? 1 : max(1, (int) $campaign->total_codes);

        if ($count > 500) {
            throw new InvalidArgumentException('Batch code generation limit is 500 codes per batch.');
        }

        return DB::transaction(function () use ($campaign, $count) {
            $createdCoupons = collect();
            $prefix = $campaign->code_prefix ?: self::getDefaultPrefix($campaign->assessment_family);
            $suffixLength = $campaign->code_length ?: 6;
            $maxAttemptsPerCode = 20;

            for ($i = 0; $i < $count; $i++) {
                $code = null;
                $attempts = 0;

                while ($attempts < $maxAttemptsPerCode) {
                    $candidateCode = $this->generateCodeString($prefix, $suffixLength);
                    $exists = Coupon::withTrashed()->where('code', $candidateCode)->exists();

                    if (!$exists) {
                        $code = $candidateCode;
                        break;
                    }

                    $attempts++;
                }

                if (!$code) {
                    throw new RuntimeException("Failed to generate unique coupon code after {$maxAttemptsPerCode} attempts for prefix '{$prefix}'.");
                }

                $usageLimit = $campaign->generation_mode === 'shared'
                    ? max(1, (int) $campaign->uses_per_code)
                    : max(1, (int) $campaign->uses_per_code);

                $coupon = Coupon::create([
                    'campaign_id' => $campaign->id,
                    'code'        => $code,
                    'type'        => $campaign->discount_type,
                    'value'       => $campaign->discount_value,
                    'usage_limit' => $usageLimit,
                    'used_count'  => 0,
                    'valid_from'  => $campaign->valid_from,
                    'valid_until' => $campaign->valid_until,
                    'expires_at'  => $campaign->valid_until,
                    'is_active'   => $campaign->is_active,
                ]);

                $createdCoupons->push($coupon);
            }

            return $createdCoupons;
        });
    }
}
