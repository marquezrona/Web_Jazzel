<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_a_pdf_report_for_the_selected_date(): void
    {
        $this->travelTo(Carbon::parse('2026-10-29 12:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'kasir', 'name' => 'Kasir Laporan']);
        $this->createSale($cashier, 'INV-PDF-001', 25000, now());

        $response = $this->actingAs($admin)->get(route('admin.laporan.export.pdf', [
            'period' => 'day',
            'date' => today()->toDateString(),
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=laporan-penjualan-'.today()->format('d-m-Y').'.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_each_report_page_displays_period_preserving_export_links(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'kasir']);
        $filters = ['period' => 'month', 'month' => now()->month, 'year' => now()->year];

        $this->actingAs($admin)
            ->get(route('admin.laporan', $filters))
            ->assertOk()
            ->assertSee(e(route('admin.laporan.export.pdf', $filters)), false)
            ->assertSee(e(route('admin.laporan.export.excel', $filters)), false);

        $this->actingAs($cashier)
            ->get(route('cashier.laporan', $filters))
            ->assertOk()
            ->assertSee(e(route('cashier.laporan.export.pdf', $filters)), false)
            ->assertSee(e(route('cashier.laporan.export.excel', $filters)), false);
    }

    public function test_cashier_excel_export_only_contains_the_cashiers_sales_for_the_selected_period(): void
    {
        $this->travelTo(Carbon::parse('2026-10-29 12:00:00'));
        $cashier = User::factory()->create(['role' => 'kasir']);
        $otherCashier = User::factory()->create(['role' => 'kasir']);
        $this->createSale($cashier, 'INV-CSV-OWN', 18000, now());
        $this->createSale($otherCashier, 'INV-CSV-OTHER', 45000, now());
        $this->createSale($cashier, 'INV-CSV-OLDER', 12000, now()->subDay());

        $response = $this->actingAs($cashier)->get(route('cashier.laporan.export.excel', [
            'period' => 'day',
            'date' => today()->toDateString(),
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=laporan-penjualan-29-10-2026.csv');

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('INV-CSV-OWN', $content);
        $this->assertStringNotContainsString('INV-CSV-OTHER', $content);
        $this->assertStringNotContainsString('INV-CSV-OLDER', $content);
    }

    public function test_report_export_routes_are_limited_to_the_matching_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cashier = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($cashier)->get(route('admin.laporan.export.pdf'))->assertForbidden();
        $this->actingAs($admin)->get(route('cashier.laporan.export.excel'))->assertForbidden();
    }

    private function createSale(User $cashier, string $invoice, int $total, Carbon $createdAt): Sale
    {
        $sale = Sale::query()->create([
            'invoice' => $invoice,
            'cashier_id' => $cashier->id,
            'cashier_name' => $cashier->name,
            'customer_type' => 'Umum',
            'subtotal' => $total,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'tax' => 0,
            'other_fee' => 0,
            'total' => $total,
            'paid' => $total,
            'change' => 0,
            'payment_method' => 'tunai',
        ]);

        $sale->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $sale;
    }
}
