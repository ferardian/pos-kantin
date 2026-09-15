<?php

namespace App\Http\Controllers;

use App\Models\Cashbox;
use App\Models\CashTransaction;
use App\Models\EmployeeReceivable;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CashboxController extends Controller
{
    public function index(Request $request)
    {
        $cashboxes = Cashbox::withCount('transactions')->get();
        $totalCashboxesCount = $cashboxes->count();
        $totalCashBalance = (float) $cashboxes->sum('balance');

        $totalCashIn = (float) CashTransaction::where('type', 'in')->sum('amount');
        $totalCashOut = (float) CashTransaction::where('type', 'out')->sum('amount');

        // Nilai Aset Produk (HPP Total Persediaan di Toko & Gudang)
        $inventoryValuation = 0;
        $products = Product::with('baseUnit')->get();
        foreach ($products as $p) {
            $cost = $p->baseUnit ? (float)$p->baseUnit->cost_price : 0;
            $inventoryValuation += ((float)$p->stock_physical * $cost);
        }

        // Total Piutang Penjualan Pelanggan
        $totalDebts = (float) EmployeeReceivable::whereIn('status', ['unpaid', 'partial'])->sum('remaining');

        // Total Nilai Usaha / Net Worth (Kas + Nilai Stok + Piutang)
        $totalBusinessNetWorth = $totalCashBalance + $inventoryValuation + $totalDebts;

        // Riwayat Transaksi Kas
        $cashboxId = $request->query('cashbox_id', 'all');
        $type = $request->query('type', 'all');

        $query = CashTransaction::with(['cashbox', 'user'])->latest();

        if ($cashboxId !== 'all') {
            $query->where('cashbox_id', $cashboxId);
        }

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        $transactions = $query->take(100)->get();

        return Inertia::render('Cashboxes/Index', [
            'cashboxes' => $cashboxes,
            'totalCashboxesCount' => $totalCashboxesCount,
            'totalCashBalance' => $totalCashBalance,
            'totalCashIn' => $totalCashIn,
            'totalCashOut' => $totalCashOut,
            'inventoryValuation' => $inventoryValuation,
            'totalDebts' => $totalDebts,
            'totalBusinessNetWorth' => $totalBusinessNetWorth,
            'transactions' => $transactions,
            'user' => Auth::user(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:cash,bank,other',
            'account_number' => 'nullable|string|max:100',
            'initial_balance' => 'nullable|numeric|min:0',
        ]);

        $initialBalance = (float)($validated['initial_balance'] ?? 0);

        return DB::transaction(function () use ($validated, $initialBalance) {
            $cashbox = Cashbox::create([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'account_number' => $validated['account_number'] ?? null,
                'balance' => $initialBalance,
                'is_default' => false,
            ]);

            if ($initialBalance > 0) {
                CashTransaction::create([
                    'cashbox_id' => $cashbox->id,
                    'user_id' => Auth::id(),
                    'type' => 'in',
                    'category' => 'Modal Awal Kas',
                    'amount' => $initialBalance,
                    'transaction_date' => now()->toDateString(),
                    'reference_type' => 'initial_balance',
                    'description' => "Saldo awal pembuatan akun kas {$cashbox->name}",
                ]);
            }

            return back()->with('success', "Akun kas '{$cashbox->name}' berhasil ditambahkan.");
        });
    }

    public function storeTransaction(Request $request)
    {
        $validated = $request->validate([
            'cashbox_id' => 'required|exists:cashboxes,id',
            'type' => 'required|in:in,out',
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:1',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($validated) {
            $cashbox = Cashbox::findOrFail($validated['cashbox_id']);
            $amount = (float)$validated['amount'];

            if ($validated['type'] === 'out') {
                $cashbox->decrement('balance', $amount);
            } else {
                $cashbox->increment('balance', $amount);
            }

            CashTransaction::create([
                'cashbox_id' => $cashbox->id,
                'user_id' => Auth::id(),
                'type' => $validated['type'],
                'category' => $validated['category'],
                'amount' => $amount,
                'transaction_date' => $validated['transaction_date'],
                'reference_type' => 'manual',
                'description' => $validated['description'] ?? null,
            ]);

            $label = $validated['type'] === 'out' ? 'Pengeluaran kas' : 'Pemasukan kas';
            return back()->with('success', "{$label} sebesar Rp " . number_format($amount, 0, ',', '.') . " berhasil dicatat.");
        });
    }

    public function transferBalance(Request $request)
    {
        $validated = $request->validate([
            'from_cashbox_id' => 'required|exists:cashboxes,id',
            'to_cashbox_id' => 'required|exists:cashboxes,id|different:from_cashbox_id',
            'amount' => 'required|numeric|min:1',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($validated) {
            $fromCashbox = Cashbox::findOrFail($validated['from_cashbox_id']);
            $toCashbox = Cashbox::findOrFail($validated['to_cashbox_id']);
            $amount = (float)$validated['amount'];

            if ($fromCashbox->balance < $amount) {
                return back()->withErrors(['amount' => 'Saldo di kas asal tidak mencukupi untuk transfer.']);
            }

            $fromCashbox->decrement('balance', $amount);
            $toCashbox->increment('balance', $amount);

            $desc = $validated['description'] ?? "Transfer kas dari {$fromCashbox->name} ke {$toCashbox->name}";

            CashTransaction::create([
                'cashbox_id' => $fromCashbox->id,
                'user_id' => Auth::id(),
                'type' => 'out',
                'category' => 'Transfer Antar Kas',
                'amount' => $amount,
                'transaction_date' => $validated['transaction_date'],
                'reference_type' => 'transfer',
                'description' => $desc,
            ]);

            CashTransaction::create([
                'cashbox_id' => $toCashbox->id,
                'user_id' => Auth::id(),
                'type' => 'in',
                'category' => 'Transfer Antar Kas',
                'amount' => $amount,
                'transaction_date' => $validated['transaction_date'],
                'reference_type' => 'transfer',
                'description' => $desc,
            ]);

            return back()->with('success', "Transfer kas sebesar Rp " . number_format($amount, 0, ',', '.') . " berhasil diproses.");
        });
    }
}
