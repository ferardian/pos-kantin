<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeReceivable;
use App\Models\ReceivablePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EmployeeReceivableController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeReceivable::with(['employee', 'transaction', 'payments.cashier'])
            ->orderBy('created_at', 'desc');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        $receivables = $query->get();

        $summary = [
            'total_unpaid'   => EmployeeReceivable::whereIn('status', ['unpaid', 'partial'])->sum('remaining'),
            'total_employees_with_debt' => EmployeeReceivable::whereIn('status', ['unpaid', 'partial'])
                ->distinct('employee_id')->count('employee_id'),
        ];

        return Inertia::render('Receivables/Index', [
            'receivables' => $receivables,
            'employees'   => \App\Services\EmployeeService::getActiveEmployees(),
            'summary'     => $summary,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'    => 'required|exists:employees,id',
            'transaction_id' => 'nullable|exists:transactions,id',
            'amount'         => 'required|numeric|min:1',
            'notes'          => 'nullable|string',
        ]);

        EmployeeReceivable::create([
            'employee_id'    => $data['employee_id'],
            'transaction_id' => $data['transaction_id'] ?? null,
            'amount'         => $data['amount'],
            'remaining'      => $data['amount'],
            'notes'          => $data['notes'] ?? null,
            'status'         => 'unpaid',
        ]);

        return back()->with('success', 'Piutang karyawan berhasil dicatat.');
    }

    public function pay(Request $request, EmployeeReceivable $receivable)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:' . $receivable->remaining,
            'notes'  => 'nullable|string',
        ]);

        DB::transaction(function () use ($receivable, $data, $request) {
            ReceivablePayment::create([
                'employee_receivable_id' => $receivable->id,
                'cashier_id'             => $request->user()->id,
                'amount'                 => $data['amount'],
                'payment_date'           => now()->toDateString(),
                'notes'                  => $data['notes'] ?? null,
            ]);

            $newRemaining = $receivable->remaining - $data['amount'];
            $receivable->update([
                'remaining' => $newRemaining,
                'status'    => $newRemaining <= 0 ? 'paid' : 'partial',
            ]);
        });

        return back()->with('success', 'Pelunasan piutang berhasil dicatat.');
    }
}
