<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::withSum(['activeReceivables as total_remaining'], 'remaining')
            ->orderBy('name');

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%")
                  ->orWhere('department', 'like', "%{$request->search}%");
        }

        if ($request->has('active')) {
            $query->where('is_active', true);
        }

        $employees = $query->get();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:150',
            'department' => 'nullable|string|max:100',
            'phone'      => 'nullable|string|max:20',
            'is_active'  => 'boolean',
        ]);

        Employee::create($data);

        return back()->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:150',
            'department' => 'nullable|string|max:100',
            'phone'      => 'nullable|string|max:20',
            'is_active'  => 'boolean',
        ]);

        $employee->update($data);

        return back()->with('success', 'Data karyawan diperbarui.');
    }

    public function destroy(Employee $employee)
    {
        if ($employee->activeReceivables()->exists()) {
            return back()->with('error', 'Karyawan masih memiliki piutang yang belum lunas.');
        }

        $employee->delete();

        return back()->with('success', 'Karyawan dihapus.');
    }

    // API: list untuk dropdown di POS
    public function apiList()
    {
        return response()->json(
            Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'department'])
        );
    }
}
