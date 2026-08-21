<?php

namespace App\Modules\Finance\Database\Seeders;

use App\Models\User;
use App\Modules\Academic\Models\Course;
use App\Modules\Assessment\Models\Test;
use App\Modules\Finance\Enums\PaymentStatus;
use App\Modules\Finance\Models\Payment;
use Illuminate\Database\Seeder;

class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        $course = Course::first();
        $test = Test::first();

        if (!$user) {
            return;
        }

        Payment::firstOrCreate([
            'reference_number' => 'INV-20260726-0001',
        ], [
            'user_id' => $user->id,
            'course_id' => $course?->id,
            'test_id' => $test?->id,
            'amount' => 750000.00,
            'currency' => 'IDR',
            'payment_method' => 'bank_transfer',
            'status' => PaymentStatus::Success,
            'paid_at' => now(),
        ]);
    }
}
