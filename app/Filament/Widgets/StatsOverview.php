<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Kategori', Category::count())
                ->description('Kategori produk terdaftar')
                ->descriptionIcon('heroicon-m-tag')
                ->color('info'),

            Stat::make('Total Produk', Product::count())
                ->description('Keseluruhan produk terdaftar')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('success'),

            Stat::make(
                'Stok Menipis (≤ 5)',
                Product::where('stock', '<=', 5)->count()
            )
                ->description('Perlu segera dilakukan restock')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
