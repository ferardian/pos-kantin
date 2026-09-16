<?php

namespace App\Http\Controllers;

use App\Services\EmployeeService;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index()
    {
        return redirect()->route('receivables.index');
    }

    public function sync()
    {
        $result = EmployeeService::syncFromApi();
        if ($result['success']) {
            return back()->with('success', $result['message']);
        }
        return back()->with('error', $result['message']);
    }

    public function store(Request $request)
    {
        return back()->with('error', 'Master data pegawai tersinkronisasi otomatis dari RSIA API.');
    }

    public function update(Request $request)
    {
        return back()->with('error', 'Master data pegawai tersinkronisasi otomatis dari RSIA API.');
    }

    public function destroy()
    {
        return back()->with('error', 'Master data pegawai tersinkronisasi otomatis dari RSIA API.');
    }

    public function apiList()
    {
        return response()->json(EmployeeService::getActiveEmployees());
    }
}
