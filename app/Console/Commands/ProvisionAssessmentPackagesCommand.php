<?php

namespace App\Console\Commands;

use App\Modules\Commerce\Domain\Enums\AssessmentFamily;
use App\Modules\Commerce\Domain\Models\Product;
use Illuminate\Console\Command;

class ProvisionAssessmentPackagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iap:commerce:provision-assessment-packages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Idempotently provision standard institutional assessment packages into the commercial product catalog';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting institutional assessment package provisioning...');

        $packages = [
            [
                'slug'              => 'toeic-mock-test-package',
                'title'             => 'TOEIC Mock Test Package',
                'product_type'      => 'assessment',
                'assessment_family' => AssessmentFamily::Toeic->value,
                'price'             => 750000,
                'description'       => 'Standard institutional TOEIC assessment package with verified scoring and digital certificate eligibility upon completion.',
                'is_active'         => true,
                'is_featured'       => true,
                'test_id'           => null,
                'course_id'         => null,
            ],
            [
                'slug'              => 'toefl-ibt-mock-test-package',
                'title'             => 'TOEFL iBT Mock Test Package',
                'product_type'      => 'assessment',
                'assessment_family' => AssessmentFamily::Toefl->value,
                'price'             => 850000,
                'description'       => 'Comprehensive institutional TOEFL iBT preparation and simulation package with automated evaluation.',
                'is_active'         => true,
                'is_featured'       => false,
                'test_id'           => null,
                'course_id'         => null,
            ],
            [
                'slug'              => 'ielts-assessment-package',
                'title'             => 'IELTS Assessment Package',
                'product_type'      => 'assessment',
                'assessment_family' => AssessmentFamily::Ielts->value,
                'price'             => 900000,
                'description'       => 'Institutional IELTS Academic mock test package with multi-skill evaluation and scoring breakdown.',
                'is_active'         => true,
                'is_featured'       => false,
                'test_id'           => null,
                'course_id'         => null,
            ],
            [
                'slug'              => 'general-assessment-package',
                'title'             => 'General Assessment Package',
                'product_type'      => 'assessment',
                'assessment_family' => AssessmentFamily::General->value,
                'price'             => 350000,
                'description'       => 'Institutional general proficiency diagnostics and proficiency evaluation package.',
                'is_active'         => true,
                'is_featured'       => false,
                'test_id'           => null,
                'course_id'         => null,
            ],
            [
                'slug'              => 'vocational-assessment-package',
                'title'             => 'Vocational Assessment Package',
                'product_type'      => 'assessment',
                'assessment_family' => AssessmentFamily::Vocational->value,
                'price'             => 500000,
                'description'       => 'Specialized vocational and vocational-technical skills assessment package.',
                'is_active'         => true,
                'is_featured'       => false,
                'test_id'           => null,
                'course_id'         => null,
            ],
        ];

        $count = 0;
        foreach ($packages as $pkg) {
            $product = Product::updateOrCreate(
                ['slug' => $pkg['slug']],
                $pkg
            );
            $this->line("  ✓ [{$product->assessment_family}] {$product->title} (IDR " . number_format($product->price) . ") - test_id: NULL");
            $count++;
        }

        $this->info("Successfully provisioned {$count} assessment package(s) into product catalog.");

        return Command::SUCCESS;
    }
}
