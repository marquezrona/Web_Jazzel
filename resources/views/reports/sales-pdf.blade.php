<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Penjualan - {{ $periodLabel }}</title>
    <style>
        @page { margin: 28px; }
        body { color: #242424; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1 { margin: 0 0 5px; font-size: 20px; }
        p { margin: 3px 0; }
        .summary { margin: 18px 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 6px; text-align: left; }
        th { background: #eee; }
        .number { text-align: right; white-space: nowrap; }
        .signatures { margin-top: 42px; width: 100%; }
        .signatures td { width: 50%; border: 0; text-align: center; padding-top: 35px; }
    </style>
</head>
<body>
    <h1>Laporan Penjualan</h1>
    <p>Periode: {{ $periodLabel }}</p>
    <p>Dicetak: {{ $generatedAt->format('d/m/Y H:i') }}</p>
    @if (! $admin)
        <p>Kasir: {{ auth()->user()->name }}</p>
    @endif

    <div class="summary">
        <strong>Jumlah transaksi:</strong> {{ $transactions->count() }}
        &nbsp;&nbsp; <strong>Total penjualan:</strong> Rp {{ number_format((float) $periodSales, 0, ',', '.') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Waktu</th>
                @if ($admin)
                    <th>Kasir</th>
                @endif
                <th>Pelanggan</th>
                <th>Metode Pembayaran</th>
                <th class="number">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($transactions as $sale)
                <tr>
                    <td>{{ $sale->invoice }}</td>
                    <td>{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                    @if ($admin)
                        <td>{{ $sale->cashier_name }}</td>
                    @endif
                    <td>{{ $sale->customer?->name ?? $sale->customer_type }}</td>
                    <td>{{ strtoupper($sale->payment_method) }}</td>
                    <td class="number">Rp {{ number_format((float) $sale->total, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $admin ? 6 : 5 }}">Belum ada transaksi pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td>Mengetahui,<br><br><br>(____________________)</td>
            <td>{{ now()->format('d/m/Y') }}<br>Petugas,<br><br><br>(____________________)</td>
        </tr>
    </table>
</body>
</html>
