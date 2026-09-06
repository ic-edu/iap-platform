<?php

namespace App\Modules\Commerce\Domain\Services;

use App\Modules\Commerce\Domain\Models\Coupon;
use Illuminate\Support\Collection;

class VoucherOperationalSummary
{
    protected Collection $allVouchers;
    protected int $activeVouchersCount;
    protected int $scheduledVouchersCount;
    protected int $expiredVouchersCount;
    protected int $inactiveVouchersCount;
    protected int $totalVoucherRedemptions;
    protected Collection $recentVouchers;

    public function __construct()
    {
        $this->allVouchers = Coupon::latest()->get();
        $this->activeVouchersCount = $this->allVouchers->filter(fn($c) => $c->isEffectiveActive())->count();
        $this->scheduledVouchersCount = $this->allVouchers->filter(fn($c) => $c->isScheduled())->count();
        $this->expiredVouchersCount = $this->allVouchers->filter(fn($c) => $c->isExpired())->count();
        $this->inactiveVouchersCount = $this->allVouchers->filter(fn($c) => $c->getEffectiveState() === 'INACTIVE')->count();
        $this->totalVoucherRedemptions = (int) Coupon::withTrashed()->sum('used_count');

        $this->recentVouchers = $this->allVouchers->sortBy([
            fn($a, $b) => match($a->getEffectiveState()) { 'ACTIVE' => 1, 'SCHEDULED' => 2, 'EXPIRED' => 3, default => 4 } <=> match($b->getEffectiveState()) { 'ACTIVE' => 1, 'SCHEDULED' => 2, 'EXPIRED' => 3, default => 4 },
            fn($a, $b) => $b->updated_at <=> $a->updated_at,
        ])->take(5);
    }

    public static function query(): self
    {
        return new self();
    }

    public static function get(): array
    {
        return (new self())->toArray();
    }

    public function activeCount(): int
    {
        return $this->activeVouchersCount;
    }

    public function scheduledCount(): int
    {
        return $this->scheduledVouchersCount;
    }

    public function expiredCount(): int
    {
        return $this->expiredVouchersCount;
    }

    public function inactiveCount(): int
    {
        return $this->inactiveVouchersCount;
    }

    public function totalRedemptions(): int
    {
        return $this->totalVoucherRedemptions;
    }

    public function currentVoucherPreview(int $limit = 5): Collection
    {
        if ($limit !== 5) {
            return $this->allVouchers->sortBy([
                fn($a, $b) => match($a->getEffectiveState()) { 'ACTIVE' => 1, 'SCHEDULED' => 2, 'EXPIRED' => 3, default => 4 } <=> match($b->getEffectiveState()) { 'ACTIVE' => 1, 'SCHEDULED' => 2, 'EXPIRED' => 3, default => 4 },
                fn($a, $b) => $b->updated_at <=> $a->updated_at,
            ])->take($limit);
        }
        return $this->recentVouchers;
    }

    public function toArray(): array
    {
        return [
            'activeVouchersCount'    => $this->activeVouchersCount,
            'scheduledVouchersCount' => $this->scheduledVouchersCount,
            'expiredVouchersCount'   => $this->expiredVouchersCount,
            'inactiveVouchersCount'  => $this->inactiveVouchersCount,
            'totalVoucherRedemptions'=> $this->totalVoucherRedemptions,
            'recentVouchers'         => $this->recentVouchers,
        ];
    }
}
