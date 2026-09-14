<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Debt;
use App\Models\DebtPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::with(['debts' => function ($q) {
            $q->where('status', '!=', 'paid');
        }])->get();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'user' => Auth::user(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'tier' => 'nullable|in:eceran,tukang,kontraktor,grosir',
            'credit_limit' => 'nullable|numeric|min:0',
        ]);

        $validated['tier'] = $validated['tier'] ?? 'eceran';
        $validated['credit_limit'] = $validated['credit_limit'] ?? 0;

        $customer = Customer::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'customer' => $customer,
                'message' => 'Pelanggan baru berhasil ditambahkan.',
            ]);
        }

        return back()->with('success', 'Pelanggan baru berhasil ditambahkan.');
    }

    public function payDebt(Request $request, $debtId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:cash,transfer',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($debtId, $validated) {
            $debt = Debt::with('customer')->findOrFail($debtId);
            $user = Auth::user();
            $payAmount = min($debt->remaining_debt, $validated['amount']);

            DebtPayment::create([
                'debt_id' => $debt->id,
                'collector_id' => $user->id,
                'amount' => $payAmount,
                'payment_method' => $validated['payment_method'],
                'payment_date' => now()->toDateString(),
                'notes' => $validated['notes'] ?? null,
            ]);

            $debt->remaining_debt -= $payAmount;
            if ($debt->remaining_debt <= 0) {
                $debt->status = 'paid';
                $debt->remaining_debt = 0;
            } else {
                $debt->status = 'partial';
            }
            $debt->save();

            // Update customer debt total
            $debt->customer->decrement('current_debt', $payAmount);

            // Record in Cashbox
            $defaultCashbox = \App\Models\Cashbox::where('is_default', true)->first() ?? \App\Models\Cashbox::first();
            if ($defaultCashbox) {
                $defaultCashbox->increment('balance', $payAmount);
                \App\Models\CashTransaction::create([
                    'cashbox_id' => $defaultCashbox->id,
                    'user_id' => $user->id,
                    'type' => 'in',
                    'category' => 'Pelunasan Piutang Pelanggan',
                    'amount' => $payAmount,
                    'transaction_date' => now()->toDateString(),
                    'reference_type' => 'debt_payment',
                    'reference_id' => $debt->id,
                    'description' => "Pelunasan Piutang: {$debt->customer->name} (" . strtoupper($validated['payment_method']) . ")",
                ]);
            }

            return back()->with('success', 'Pembayaran piutang berhasil dicatat dan masuk ke buku kas.');
        });
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        if ($request->has('tier')) {
            $request->merge(['tier' => strtolower(trim((string) $request->input('tier')))]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'tier' => 'required|in:eceran,tukang,kontraktor,grosir',
            'credit_limit' => 'nullable|numeric|min:0',
        ]);

        $validated['credit_limit'] = $validated['credit_limit'] ?? 0;

        $customer->update($validated);

        return back()->with('success', "Data pelanggan {$customer->name} berhasil diperbarui.");
    }

    public function adjustDebt(Request $request, $id)
    {
        $validated = $request->validate([
            'type' => 'required|in:add,deduct,set',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($id, $validated) {
            $customer = Customer::findOrFail($id);
            $user = Auth::user();
            $amount = (float) $validated['amount'];
            $type = $validated['type'];
            $dueDate = $validated['due_date'] ?? now()->addDays(14)->toDateString();
            $notes = $validated['notes'] ?? 'Penyesuaian piutang manual';

            if ($type === 'add') {
                Debt::create([
                    'customer_id' => $customer->id,
                    'transaction_id' => null,
                    'total_debt' => $amount,
                    'remaining_debt' => $amount,
                    'due_date' => $dueDate,
                    'status' => 'unpaid',
                ]);
                $customer->increment('current_debt', $amount);
                $msg = "Piutang sebesar " . number_format($amount, 0, ',', '.') . " berhasil ditambahkan ke {$customer->name}.";
            } elseif ($type === 'deduct') {
                $deductRemaining = $amount;
                $activeDebts = Debt::where('customer_id', $customer->id)
                    ->where('status', '!=', 'paid')
                    ->orderBy('created_at', 'asc')
                    ->get();

                foreach ($activeDebts as $d) {
                    if ($deductRemaining <= 0) break;
                    $pay = min($d->remaining_debt, $deductRemaining);
                    $d->remaining_debt -= $pay;
                    if ($d->remaining_debt <= 0) {
                        $d->status = 'paid';
                        $d->remaining_debt = 0;
                    } else {
                        $d->status = 'partial';
                    }
                    $d->save();

                    DebtPayment::create([
                        'debt_id' => $d->id,
                        'collector_id' => $user->id,
                        'amount' => $pay,
                        'payment_method' => 'cash',
                        'payment_date' => now()->toDateString(),
                        'notes' => "Koreksi/Pengurangan manual: {$notes}",
                    ]);

                    $deductRemaining -= $pay;
                }

                $effectiveDeduct = min($customer->current_debt, $amount);
                $customer->decrement('current_debt', $effectiveDeduct);
                $msg = "Piutang {$customer->name} berhasil dikurangi sebesar " . number_format($effectiveDeduct, 0, ',', '.') . ".";
            } else {
                $oldDebt = $customer->current_debt;
                $diff = $amount - $oldDebt;
                $customer->current_debt = $amount;
                $customer->save();

                if ($diff > 0) {
                    Debt::create([
                        'customer_id' => $customer->id,
                        'transaction_id' => null,
                        'total_debt' => $diff,
                        'remaining_debt' => $diff,
                        'due_date' => $dueDate,
                        'status' => 'unpaid',
                    ]);
                } elseif ($diff < 0) {
                    $deductRemaining = abs($diff);
                    $activeDebts = Debt::where('customer_id', $customer->id)
                        ->where('status', '!=', 'paid')
                        ->orderBy('created_at', 'asc')
                        ->get();
                    foreach ($activeDebts as $d) {
                        if ($deductRemaining <= 0) break;
                        $pay = min($d->remaining_debt, $deductRemaining);
                        $d->remaining_debt -= $pay;
                        if ($d->remaining_debt <= 0) {
                            $d->status = 'paid';
                            $d->remaining_debt = 0;
                        } else {
                            $d->status = 'partial';
                        }
                        $d->save();
                        $deductRemaining -= $pay;
                    }
                }
                $msg = "Total piutang {$customer->name} berhasil disesuaikan menjadi " . number_format($amount, 0, ',', '.') . ".";
            }

            return back()->with('success', $msg);
        });
    }

    public function destroy($id)
    {
        $customer = Customer::findOrFail($id);

        if ($customer->name === 'Pelanggan Umum') {
            return back()->with('error', 'Pelanggan Umum utama toko adalah akun sistem kasir dan tidak dapat dihapus.');
        }

        if ($customer->current_debt > 0) {
            $formattedDebt = number_format($customer->current_debt, 0, ',', '.');
            return back()->with('error', "Pelanggan {$customer->name} masih memiliki sisa piutang berjalan sebesar Rp {$formattedDebt}. Harap lunaskan atau sesuaikan piutang ke Rp 0 terlebih dahulu.");
        }

        try {
            $customerName = $customer->name;
            $customer->delete();
            return back()->with('success', "Pelanggan {$customerName} berhasil dihapus dari sistem.");
        } catch (\Exception $e) {
            return back()->with('error', "Gagal menghapus pelanggan: " . $e->getMessage());
        }
    }
}
