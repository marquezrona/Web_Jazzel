<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\NfcCard;
use App\Models\NfcCardTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $sales = Sale::query()->when($user->role === 'kasir', fn (Builder $query) => $query->where('cashier_id', $user->id));
        $products = Product::query();

        return response()->json([
            'user' => $user,
            'role' => $user->role,
            'today_sales' => (clone $sales)->whereDate('created_at', today())->sum('total'),
            'month_sales' => (clone $sales)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total'),
            'transaction_count' => (clone $sales)->count(),
            'product_count' => (clone $products)->count(),
            'active_product_count' => (clone $products)->where('is_active', true)->count(),
            'low_stock_products' => (clone $products)->where('is_active', true)->where('stock', '<=', 5)->count(),
            'recent_sales' => (clone $sales)->with('customer')->latest()->limit(5)->get(),
            'store' => StoreSetting::current(),
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $isCashier = $request->user()->role === 'kasir';

        $products = Product::query()
            ->when($isCashier, fn (Builder $query) => $query->where('is_active', true)->where('stock', '>', 0))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): array => $this->productData($product));

        return response()->json(['data' => $products]);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $data = $this->validateProduct($request);
        $image = $data['image'] ?? null;
        unset($data['image']);
        if ($image) {
            $data['image'] = $image->store('products', 'public');
        }
        $data['is_active'] = $request->boolean('is_active', true);
        $product = Product::query()->create($data);

        return response()->json(['data' => $this->productData($product)], 201);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        $data = $this->validateProduct($request);
        $image = $data['image'] ?? null;
        unset($data['image']);
        if ($image) {
            $previousImage = $product->image;
            $data['image'] = $image->store('products', 'public');
            if ($previousImage) {
                Storage::disk('public')->delete($previousImage);
            }
        }
        $data['is_active'] = $request->boolean('is_active');
        $product->update($data);

        return response()->json(['data' => $this->productData($product->fresh())]);
    }

    public function destroyProduct(Product $product): JsonResponse
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return response()->json(['message' => 'Barang berhasil dihapus.']);
    }

    public function sales(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'period' => ['nullable', 'in:all,day,week,month,year'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:1900,9999'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = Sale::query()
            ->with(['items', 'customer'])
            ->when($request->user()->role === 'kasir', fn (Builder $query) => $query->where('cashier_id', $request->user()->id))
            ->when($filters['period'] ?? 'all', function (Builder $query, string $period): void {
                if ($period === 'all') {
                    return;
                }

                $start = match ($period) {
                    'day' => now()->startOfDay(),
                    'week' => now()->startOfWeek(Carbon::MONDAY),
                    'month' => now()->startOfMonth(),
                    'year' => now()->startOfYear(),
                };
                $end = match ($period) {
                    'day' => now()->endOfDay(),
                    'week' => now()->endOfWeek(Carbon::SUNDAY),
                    'month' => now()->endOfMonth(),
                    'year' => now()->endOfYear(),
                };
                $query->whereBetween('created_at', [$start, $end]);
            })
            ->when($filters['date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', $date))
            ->when($filters['month'] ?? null, fn (Builder $query, int $month) => $query->whereMonth('created_at', $month))
            ->when($filters['year'] ?? null, fn (Builder $query, int $year) => $query->whereYear('created_at', $year))
            ->when(trim($filters['search'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $search = trim($filters['search']);
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('invoice', 'like', "%{$search}%")
                        ->orWhere('customer_type', 'like', "%{$search}%")
                        ->orWhere('customer_contact', 'like', "%{$search}%")
                        ->orWhere('payment_method', 'like', "%{$search}%")
                        ->orWhereHas('items', fn (Builder $items) => $items->where('product_name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return response()->json($query);
    }

    public function report(Request $request): JsonResponse
    {
        [$start, $end, $periodLabel] = $this->reportPeriod($request);
        $sales = $this->reportSales($request, $start, $end);
        $cashierSales = Sale::query()
            ->when($request->user()->role === 'kasir', fn (Builder $query) => $query->where('cashier_id', $request->user()->id));

        return response()->json([
            'period' => $periodLabel,
            'start' => $start->toDateTimeString(),
            'end' => $end->toDateTimeString(),
            'total_sales' => (clone $sales)->sum('total'),
            'transaction_count' => (clone $sales)->count(),
            'today_sales' => (clone $cashierSales)->whereDate('created_at', today())->sum('total'),
            'month_sales' => (clone $cashierSales)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total'),
            'lifetime_sales' => (clone $cashierSales)->sum('total'),
            'transactions' => $sales->with('customer')->latest()->paginate(25)->withQueryString(),
        ]);
    }

    public function customers(): JsonResponse
    {
        $customers = Customer::query()->with('nfcCard')->latest()->paginate(25);

        return response()->json($customers);
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $request->merge([
            'card_uid' => strtoupper((string) preg_replace('/\s+/', '', trim((string) $request->input('card_uid', '')))),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'card_uid' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9:_-]+$/', 'unique:nfc_cards,uid'],
        ]);
        $customer = DB::transaction(function () use ($data): Customer {
            $customer = Customer::query()->create([
                'name' => trim($data['name']),
                'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            ]);
            $customer->nfcCard()->create(['uid' => $data['card_uid'], 'balance' => 0]);

            return $customer->load('nfcCard');
        });

        return response()->json(['data' => $customer], 201);
    }

    public function topUp(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999999.99'],
            'payment_method' => ['required', 'in:tunai,qris,transfer'],
        ]);

        DB::transaction(function () use ($data, $request, $customer): void {
            $card = NfcCard::query()->where('customer_id', $customer->id)->lockForUpdate()->first();
            if (! $card) {
                throw ValidationException::withMessages(['customer' => 'Pelanggan ini belum memiliki kartu NFC.']);
            }

            $amount = round((float) $data['amount'], 2);
            if ((float) $card->balance + $amount > 9999999999.99) {
                throw ValidationException::withMessages(['amount' => 'Saldo kartu melebihi batas maksimum.']);
            }

            $card->increment('balance', $amount);
            NfcCardTransaction::query()->create([
                'nfc_card_id' => $card->id,
                'cashier_id' => $request->user()->id,
                'type' => 'top_up',
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
            ]);
        });

        return response()->json(['data' => $customer->fresh()->load('nfcCard')]);
    }

    public function cashiers(): JsonResponse
    {
        return response()->json([
            'data' => User::query()->whereIn('role', ['admin', 'kasir'])->orderBy('name')->get(['id', 'name', 'email', 'role']),
        ]);
    }

    public function storeCashier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);
        $user = User::query()->create([
            ...$data,
            'password' => Hash::make($data['password']),
            'role' => 'kasir',
            'email_verified_at' => now(),
        ]);

        return response()->json(['data' => $user->only(['id', 'name', 'email', 'role'])], 201);
    }

    public function settings(): JsonResponse
    {
        return response()->json([
            'store' => StoreSetting::current()->append('logo_url'),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:150'],
            'store_address' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $settings = StoreSetting::current();
        $previous = $settings->logo_path;
        if ($request->hasFile('logo')) {
            $settings->logo_path = $request->file('logo')->store('store-logos', 'public');
        }
        $settings->store_name = $data['store_name'];
        $settings->store_address = $data['store_address'];
        $settings->save();
        if ($previous && $previous !== $settings->logo_path) {
            Storage::disk('public')->delete($previous);
        }

        return response()->json(['data' => $settings->fresh()->append('logo_url')]);
    }

    public function members(): JsonResponse
    {
        return response()->json([
            'data' => Customer::query()
                ->with(['nfcCard.transactions' => fn (Builder $query) => $query->latest()->limit(1)])
                ->latest()
                ->paginate(25),
            'member_count' => Customer::query()->count(),
            'total_balance' => NfcCard::query()->sum('balance'),
        ]);
    }

    public function updateAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        $emailChanged = strtolower((string) $request->input('email')) !== $user->email;
        $passwordChanged = filled($request->input('password'));
        $data = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => [Rule::requiredIf($emailChanged || $passwordChanged), 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);
        $user->email = $data['email'];
        if ($passwordChanged) {
            $user->password = $data['password'];
        }
        $user->save();

        return response()->json(['data' => $user->only(['id', 'name', 'email', 'role'])]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $emailChanged = strtolower((string) $request->input('email')) !== $user->email;
        $passwordChanged = filled($request->input('password'));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => [Rule::requiredIf($emailChanged || $passwordChanged), 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);
        $user->name = $data['name'];
        $user->email = $data['email'];
        if ($passwordChanged) {
            $user->password = $data['password'];
        }
        $user->save();

        return response()->json(['data' => $user->only(['id', 'name', 'email', 'role'])]);
    }

    private function validateProduct(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function productData(Product $product): array
    {
        return [
            ...$product->toArray(),
            'image_url' => $product->image ? Storage::disk('public')->url($product->image) : null,
        ];
    }

    private function reportPeriod(Request $request): array
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
        $year = (int) ($filters['year'] ?? now()->year);
        if ($period === 'day') {
            $start = Carbon::parse($filters['date'] ?? today()->toDateString())->startOfDay();
            return [$start, $start->copy()->endOfDay(), $start->format('d/m/Y')];
        }
        if ($period === 'week') {
            $week = (int) ($filters['week'] ?? now()->format('W'));
            $start = Carbon::now()->setISODate($year, $week, 1)->startOfDay();
            if ((int) $start->format('o') !== $year) {
                throw ValidationException::withMessages(['week' => 'Minggu yang dipilih tidak tersedia pada tahun tersebut.']);
            }

            $end = $start->copy()->endOfWeek(Carbon::SUNDAY);
            return [$start, $end, sprintf('Minggu ke-%d (%s–%s)', $week, $start->format('d/m'), $end->format('d/m/Y'))];
        }

        $month = (int) ($filters['month'] ?? now()->month);
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $monthNames = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return [$start, $start->copy()->endOfMonth()->endOfDay(), $monthNames[$month].' '.$year];
    }

    private function reportSales(Request $request, Carbon $start, Carbon $end): Builder
    {
        return Sale::query()
            ->when($request->user()->role === 'kasir', fn (Builder $query) => $query->where('cashier_id', $request->user()->id))
            ->whereBetween('created_at', [$start, $end]);
    }
}
