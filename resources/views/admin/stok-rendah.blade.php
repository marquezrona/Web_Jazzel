@extends('layouts.admin')

@section('title', 'Stok Rendah')

@section('content')
    <header class="page-heading">
        <h1>Stok Rendah</h1>
        <p>Barang dengan sisa stok kurang dari 10 perlu segera diperiksa atau ditambahkan.</p>
    </header>

    <section class="admin-card">
        <div class="admin-card-header d-flex justify-content-between align-items-center">
            <h2>Daftar Barang Stok Rendah</h2>
            <span class="text-secondary small">{{ $products->count() }} barang</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Stok Tersisa</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="fw-semibold">{{ $product->name }}</td>
                            <td>{{ $product->category ?? 'Umum' }}</td>
                            <td>Rp {{ number_format($product->price, 0, ',', '.') }}</td>
                            <td><span class="badge text-bg-danger">{{ $product->stock }}</span></td>
                            <td>
                                <span class="badge {{ $product->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $product->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <a href="{{ route('products.show', $product) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-5 text-center text-secondary">Tidak ada barang dengan stok di bawah 10.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
