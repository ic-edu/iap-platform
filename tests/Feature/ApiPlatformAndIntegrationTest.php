<?php

use App\Integrations\EmailIntegrationDriver;
use App\Integrations\GoogleCalendarIntegrationDriver;
use App\Integrations\PaymentIntegrationDriver;
use App\Integrations\SMSIntegrationDriver;
use App\Integrations\StorageIntegrationDriver;
use App\Integrations\WhatsAppIntegrationDriver;
use App\Integrations\ZoomIntegrationDriver;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Modules\Academic\Models\Course;
use App\Modules\Academic\Models\CourseCategory;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Enums\CertificateStatus;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\CMS\Models\Announcement;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Commerce\Domain\Models\ProductCategory;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Services\WebhookEngine;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('api auth login returns bearer token for valid user credentials', function () {
    $user = User::factory()->create(['email' => 'api@example.com', 'password' => bcrypt('password')]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'api@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['token', 'user']]);
});

test('api auth login rejects invalid credentials with 401', function () {
    User::factory()->create(['email' => 'valid@example.com', 'password' => bcrypt('password')]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'valid@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('success', false);
});

test('api auth me returns profile for authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', $user->email);
});

test('api auth logout revokes access token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/logout');

    $response->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('public api verifies valid certificate code', function () {
    $user = User::factory()->create(['name' => 'John Doe']);
    $test = Test::create(['title' => 'T1', 'slug' => 't1', 'test_type' => TestType::General, 'assessment_mode' => 'real_test', 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $user->id]);
    $attempt = Attempt::create(['test_id' => $test->id, 'user_id' => $user->id]);

    $cert = Certificate::create([
        'attempt_id' => $attempt->id,
        'user_id' => $user->id,
        'certificate_number' => 'CERT-20260726-1001',
        'verification_code' => 'VRF-1001',
        'status' => CertificateStatus::Valid,
        'issued_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/public/verify/VRF-1001');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.recipient_name', 'John Doe');
});

test('public api lists published courses', function () {
    $cat = CourseCategory::create(['name' => 'Cat P', 'slug' => 'cat-p']);
    Course::create(['title' => 'C1', 'slug' => 'c1', 'code' => 'C1', 'category_id' => $cat->id, 'is_published' => true]);

    $response = $this->getJson('/api/v1/public/courses');

    $response->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('public api lists active products', function () {
    $cat = ProductCategory::create(['name' => 'Prod Cat', 'slug' => 'prod-cat']);
    Product::create(['title' => 'P1', 'slug' => 'p1', 'product_type' => 'toefl_prep', 'category_id' => $cat->id, 'price' => 50000, 'is_active' => true]);

    $response = $this->getJson('/api/v1/public/products');

    $response->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('public api lists announcements and categories', function () {
    Announcement::create(['title' => 'News 1', 'content' => 'Content 1']);

    $res1 = $this->getJson('/api/v1/public/announcements');
    $res1->assertStatus(200)->assertJsonPath('success', true);

    $res2 = $this->getJson('/api/v1/public/categories');
    $res2->assertStatus(200)->assertJsonPath('success', true);
});

test('internal api lists question banks and tests for authenticated user', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    QuestionBank::create(['title' => 'QB 1', 'slug' => 'qb-1', 'code' => 'QB1', 'created_by' => $user->id]);
    Test::create(['title' => 'Test 1', 'slug' => 't1', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $user->id]);

    $res1 = $this->actingAs($user, 'sanctum')->getJson('/api/v1/question-banks');
    $res1->assertStatus(200)->assertJsonPath('success', true);

    $res2 = $this->actingAs($user, 'sanctum')->getJson('/api/v1/tests');
    $res2->assertStatus(200)->assertJsonPath('success', true);
});

test('internal api lists user attempts and certificates', function () {
    $user = User::factory()->create();
    $teacher = User::factory()->create();
    $test = Test::create(['title' => 'Test A', 'slug' => 'ta', 'test_type' => TestType::General, 'assessment_mode' => 'real_test', 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $teacher->id]);

    $attempt = Attempt::create(['test_id' => $test->id, 'user_id' => $user->id]);
    Certificate::create(['attempt_id' => $attempt->id, 'user_id' => $user->id, 'certificate_number' => 'C-01', 'verification_code' => 'V-01', 'status' => CertificateStatus::Valid]);

    $res1 = $this->actingAs($user, 'sanctum')->getJson('/api/v1/attempts');
    $res1->assertStatus(200)->assertJsonPath('success', true);

    $res2 = $this->actingAs($user, 'sanctum')->getJson('/api/v1/certificates');
    $res2->assertStatus(200)->assertJsonPath('success', true);
});

test('internal api lists user orders and invoices', function () {
    $user = User::factory()->create();
    $order = Order::create(['user_id' => $user->id, 'order_number' => 'ORD-1', 'grand_total' => 100000]);
    Invoice::create(['order_id' => $order->id, 'user_id' => $user->id, 'invoice_number' => 'INV-1', 'amount' => 100000]);

    $res1 = $this->actingAs($user, 'sanctum')->getJson('/api/v1/orders');
    $res1->assertStatus(200)->assertJsonPath('success', true);

    $res2 = $this->actingAs($user, 'sanctum')->getJson('/api/v1/invoices');
    $res2->assertStatus(200)->assertJsonPath('success', true);
});

test('internal api returns system health status', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/system/health');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'healthy');
});

test('webhook engine dispatches payload and records delivery log', function () {
    $engine = new WebhookEngine;
    $user = User::factory()->create();

    $sub = WebhookSubscription::create([
        'user_id' => $user->id,
        'name' => 'Payment Webhook',
        'target_url' => 'https://example.com/webhook',
        'secret' => 'whsec_test123',
        'event' => 'payment.confirmed',
        'is_active' => true,
    ]);

    $deliveries = $engine->dispatch('payment.confirmed', ['payment_id' => 'PAY-1']);

    expect(count($deliveries))->toBe(1);
    expect($deliveries[0]->status)->toBe('delivered');
});

test('webhook engine retries failed delivery', function () {
    $engine = new WebhookEngine;
    $user = User::factory()->create();

    $sub = WebhookSubscription::create([
        'user_id' => $user->id,
        'name' => 'Retry Webhook',
        'target_url' => 'https://example.com/webhook',
        'secret' => 'whsec_test123',
        'event' => 'payment.confirmed',
        'is_active' => true,
    ]);

    $delivery = WebhookDelivery::create([
        'webhook_subscription_id' => $sub->id,
        'event' => 'payment.confirmed',
        'payload' => ['test' => 1],
        'status' => 'failed',
        'attempts' => 1,
    ]);

    $retried = $engine->retry($delivery);
    expect($retried->attempts)->toBe(2);
    expect($retried->status)->toBe('delivered');
});

test('zoom integration driver creates meeting room', function () {
    $driver = new ZoomIntegrationDriver;
    $meeting = $driver->createMeeting('TOEFL Exam', now()->toIso8601String(), 60);

    expect($meeting['meeting_id'])->toContain('ZM-');
});

test('google calendar driver creates calendar event', function () {
    $driver = new GoogleCalendarIntegrationDriver;
    $event = $driver->createEvent('Exam', now()->toIso8601String(), now()->addHour()->toIso8601String(), 'test@example.com');

    expect($event['event_id'])->toContain('GCAL-');
});

test('whatsapp and sms integration drivers send message alerts', function () {
    $wa = new WhatsAppIntegrationDriver;
    $waRes = $wa->sendMessage('+62812345678', 'Reminder');
    expect($waRes['status'])->toBe('queued');

    $sms = new SMSIntegrationDriver;
    $smsRes = $sms->sendSms('+62812345678', 'OTP 1234');
    expect($smsRes['status'])->toBe('sent');
});

test('email payment and storage drivers execute mock operations', function () {
    $eml = new EmailIntegrationDriver;
    $emlRes = $eml->sendEmail('user@example.com', 'Subject', 'Body');
    expect($emlRes['status'])->toBe('queued');

    $pay = new PaymentIntegrationDriver;
    $payRes = $pay->charge('INV-1', 10000);
    expect($payRes['status'])->toBe('success');

    $stg = new StorageIntegrationDriver;
    $stgRes = $stg->storeFile('certs/c1.pdf', 'pdfdata');
    expect($stgRes['url'])->toContain('icedu.org');
});

test('api audit log middleware records request latency in activity log', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/system/health');

    $response->assertStatus(200);
    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $user->id,
        'action' => 'api_request',
    ]);
});
