<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportExportController extends Controller
{
    public function adminPdf(Request $request)
    {
        return $this->pdf($request, true);
    }

    public function adminExcel(Request $request): StreamedResponse
    {
        return $this->excel($request, true);
    }

    public function cashierPdf(Request $request)
    {
        return $this->pdf($request, false);
    }

    public function cashierExcel(Request $request): StreamedResponse
    {
        return $this->excel($request, false);
    }

    private function pdf(Request $request, bool $admin)
    {
        [$start, $end, $periodLabel] = $this->period($request);
        $transactions = $this->transactions($request, $admin, $start, $end)->get();

        return Pdf::loadView('reports.sales-pdf', [
            'admin' => $admin,
            'transactions' => $transactions,
            'periodLabel' => $periodLabel,
            'periodSales' => $transactions->sum('total'),
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')
            ->download($this->filename($periodLabel, 'pdf'));
    }

    private function excel(Request $request, bool $admin): StreamedResponse
    {
        [$start, $end, $periodLabel] = $this->period($request);
        $transactions = $this->transactions($request, $admin, $start, $end)->get();
        $filename = $this->filename($periodLabel, 'csv');

        return response()->streamDownload(function () use ($admin, $transactions, $periodLabel): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('Tidak dapat membuka aliran ekspor laporan.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Laporan Penjualan', $periodLabel]);
            fputcsv($output, ['Total Transaksi', $transactions->count()]);
            fputcsv($output, ['Total Penjualan', $transactions->sum('total')]);
            fputcsv($output, []);
            fputcsv($output, $admin
                ? ['Invoice', 'Waktu', 'Kasir', 'Pelanggan', 'Metode Pembayaran', 'Total']
                : ['Invoice', 'Waktu', 'Pelanggan', 'Metode Pembayaran', 'Total']);

            foreach ($transactions as $sale) {
                $row = [
                    $this->spreadsheetText($sale->invoice),
                    $sale->created_at->format('d/m/Y H:i'),
                ];

                if ($admin) {
                    $row[] = $this->spreadsheetText($sale->cashier_name);
                }

                $row[] = $this->spreadsheetText($sale->customer?->name ?? $sale->customer_type);
                $row[] = strtoupper($sale->payment_method);
                $row[] = (float) $sale->total;

                fputcsv($output, $row);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function period(Request $request): array
    {
        $currentYear = now()->year;
        $filters = $request->validate([
            'period' => ['nullable', 'in:day,week,month'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'week' => ['nullable', 'integer', 'between:1,53'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:1900,'.$currentYear],
        ]);

        $period = $filters['period'] ?? 'day';
        $year = (int) ($filters['year'] ?? $currentYear);
        $week = (int) ($filters['week'] ?? now()->format('W'));
        $month = (int) ($filters['month'] ?? now()->month);

        if ($period === 'day') {
            $start = Carbon::parse($filters['date'] ?? today()->toDateString())->startOfDay();
            $end = $start->copy()->endOfDay();
            $periodLabel = $start->format('d/m/Y');
        } elseif ($period === 'week') {
            $start = Carbon::now()->setISODate($year, $week, 1)->startOfDay();
            if ((int) $start->format('o') !== $year) {
                abort(422, 'Minggu yang dipilih tidak tersedia pada tahun tersebut.');
            }

            $end = $start->copy()->endOfWeek(Carbon::SUNDAY);
            $periodLabel = sprintf('Minggu ke-%d (%s–%s)', $week, $start->format('d/m'), $end->format('d/m/Y'));
        } else {
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $end = $start->copy()->endOfMonth()->endOfDay();
            $monthNames = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
            ];
            $periodLabel = $monthNames[$month].' '.$year;
        }

        return [$start, $end, $periodLabel];
    }

    private function transactions(Request $request, bool $admin, Carbon $start, Carbon $end): Builder
    {
        return Sale::query()
            ->when(! $admin, fn (Builder $query) => $query->where('cashier_id', $request->user()->id))
            ->whereBetween('created_at', [$start, $end])
            ->with('customer')
            ->oldest('created_at');
    }

    private function filename(string $periodLabel, string $extension): string
    {
        return 'laporan-penjualan-'.Str::slug(str_replace('/', '-', $periodLabel)).'.'.$extension;
    }

    private function spreadsheetText(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
