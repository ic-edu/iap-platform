<?php

namespace App\Services;

use App\Modules\Academic\Events\BatchOperationCompleted;
use App\Modules\Assessment\Models\Test;

class BatchOperationService
{
    /**
     * Bulk publish multiple tests by IDs.
     *
     * @param  array<int, string>  $testIds
     */
    public function bulkPublishTests(array $testIds): int
    {
        $updated = Test::whereIn('id', $testIds)->update(['is_published' => true]);

        event(new BatchOperationCompleted('bulk_publish_tests', $updated));

        return $updated;
    }
}
