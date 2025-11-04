<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pengeluaran;
use Carbon\Carbon;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Total pengeluaran bulan ini
        $bulanIni = Carbon::now()->format('Y-m');
        $totalPengeluaranBulanIni = Pengeluaran::where('user_id', $user->id)
            ->where('tanggal', 'like', "$bulanIni%")
            ->sum('total');

        // Transaksi terbaru (5 data terakhir)
        $transaksiTerbaru = Pengeluaran::where('user_id', $user->id)
            ->orderBy('tanggal', 'desc')
            ->take(5)
            ->get(['id', 'total', 'tanggal'])
            ->map(function ($trx) {
                return [
                    'id' => $trx->id,
                    'total' => $trx->total,
                    'tanggal' => $trx->tanggal,
                ];
            });

        return response()->json([
            'nama_user' => $user->name,
            'sisa_gaji' => $user->gaji_bulanan,
            'total_pengeluaran_bulan_ini' => $totalPengeluaranBulanIni,
            'transaksi_terbaru' => $transaksiTerbaru,
        ]);
    }
}
