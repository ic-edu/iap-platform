<?php

use App\Modules\Reporting\Controllers\ReportingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'role:admin|super-admin|teacher'])->prefix('admin/reporting')->group(function () {
    Route::get('/', [ReportingController::class, 'index'])->name('admin.reporting.index');
    Route::get('/export/csv', [ReportingController::class, 'exportCsv'])->name('admin.reporting.export-csv');
});
