<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmployeeService
{
    /**
     * Sinkronisasi data pegawai dari RSIA API (Hanya nama dan unit/departemen).
     */
    public static function syncFromApi(): array
    {
        $apiUrl = config('services.rsia.api_url', 'http://localhost:8010/api/v2');
        try {
            $response = Http::timeout(5)->get("{$apiUrl}/pegawai-kantin");
            if ($response->successful()) {
                $items = $response->json('data') ?? [];
                $activeNiks = [];

                foreach ($items as $item) {
                    if (empty($item['nik']) || empty($item['nama'])) {
                        continue;
                    }
                    $activeNiks[] = $item['nik'];

                    Employee::updateOrCreate(
                        ['nik' => $item['nik']],
                        [
                            'name' => $item['nama'],
                            'department' => $item['unit'] ?? '-',
                            'is_active' => true,
                        ]
                    );
                }

                // Non-aktifkan pegawai yang sudah tidak aktif di RSIA API
                if (!empty($activeNiks)) {
                    Employee::whereNotIn('nik', $activeNiks)->update(['is_active' => false]);
                }

                return [
                    'success' => true,
                    'count' => count($activeNiks),
                    'message' => "Berhasil sinkronisasi " . count($activeNiks) . " pegawai dari RSIA API."
                ];
            }

            return [
                'success' => false,
                'count' => 0,
                'message' => "RSIA API merespon dengan status: " . $response->status()
            ];
        } catch (\Throwable $e) {
            Log::warning('Gagal sinkron pegawai dari RSIA API: ' . $e->getMessage());
            return [
                'success' => false,
                'count' => 0,
                'message' => 'Gagal terhubung ke RSIA API: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Dapatkan daftar pegawai aktif (otomatis sinkron jika data lokal masih kosong).
     */
    public static function getActiveEmployees($forceSync = false)
    {
        if ($forceSync || Employee::count() === 0) {
            self::syncFromApi();
        }

        return Employee::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'nik', 'name', 'department']);
    }
}
