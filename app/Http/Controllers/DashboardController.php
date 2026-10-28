<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(DashboardService $service)
    {
        return view('dashboard.index', [
            'batch' => $service->activeBatch(),
        ]);
    }

    /** Opsi filter, daftar chart, info batch */
    public function meta(DashboardService $service)
    {
        return response()->json($service->meta());
    }

    /** Data satu chart. Query string: year, division, training_type (kosong atau "All" = tanpa filter) */
    public function chart(Request $request, DashboardService $service, int $id)
    {
        $request->validate([
            'year'          => ['nullable', 'regex:/^(all|\d{4})$/i'],
            'division'      => ['nullable', 'string', 'max:100'],
            'training_type' => ['nullable', 'string', 'max:100'],
        ]);

        abort_unless($service->hasChart($id), 404, 'Chart tidak ditemukan.');

        $data = $service->chart($id, $request->only(['year', 'division', 'training_type']));

        if ($data === null) {
            return response()->json(['message' => 'Belum ada data. Import file Excel terlebih dahulu.'], 409);
        }

        return response()->json($data);
    }
}