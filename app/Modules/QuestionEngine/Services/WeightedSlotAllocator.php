<?php

namespace App\Modules\QuestionEngine\Services;

use InvalidArgumentException;

class WeightedSlotAllocator
{
    /**
     * Allocate integer counts across weights deterministically using the Largest Remainder Method (Hare-Niemeyer).
     *
     * @param  int  $totalCount  Total slots to distribute
     * @param  array<string|int, int|float>  $weights  Associative array of [key => percentage]
     * @param  int|null  $seed  Optional deterministic seed for tie-breaking
     * @return array<string|int, int> Associative array of [key => exact integer count]
     *
     * @throws InvalidArgumentException
     */
    public function allocate(int $totalCount, array $weights, ?int $seed = null): array
    {
        if ($totalCount < 0) {
            throw new InvalidArgumentException("Total count must be non-negative, got {$totalCount}.");
        }

        if (empty($weights)) {
            if ($totalCount === 0) {
                return [];
            }
            throw new InvalidArgumentException('Weights array cannot be empty when total count > 0.');
        }

        $sum = array_sum($weights);
        if (abs($sum - 100.0) > 0.0001) {
            throw new InvalidArgumentException("Weight percentages must sum to exactly 100. Sum: {$sum}.");
        }

        if ($totalCount === 0) {
            $result = [];
            foreach ($weights as $key => $weight) {
                $result[$key] = 0;
            }

            return $result;
        }

        $allocations = [];
        $remainders = [];
        $allocatedSum = 0;

        $index = 0;
        foreach ($weights as $key => $weight) {
            if ($weight < 0) {
                throw new InvalidArgumentException("Weight cannot be negative: {$weight} for key [{$key}].");
            }
            $exact = ($totalCount * $weight) / 100.0;
            $base = (int) floor($exact);
            $allocations[$key] = $base;
            $allocatedSum += $base;
            $remainders[] = [
                'key' => $key,
                'remainder' => $exact - $base,
                'weight' => $weight,
                'index' => $index++,
            ];
        }

        $shortage = $totalCount - $allocatedSum;

        if ($shortage > 0) {
            usort($remainders, function ($a, $b) use ($seed) {
                if (abs($b['remainder'] - $a['remainder']) > 0.000001) {
                    return $b['remainder'] <=> $a['remainder'];
                }
                if ($seed !== null) {
                    $hashA = md5("{$seed}:{$a['key']}");
                    $hashB = md5("{$seed}:{$b['key']}");

                    return strcmp($hashA, $hashB);
                }

                return $a['index'] <=> $b['index'];
            });

            for ($i = 0; $i < $shortage; $i++) {
                $k = $remainders[$i]['key'];
                $allocations[$k]++;
            }
        }

        return $allocations;
    }

    /**
     * Distribute elements into a deterministic sequence of keys of length $totalCount.
     *
     * @param  array<string|int, int|float>  $weights
     * @return list<string|int>
     */
    public function allocateSequence(int $totalCount, array $weights, ?int $seed = null): array
    {
        $counts = $this->allocate($totalCount, $weights, $seed);
        $sequence = [];

        foreach ($counts as $key => $count) {
            for ($i = 0; $i < $count; $i++) {
                $sequence[] = $key;
            }
        }

        if ($seed !== null && count($sequence) > 1) {
            $sequence = $this->deterministicShuffle($sequence, $seed);
        }

        return $sequence;
    }

    /**
     * Pure deterministic Fisher-Yates shuffle using an isolated 31-bit LCG without global PRNG side-effects.
     *
     * @param  list<mixed>  $items
     * @return list<mixed>
     */
    private function deterministicShuffle(array $items, int $seed): array
    {
        $count = count($items);
        if ($count <= 1) {
            return $items;
        }

        $state = $seed & 0x7FFFFFFF;
        for ($i = $count - 1; $i > 0; $i--) {
            $state = (1103515245 * $state + 12345) & 0x7FFFFFFF;
            $j = $state % ($i + 1);
            $temp = $items[$i];
            $items[$i] = $items[$j];
            $items[$j] = $temp;
        }

        return $items;
    }
}
