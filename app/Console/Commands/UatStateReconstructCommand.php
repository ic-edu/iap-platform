<?php

namespace App\Console\Commands;

use App\Models\AssessmentRequest;
use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\ScoringMethod;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Commerce\Domain\Enums\InvoiceStatus;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\CouponCampaign;
use App\Modules\Commerce\Domain\Models\CouponRedemption;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Organization\Enums\EntitlementStatus;
use App\Modules\Organization\Enums\GroupType;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Enums\SeatAllocationStatus;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationEntitlement;
use App\Modules\Organization\Models\OrganizationGroup;
use App\Modules\Organization\Models\OrganizationGroupMember;
use App\Modules\Organization\Models\OrganizationMembership;
use App\Modules\Organization\Models\OrganizationSeatAllocation;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UatStateReconstructCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iap:uat-reconstruct
                            {--database= : Absolute or relative path to the isolated target SQLite recovery database}
                            {--dry-run : Simulate the reconstruction without writing changes}
                            {--verify : Run complete structural and data verification on the target database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely and idempotently reconstruct structured UAT state into a dedicated recovery SQLite database.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('================================================================');
        $this->info('  iC.edu Assessment Platform (IAP) — Controlled UAT Reconstruction');
        $this->info('================================================================');

        $dbOption = $this->option('database');
        if (empty($dbOption)) {
            $this->error('Error: The --database option is required.');
            $this->line('Usage: php artisan iap:uat-reconstruct --database=database/recovery/uat-reconstructed.sqlite');
            return self::FAILURE;
        }

        $targetPath = $this->resolveDatabasePath($dbOption);
        $activeDbPath = realpath(database_path('database.sqlite')) ?: database_path('database.sqlite');

        // CRITICAL SAFETY CHECK: Never allow targeting the active live database
        if (realpath($targetPath) === $activeDbPath || $targetPath === $activeDbPath) {
            $this->error('CRITICAL SAFETY VIOLATION: Target recovery database cannot be the active application database (database/database.sqlite).');
            $this->error('Reconstruction MUST execute into an isolated staging database (e.g. database/recovery/uat-reconstructed.sqlite).');
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            return $this->handleDryRun($targetPath);
        }

        if ($this->option('verify')) {
            return $this->handleVerification($targetPath);
        }

        return $this->handleReconstruction($targetPath);
    }

    /**
     * Resolve absolute path for database option.
     */
    protected function resolveDatabasePath(string $path): string
    {
        if (Str::startsWith($path, ['/', '\\']) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * Handle dry-run simulation.
     */
    protected function handleDryRun(string $targetPath): int
    {
        $this->warn('--- DRY RUN MODE: Simulating UAT Reconstruction Plan ---');
        $this->line("Target Database Path : {$targetPath}");
        $this->line("Target Exists        : " . (File::exists($targetPath) ? 'YES' : 'NO (will be created)'));

        $this->info("\n[Plan Summary]:");
        $this->line("1. Schema & Base Data:");
        $this->line("   - Execute database migrations against target SQLite.");
        $this->line("   - Seed baseline roles, permissions, and demo users (admin, opadmin, repomanager, teacher, student, finance).");
        $this->line("2. Identity & O1 Organization (Institutional Hierarchy):");
        $this->line("   - Reconstruct Indra Wahyudi (ic.edu.bdg@gmail.com) -> Role: [organization-coordinator] ONLY (No student role).");
        $this->line("   - Reconstruct CA01 (ca01@uat.org) -> Role: [student].");
        $this->line("   - Reconstruct Organization: 'iC.edu UAT University' (slug: icedu-uat-university, status: active, created_by: Indra).");
        $this->line("   - Reconstruct Membership: Indra (Coordinator), CA01 (Member, NIM01).");
        $this->line("   - Reconstruct Group: 'Class 9A' with CA01 enrolled.");
        $this->line("3. Commerce & O2 Voucher Engine:");
        $this->line("   - Reconstruct Product: 'TOEIC Mock Test Package' (slug: toeic-mock-test-package, price: 85,000 IDR).");
        $this->line("   - Reconstruct Voucher Campaign: 'TOEIC O2 UAT Promo' (10% discount, assessment_family: toeic).");
        $this->line("   - Reconstruct Voucher: 'TOEIC-JX3JFS' (10% discount, usage_limit: 10, used_count: 1).");
        $this->line("   - Reconstruct Order: 'ORD-20260907-3DWQ' (Subtotal: 255,000, Discount: 25,500, Tax: 25,245, Grand Total: 254,745 IDR).");
        $this->line("   - Reconstruct Order Item: 3 seats @ 85,000 IDR.");
        $this->line("   - Reconstruct Invoice: 'INV-20260907-DEZK' (Amount: 254,745 IDR, status: paid).");
        $this->line("   - Reconstruct Payment: 'PAY-20260907-0NGF' (Gateway: manual_transfer, Txn: BCA-UAT-CONFIRM-01, status: success).");
        $this->line("   - Reconstruct Payment Proof: storage/app/private/payment_proofs/QJTxKAs6IJ4jVZmGKtjp3j3qOfHIk7yxy5ZrvTSd.png.");
        $this->line("   - Reconstruct Coupon Redemption: consumed (Hold/Usage correctly tracked).");
        $this->line("4. O3 Institutional Entitlements & Allocation:");
        $this->line("   - Reconstruct Entitlement: 1 pool (3 total seats, 1 allocated, 2 available, status: active).");
        $this->line("   - Reconstruct Seat Allocation: CA01 allocated by Indra (status: active).");
        $this->line("   - CBT Assignments: 0 (No automated test assignments created).");
        $this->line("5. Governed Assessment Request & Test Draft:");
        $this->line("   - Reconstruct Assessment Request: 'TOEIC Mock Test' (Candidate: CA01, Program: 'iC.edu UAT University — Class 9A', status: draft_created).");
        $this->line("   - Reconstruct Governed Draft: 'TOEIC Mock Test Group UAT' (status: draft, is_published: false, mode: real_test, Pass Score: 700, Duration: 120, Assigned Teacher: teacher@icedu.org).");
        $this->line("   - Reconstruct Test Section: 'Section 1: General Core'.");
        $this->line("   - Question Content: 0 questions (Zero invented/fabricated questions; authentic human draft placeholder).");

        $this->info("\n[DRY RUN COMPLETE] No modifications were written.");
        return self::SUCCESS;
    }

    /**
     * Handle actual reconstruction execution.
     */
    protected function handleReconstruction(string $targetPath): int
    {
        $this->info("Initializing target recovery SQLite file at: {$targetPath}");

        $dir = dirname($targetPath);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        if (!File::exists($targetPath)) {
            File::put($targetPath, '');
        }

        $this->configureRecoveryConnection($targetPath);

        $this->info("Step 1/5: Running database migrations on recovery target...");
        $this->call('migrate', [
            '--database' => 'recovery',
            '--force'    => true,
        ]);

        $this->info("Step 2/5: Running base seeders (Roles, Permissions, Demo Accounts)...");
        $this->call('db:seed', [
            '--database' => 'recovery',
            '--force'    => true,
        ]);

        $this->info("Step 3/5: Replaying canonical UAT datasets in isolated transaction...");
        
        DB::connection('recovery')->transaction(function () {
            $this->replayUatEntities();
        });

        $this->info("Step 4/5: Verification check on reconstructed database...");
        $verifyStatus = $this->handleVerification($targetPath);

        if ($verifyStatus !== self::SUCCESS) {
            $this->error('Post-reconstruction verification encountered discrepancies.');
            return self::FAILURE;
        }

        $this->info("================================================================");
        $this->info("  UAT RECONSTRUCTION COMPLETED SUCCESSFULLY IN STAGING DATABASE!");
        $this->info("  Target: {$targetPath}");
        $this->info("  NOTE: Active database/database.sqlite has NOT been modified.");
        $this->info("================================================================");

        return self::SUCCESS;
    }

    /**
     * Configure dynamic 'recovery' database connection.
     */
    protected function configureRecoveryConnection(string $targetPath): void
    {
        Config::set('database.connections.recovery', [
            'driver'                  => 'sqlite',
            'database'                => $targetPath,
            'prefix'                  => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('recovery');
        Config::set('database.default', 'recovery');
        DB::setDefaultConnection('recovery');
    }

    /**
     * Replay exact UAT entities into the target connection.
     */
    protected function replayUatEntities(): void
    {
        // 1. Core Demo User Assertions/Normalization
        $admin = User::where('email', 'admin@icedu.org')->firstOrFail();
        $opadmin = User::where('email', 'opadmin@icedu.org')->firstOrFail();
        $repomanager = User::where('email', 'repomanager@icedu.org')->firstOrFail();
        $teacher = User::where('email', 'teacher@icedu.org')->firstOrFail();
        $student = User::where('email', 'student@icedu.org')->firstOrFail();
        $finance = User::where('email', 'finance@icedu.org')->firstOrFail();

        // 2. Identity Reconstruction: Indra Wahyudi & CA01
        $indra = User::firstOrCreate(
            ['email' => 'ic.edu.bdg@gmail.com'],
            [
                'name'     => 'Indra Wahyudi',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        $indra->syncRoles(['organization-coordinator']); // Strictly NO student role

        $ca01 = User::firstOrCreate(
            ['email' => 'ca01@uat.org'],
            [
                'name'     => 'CA01',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        $ca01->syncRoles(['student']);

        // 3. Organization O1 Hierarchy
        $org = Organization::firstOrCreate(
            ['slug' => 'icedu-uat-university'],
            [
                'name'              => 'iC.edu UAT University',
                'organization_type' => OrganizationType::University,
                'status'            => OrganizationStatus::Active,
                'email'             => 'contact@icedu-uat.org',
                'country'           => 'ID',
                'created_by'        => $indra->id,
            ]
        );

        $indraMembership = OrganizationMembership::firstOrCreate(
            [
                'organization_id' => $org->id,
                'user_id'         => $indra->id,
            ],
            [
                'role'      => MembershipRole::Coordinator,
                'status'    => MembershipStatus::Active,
                'joined_at' => now()->subDays(7),
            ]
        );

        $ca01Membership = OrganizationMembership::firstOrCreate(
            [
                'organization_id' => $org->id,
                'user_id'         => $ca01->id,
            ],
            [
                'role'              => MembershipRole::Member,
                'member_identifier' => 'NIM01',
                'department'        => 'Hospitality',
                'status'            => MembershipStatus::Active,
                'joined_at'         => now()->subDays(7),
            ]
        );

        $group = OrganizationGroup::firstOrCreate(
            [
                'organization_id' => $org->id,
                'name'            => 'Class 9A',
            ],
            [
                'group_type' => GroupType::ClassGroup,
                'is_active'  => true,
                'created_by' => $indra->id,
            ]
        );

        OrganizationGroupMember::firstOrCreate([
            'group_id'      => $group->id,
            'membership_id' => $ca01Membership->id,
        ]);

        // 4. Product & Voucher Campaign (O2)
        $product = Product::firstOrCreate(
            ['slug' => 'toeic-mock-test-package'],
            [
                'title'        => 'TOEIC Mock Test Package',
                'product_type' => 'assessment',
                'price'        => 85000.0,
                'is_active'    => true,
                'is_featured'  => false,
            ]
        );

        $campaign = CouponCampaign::firstOrCreate(
            ['name' => 'TOEIC O2 UAT Promo'],
            [
                'assessment_family' => 'toeic',
                'scope_mode'        => 'all_products_in_family',
                'discount_type'     => 'percentage',
                'discount_value'    => 10.0,
                'generation_mode'   => 'manual',
                'code_prefix'       => 'TOEIC',
                'code_length'       => 12,
                'uses_per_code'     => 10,
                'total_codes'       => 1,
                'valid_from'        => now()->subDays(7),
                'valid_until'       => now()->addMonths(6),
                'is_active'         => true,
                'created_by'        => $indra->id,
            ]
        );

        $coupon = Coupon::firstOrCreate(
            ['code' => 'TOEIC-JX3JFS'],
            [
                'campaign_id' => $campaign->id,
                'type'        => 'percentage',
                'value'       => 10.0,
                'usage_limit' => 10,
                'used_count'  => 1,
                'valid_from'  => now()->subDays(7),
                'valid_until' => now()->addMonths(6),
                'expires_at'  => now()->addMonths(6),
                'is_active'   => true,
            ]
        );

        // 5. Commerce Flow (Order, Invoice, Payment, Redemption)
        $order = Order::firstOrCreate(
            ['order_number' => 'ORD-20260907-3DWQ'],
            [
                'user_id'         => $indra->id,
                'organization_id' => $org->id,
                'coupon_id'       => $coupon->id,
                'status'          => OrderStatus::Completed,
                'subtotal'        => 255000.0,
                'discount'        => 25500.0,
                'tax'             => 25245.0,
                'grand_total'     => 254745.0,
            ]
        );

        $orderItem = OrderItem::firstOrCreate(
            [
                'order_id'   => $order->id,
                'product_id' => $product->id,
            ],
            [
                'quantity' => 3,
                'price'    => 85000.0,
                'total'    => 255000.0,
            ]
        );

        $invoice = Invoice::firstOrCreate(
            ['invoice_number' => 'INV-20260907-DEZK'],
            [
                'order_id' => $order->id,
                'user_id'  => $indra->id,
                'status'   => InvoiceStatus::Paid,
                'amount'   => 254745.0,
                'due_date' => now()->addDays(7),
                'paid_at'  => now()->subDays(1),
            ]
        );

        $payment = Payment::firstOrCreate(
            ['reference_number' => 'PAY-20260907-0NGF'],
            [
                'invoice_id'          => $invoice->id,
                'user_id'             => $indra->id,
                'payment_gateway'     => 'manual_transfer',
                'transaction_id'      => 'BCA-UAT-CONFIRM-01',
                'status'              => PaymentStatus::Success,
                'amount'              => 254745.0,
                'proof_path'          => 'payment_proofs/QJTxKAs6IJ4jVZmGKtjp3j3qOfHIk7yxy5ZrvTSd.png',
                'proof_original_name' => 'transfer_receipt_bca_254745.png',
                'proof_uploaded_at'   => now()->subDays(1),
                'confirmed_at'        => now()->subDays(1),
            ]
        );

        $redemption = CouponRedemption::firstOrCreate(
            [
                'coupon_id' => $coupon->id,
                'order_id'  => $order->id,
            ],
            [
                'user_id'         => $indra->id,
                'organization_id' => $org->id,
                'status'          => 'consumed',
                'discount_amount' => 25500.0,
                'reserved_at'     => now()->subDays(1),
                'consumed_at'     => now()->subDays(1),
            ]
        );

        // 6. Entitlements & Seat Allocation (O3)
        $entitlement = OrganizationEntitlement::firstOrCreate(
            [
                'organization_id' => $org->id,
                'order_item_id'   => $orderItem->id,
            ],
            [
                'product_id'   => $product->id,
                'total_seats'  => 3,
                'status'       => EntitlementStatus::Active,
                'activated_at' => now()->subDays(1),
                'valid_from'   => now()->subDays(1),
                'expires_at'   => now()->addYear(),
            ]
        );

        $seatAllocation = OrganizationSeatAllocation::firstOrCreate(
            [
                'organization_entitlement_id' => $entitlement->id,
                'organization_membership_id'  => $ca01Membership->id,
            ],
            [
                'allocated_by' => $indra->id,
                'status'       => SeatAllocationStatus::Active,
                'allocated_at' => now()->subDays(1),
            ]
        );

        // 7. Governed Assessment Request & Test Draft
        $governedDraft = Test::firstOrCreate(
            ['title' => 'TOEIC Mock Test Group UAT'],
            [
                'slug'             => 'toeic-mock-test-group-uat',
                'test_type'        => TestType::Toeic,
                'assessment_mode'  => AssessmentMode::RealTest,
                'scoring_method'   => ScoringMethod::Automatic,
                'duration_minutes' => 120,
                'pass_score'       => 700,
                'shuffle_questions'=> false,
                'shuffle_choices'  => false,
                'is_published'     => false,
                'status'           => 'draft',
                'created_by'       => $repomanager->id,
                'assigned_to'      => $teacher->id,
                'instructions'     => 'Governed UAT Test Draft for Institutional Group Evaluation.',
            ]
        );

        $section = TestSection::firstOrCreate(
            [
                'test_id' => $governedDraft->id,
                'title'   => 'Section 1: General Core',
            ],
            [
                'section_type'     => SectionType::Listening,
                'duration_minutes' => 120,
                'order'            => 1,
            ]
        );

        $assessmentRequest = AssessmentRequest::firstOrCreate(
            [
                'title'        => 'TOEIC Mock Test',
                'candidate_id' => $ca01->id,
            ],
            [
                'test_type'          => 'toeic',
                'program_context'    => 'iC.edu UAT University — Class 9A',
                'required_sections'  => json_encode(['listening', 'reading']),
                'notes'              => 'Skill UAT',
                'requested_by'       => $opadmin->id,
                'status'             => 'draft_created',
                'test_id'            => $governedDraft->id,
                'requested_deadline' => now()->addDays(14),
            ]
        );

        if ($governedDraft->assessment_request_id !== $assessmentRequest->id) {
            $governedDraft->update(['assessment_request_id' => $assessmentRequest->id]);
        }
    }

    /**
     * Handle comprehensive verification against target database.
     */
    protected function handleVerification(string $targetPath): int
    {
        $this->info("\n================================================================");
        $this->info("  COMPREHENSIVE UAT RECOVERY VERIFICATION SUITE");
        $this->info("  Target Database: {$targetPath}");
        $this->info("================================================================");

        if (!File::exists($targetPath)) {
            $this->error("Target database file does not exist: {$targetPath}");
            return self::FAILURE;
        }

        $this->configureRecoveryConnection($targetPath);

        $results = [];
        $hasErrors = false;

        $check = function (string $category, string $item, bool $passed, string $details) use (&$results, &$hasErrors) {
            $results[] = [
                'Category' => $category,
                'Item'     => $item,
                'Status'   => $passed ? 'PASS ✅' : 'FAIL ❌',
                'Details'  => $details,
            ];
            if (!$passed) {
                $hasErrors = true;
            }
        };

        // 1. Identity Verification
        $canonicalUsers = [
            'admin@icedu.org'          => 'super-admin',
            'opadmin@icedu.org'        => 'admin',
            'repomanager@icedu.org'    => 'repository-manager',
            'teacher@icedu.org'        => 'teacher',
            'student@icedu.org'        => 'student',
            'finance@icedu.org'        => 'finance',
            'ic.edu.bdg@gmail.com'     => 'organization-coordinator',
            'ca01@uat.org'             => 'student',
        ];

        foreach ($canonicalUsers as $email => $expectedRole) {
            $u = User::where('email', $email)->first();
            $exists = $u !== null;
            $hasRole = $exists && $u->hasRole($expectedRole);
            $check('Identity', "User {$email}", $exists && $hasRole, $exists ? "ID: {$u->id}, Roles: " . implode(', ', $u->getRoleNames()->all()) : 'Missing');
        }

        // Coordinator Identity Safety Boundary
        $indra = User::where('email', 'ic.edu.bdg@gmail.com')->first();
        $indraNoStudent = $indra && !$indra->hasRole('student');
        $check('Identity Safety', 'Indra Coordinator Isolation', (bool) $indraNoStudent, $indra ? "Roles: " . implode(', ', $indra->getRoleNames()->all()) . " (Must NOT have student)" : 'Missing');

        // 2. Organization O1 Hierarchy
        $org = Organization::where('slug', 'icedu-uat-university')->first();
        $check('Organization (O1)', "Org 'iC.edu UAT University'", $org !== null && $org->status === OrganizationStatus::Active, $org ? "ID: {$org->id}, Status: {$org->status->value}" : 'Missing');

        $indraMem = $org ? OrganizationMembership::where('organization_id', $org->id)->where('user_id', $indra?->id)->first() : null;
        $check('Organization (O1)', 'Indra Coordinator Membership', $indraMem !== null && $indraMem->role === MembershipRole::Coordinator, $indraMem ? "Role: {$indraMem->role->value}, Status: {$indraMem->status->value}" : 'Missing');

        $ca01 = User::where('email', 'ca01@uat.org')->first();
        $ca01Mem = $org ? OrganizationMembership::where('organization_id', $org->id)->where('user_id', $ca01?->id)->first() : null;
        $check('Organization (O1)', 'CA01 Candidate Membership', $ca01Mem !== null && $ca01Mem->role === MembershipRole::Member && $ca01Mem->member_identifier === 'NIM01', $ca01Mem ? "Role: {$ca01Mem->role->value}, NIM: {$ca01Mem->member_identifier}" : 'Missing');

        $group = $org ? OrganizationGroup::where('organization_id', $org->id)->where('name', 'Class 9A')->first() : null;
        $inGroup = $group && $ca01Mem ? OrganizationGroupMember::where('group_id', $group->id)->where('membership_id', $ca01Mem->id)->exists() : false;
        $check('Organization (O1)', "Group 'Class 9A' Enrollment", (bool) $inGroup, $group ? "Group ID: {$group->id}, CA01 Member Enrolled: " . ($inGroup ? 'YES' : 'NO') : 'Missing');

        // 3. Commerce & Voucher Engine (O2)
        $product = Product::where('slug', 'toeic-mock-test-package')->first();
        $check('Commerce (O2)', 'Product 85k TOEIC Package', $product !== null && (float) $product->price === 85000.0, $product ? "ID: {$product->id}, Price: {$product->price}" : 'Missing');

        $coupon = Coupon::where('code', 'TOEIC-JX3JFS')->first();
        $check('Commerce (O2)', 'Voucher TOEIC-JX3JFS', $coupon !== null && (float) $coupon->value === 10.0 && $coupon->used_count === 1, $coupon ? "Used: {$coupon->used_count}/{$coupon->usage_limit}, State: {$coupon->effective_state}" : 'Missing');

        $order = Order::where('order_number', 'ORD-20260907-3DWQ')->first();
        $check('Commerce (O2)', 'Order ORD-20260907-3DWQ', $order !== null && (float) $order->grand_total === 254745.0, $order ? "Subtotal: {$order->subtotal}, Discount: {$order->discount}, Total: {$order->grand_total}" : 'Missing');

        $invoice = Invoice::where('invoice_number', 'INV-20260907-DEZK')->first();
        $check('Commerce (O2)', 'Invoice INV-20260907-DEZK', $invoice !== null && $invoice->status === InvoiceStatus::Paid, $invoice ? "Amount: {$invoice->amount}, Status: {$invoice->status->value}" : 'Missing');

        $payment = Payment::where('reference_number', 'PAY-20260907-0NGF')->first();
        $proofExists = $payment && File::exists(storage_path('app/private/' . $payment->proof_path));
        $check('Commerce (O2)', 'Payment PAY-20260907-0NGF', $payment !== null && $payment->status === PaymentStatus::Success && $payment->transaction_id === 'BCA-UAT-CONFIRM-01', $payment ? "Status: {$payment->status->value}, Proof On Disk: " . ($proofExists ? 'YES' : 'NO') : 'Missing');

        $redemption = CouponRedemption::where('coupon_id', $coupon?->id)->where('order_id', $order?->id)->first();
        $check('Commerce (O2)', 'Coupon Redemption (Consumed)', $redemption !== null && $redemption->status === 'consumed', $redemption ? "Status: {$redemption->status}, Discount: {$redemption->discount_amount}" : 'Missing');

        // 4. Entitlements & Seat Allocations (O3)
        $entitlement = $org ? OrganizationEntitlement::where('organization_id', $org->id)->first() : null;
        $entitlementValid = $entitlement && $entitlement->total_seats === 3 && $entitlement->allocated_seats === 1 && $entitlement->available_seats === 2;
        $check('Entitlement (O3)', 'Entitlement Pool 3 Seats', (bool) $entitlementValid, $entitlement ? "Total: {$entitlement->total_seats}, Allocated: {$entitlement->allocated_seats}, Available: {$entitlement->available_seats}" : 'Missing');

        $seatAlloc = $entitlement && $ca01Mem ? OrganizationSeatAllocation::where('organization_entitlement_id', $entitlement->id)->where('organization_membership_id', $ca01Mem->id)->first() : null;
        $check('Entitlement (O3)', 'Seat Allocation to CA01', $seatAlloc !== null && $seatAlloc->status === SeatAllocationStatus::Active, $seatAlloc ? "Status: {$seatAlloc->status->value}, Allocated By ID: {$seatAlloc->allocated_by}" : 'Missing');

        $cbtAssignmentsCount = CandidateTestAssignment::count();
        $check('Entitlement (O3)', 'Candidate CBT Assignments Count', $cbtAssignmentsCount === 0, "Count: {$cbtAssignmentsCount} (Must be 0 until assigned via CBT workflow)");

        // 5. Governed Assessment Request & Test Draft
        $req = AssessmentRequest::where('title', 'TOEIC Mock Test')->first();
        $check('Governance', "Assessment Request 'TOEIC Mock Test'", $req !== null && $req->status === 'draft_created', $req ? "ID: {$req->id}, Status: {$req->status}, Context: {$req->program_context}" : 'Missing');

        $testDraft = Test::where('title', 'TOEIC Mock Test Group UAT')->first();
        $section = $testDraft ? TestSection::where('test_id', $testDraft->id)->first() : null;
        $questionCount = $testDraft ? TestQuestion::whereHas('section', fn($q) => $q->where('test_id', $testDraft->id))->count() : 0;

        $check('Governance', "Governed Draft 'TOEIC Mock Test Group UAT'", $testDraft !== null && $testDraft->status === 'draft' && !$testDraft->is_published && $testDraft->pass_score === 700 && $testDraft->duration_minutes === 120, $testDraft ? "ID: {$testDraft->id}, Mode: {$testDraft->assessment_mode->value}, Status: {$testDraft->status}, Published: " . ($testDraft->is_published ? 'YES' : 'NO') : 'Missing');

        $check('Governance', "Governed Section 'Section 1: General Core'", $section !== null, $section ? "ID: {$section->id}, Title: {$section->title}" : 'Missing');

        $check('Governance', 'Zero Hallucinated Question Content', $questionCount === 0, "Question Count: {$questionCount} (Preserved empty draft structure; zero invented questions)");

        $this->table(['Category', 'Item', 'Status', 'Details'], $results);

        if ($hasErrors) {
            $this->error("Verification FAILED: One or more UAT recovery assertions did not pass.");
            return self::FAILURE;
        }

        $this->info("Verification PASSED: All canonical assertions matched 100%!");
        return self::SUCCESS;
    }
}
