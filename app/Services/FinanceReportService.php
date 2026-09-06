<?php

namespace App\Services;

use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class FinanceReportService
{
    /**
     * Build the filtered query for Finance Payment & Invoice Reports.
     *
     * @param array{
     *     status?: string|null,
     *     search?: string|null,
     *     start_date?: string|null,
     *     end_date?: string|null,
     *     product_id?: string|null
     * } $filters
     * @return Builder<\App\Modules\Commerce\Domain\Models\Payment>
     */
    public function buildReportQuery(array $filters): Builder
    {
        $query = Payment::validCommerce()
            ->with(['user', 'invoice.order.items.product.test', 'invoice.order.organization'])
            ->latest('created_at');

        // 1. Status Filter
        $status = $filters['status'] ?? 'all';
        if (in_array($status, ['pending', 'success', 'failed', 'refunded'], true)) {
            $query->where('status', $status);
        }

        // 2. Search Filter (Payment ref, invoice ref, candidate name, candidate email, transaction ID, organization name)
        if (!empty($filters['search'])) {
            $searchTerm = trim($filters['search']);
            $query->where(function (Builder $q) use ($searchTerm) {
                $q->where('reference_number', 'like', "%{$searchTerm}%")
                    ->orWhere('transaction_id', 'like', "%{$searchTerm}%")
                    ->orWhereHas('user', function (Builder $userQ) use ($searchTerm) {
                        $userQ->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('email', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('invoice', function (Builder $invQ) use ($searchTerm) {
                        $invQ->where('invoice_number', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('invoice.order.organization', function (Builder $orgQ) use ($searchTerm) {
                        $orgQ->where('name', 'like', "%{$searchTerm}%");
                    });
            });
        }

        // 3. Date Range Filter (Using canonical transaction initiation timestamp `created_at`)
        if (!empty($filters['start_date'])) {
            try {
                $startDate = Carbon::parse($filters['start_date'])->startOfDay();
                $query->where('created_at', '>=', $startDate);
            } catch (\Throwable $e) {
                // Ignore invalid date safely
            }
        }

        if (!empty($filters['end_date'])) {
            try {
                $endDate = Carbon::parse($filters['end_date'])->endOfDay();
                $query->where('created_at', '<=', $endDate);
            } catch (\Throwable $e) {
                // Ignore invalid date safely
            }
        }

        // 4. Product / Package Filter
        if (!empty($filters['product_id'])) {
            $productId = $filters['product_id'];
            $query->whereHas('invoice.order.items', function (Builder $itemQ) use ($productId) {
                $itemQ->where('product_id', $productId)
                    ->orWhereHas('product', function (Builder $prodQ) use ($productId) {
                        $prodQ->where('slug', $productId)
                            ->orWhere('title', $productId);
                    });
            });
        }

        return $query;
    }

    /**
     * Compute financial summary metrics on the filtered dataset.
     *
     * @param Builder<\App\Modules\Commerce\Domain\Models\Payment> $filteredQuery
     * @return array{
     *     total_count: int,
     *     pending_count: int,
     *     confirmed_count: int,
     *     cancelled_count: int,
     *     pending_amount: float,
     *     confirmed_amount: float,
     *     cancelled_amount: float,
     *     total_gross_amount: float,
     *     total_tax_amount: float,
     *     total_base_amount: float,
     *     total_discount_amount: float
     * }
     */
    public function calculateFinancialSummary(Builder $filteredQuery): array
    {
        $payments = (clone $filteredQuery)->get();

        $totalCount = $payments->count();
        $pendingCount = 0;
        $confirmedCount = 0;
        $cancelledCount = 0;

        $pendingAmount = 0.0;
        $confirmedAmount = 0.0;
        $cancelledAmount = 0.0;
        $totalGrossAmount = 0.0;

        $totalTaxAmount = 0.0;
        $totalBaseAmount = 0.0;
        $totalDiscountAmount = 0.0;

        foreach ($payments as $payment) {
            $amount = (float) $payment->amount;
            $status = is_object($payment->status) ? $payment->status->value : (string) $payment->status;

            $totalGrossAmount += $amount;

            if ($status === 'pending') {
                $pendingCount++;
                $pendingAmount += $amount;
            } elseif (in_array($status, ['success', 'paid'], true)) {
                $confirmedCount++;
                $confirmedAmount += $amount;
            } elseif (in_array($status, ['failed', 'cancelled'], true)) {
                $cancelledCount++;
                $cancelledAmount += $amount;
            }

            // Extract persisted order financial breakdown
            $order = $payment->invoice?->order;
            if ($order) {
                $totalBaseAmount += (float) ($order->subtotal ?? 0);
                $totalDiscountAmount += (float) ($order->discount ?? 0);
                $totalTaxAmount += (float) ($order->tax ?? 0);
            }
        }

        return [
            'total_count'           => $totalCount,
            'pending_count'         => $pendingCount,
            'confirmed_count'       => $confirmedCount,
            'cancelled_count'       => $cancelledCount,
            'pending_amount'        => $pendingAmount,
            'confirmed_amount'      => $confirmedAmount,
            'cancelled_amount'      => $cancelledAmount,
            'total_gross_amount'    => $totalGrossAmount,
            'total_tax_amount'      => $totalTaxAmount,
            'total_base_amount'     => $totalBaseAmount,
            'total_discount_amount' => $totalDiscountAmount,
        ];
    }

    /**
     * Get active products list for filter dropdown.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function getFilterProducts()
    {
        return Product::orderBy('title')->get(['id', 'title', 'slug', 'product_type']);
    }

    /**
     * Stream CSV export of filtered payments.
     */
    public function exportCsv(Builder $query): StreamedResponse
    {
        $payments = (clone $query)->get();
        $filename = 'finance_payment_report_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($payments) {
            $handle = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Payment Reference',
                'Payment Status',
                'Payment Date',
                'Candidate Name',
                'Candidate Email',
                'Product / Package',
                'Order Reference',
                'Invoice Reference',
                'Base Amount (IDR)',
                'Discount (IDR)',
                'Tax / PPN (IDR)',
                'Grand Total (IDR)',
                'Gateway',
                'Transaction ID',
                'Confirmed At',
                'Notes / Reason',
            ]);

            foreach ($payments as $payment) {
                $order = $payment->invoice?->order;
                $productTitle = $order?->items?->first()?->product?->title ?? 'Package Item';
                $orderNumber = $order?->order_number ?? '—';
                $invoiceNumber = $payment->invoice?->invoice_number ?? '—';
                $status = is_object($payment->status) ? $payment->status->value : (string) $payment->status;

                $baseAmount = $order ? (float) $order->subtotal : (float) $payment->amount;
                $discount = $order ? (float) $order->discount : 0.0;
                $tax = $order ? (float) $order->tax : 0.0;
                $grandTotal = (float) $payment->amount;

                fputcsv($handle, [
                    $payment->reference_number,
                    strtoupper($status),
                    $payment->created_at?->format('Y-m-d H:i:s') ?? '',
                    $payment->user?->name ?? 'Candidate',
                    $payment->user?->email ?? '',
                    $productTitle,
                    $orderNumber,
                    $invoiceNumber,
                    $baseAmount,
                    $discount,
                    $tax,
                    $grandTotal,
                    strtoupper(str_replace('_', ' ', $payment->payment_gateway)),
                    $payment->transaction_id ?? '—',
                    $payment->confirmed_at?->format('Y-m-d H:i:s') ?? '—',
                    $payment->proof_notes ?? '—',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Stream XLSX export of filtered payments using native OpenXML packaging.
     */
    public function exportXlsx(Builder $query): StreamedResponse
    {
        $payments = (clone $query)->get();
        $filename = 'finance_payment_report_' . now()->format('Ymd_His') . '.xlsx';

        // Prepare temporary file for OpenXML zip
        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive();
        $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // 2. _rels/.rels
        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
        $zip->addFromString('_rels/.rels', $rootRels);

        // 3. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // 4. xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Payment Reports" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        // 5. xl/styles.xml
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>'
            . '</cellXfs>'
            . '</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        // 6. xl/worksheets/sheet1.xml
        $rowsXml = '<row r="1">';
        $columns = [
            'Payment Reference', 'Payment Status', 'Payment Date', 'Candidate Name',
            'Candidate Email', 'Product / Package', 'Order Reference', 'Invoice Reference',
            'Base Amount (IDR)', 'Discount (IDR)', 'Tax / PPN (IDR)', 'Grand Total (IDR)',
            'Gateway', 'Transaction ID', 'Confirmed At', 'Notes / Reason'
        ];
        foreach ($columns as $idx => $colName) {
            $colLetter = $this->getColumnLetter($idx + 1);
            $rowsXml .= "<c r=\"{$colLetter}1\" s=\"1\" t=\"inlineStr\"><is><t>" . htmlspecialchars($colName, ENT_XML1) . "</t></is></c>";
        }
        $rowsXml .= '</row>';

        $rowIndex = 2;
        foreach ($payments as $payment) {
            $order = $payment->invoice?->order;
            $productTitle = $order?->items?->first()?->product?->title ?? 'Package Item';
            $orderNumber = $order?->order_number ?? '—';
            $invoiceNumber = $payment->invoice?->invoice_number ?? '—';
            $status = is_object($payment->status) ? $payment->status->value : (string) $payment->status;

            $baseAmount = $order ? (float) $order->subtotal : (float) $payment->amount;
            $discount = $order ? (float) $order->discount : 0.0;
            $tax = $order ? (float) $order->tax : 0.0;
            $grandTotal = (float) $payment->amount;

            $dataValues = [
                $payment->reference_number,
                strtoupper($status),
                $payment->created_at?->format('Y-m-d H:i:s') ?? '',
                $payment->user?->name ?? 'Candidate',
                $payment->user?->email ?? '',
                $productTitle,
                $orderNumber,
                $invoiceNumber,
                (string) $baseAmount,
                (string) $discount,
                (string) $tax,
                (string) $grandTotal,
                strtoupper(str_replace('_', ' ', $payment->payment_gateway)),
                $payment->transaction_id ?? '—',
                $payment->confirmed_at?->format('Y-m-d H:i:s') ?? '—',
                $payment->proof_notes ?? '—',
            ];

            $rowsXml .= "<row r=\"{$rowIndex}\">";
            foreach ($dataValues as $idx => $val) {
                $colLetter = $this->getColumnLetter($idx + 1);
                $rowsXml .= "<c r=\"{$colLetter}{$rowIndex}\" t=\"inlineStr\"><is><t>" . htmlspecialchars((string) $val, ENT_XML1) . "</t></is></c>";
            }
            $rowsXml .= '</row>';
            $rowIndex++;
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $rowsXml . '</sheetData>'
            . '</worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        $zip->close();

        $headers = [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($tempPath) {
            readfile($tempPath);
            @unlink($tempPath);
        }, 200, $headers);
    }

    /**
     * Convert 1-based index to Excel column letter (1 -> A, 2 -> B, ..., 27 -> AA).
     */
    protected function getColumnLetter(int $colIndex): string
    {
        $letter = '';
        while ($colIndex > 0) {
            $modulo = ($colIndex - 1) % 26;
            $letter = chr(65 + $modulo) . $letter;
            $colIndex = intval(($colIndex - $modulo) / 26);
        }
        return $letter;
    }
}
