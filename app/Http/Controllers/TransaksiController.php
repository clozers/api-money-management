<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pengeluaran;
use App\Models\PengeluaranItem;
use Illuminate\Support\Facades\DB;

class TransaksiController extends Controller
{
    public function storeTransaksi(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'total' => 'required|integer|min:0',
            'items' => 'nullable|array',
            'items.*.nama' => 'required_with:items|string|max:255',
            'items.*.qty' => 'required_with:items|integer|min:1',
            'items.*.harga' => 'required_with:items|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Simpan nota utama
            $nota = Pengeluaran::create([
                'user_id' => $request->user()->id,
                'filename' => '',
                'tanggal' => $request->tanggal,
                'total' => $request->total,
            ]);

            // Simpan item jika ada
            if ($request->has('items')) {
                foreach ($request->items as $item) {
                    PengeluaranItem::create([
                        'nota_id' => $nota->id,
                        'nama' => $item['nama'],
                        'qty' => $item['qty'],
                        'harga' => $item['harga'],
                    ]);
                }
            }

            // 🔽 Kurangi gaji user
            $user = $request->user();
            $sisaGaji = $user->gaji_bulanan - $request->total;

            // Pastikan tidak minus
            if ($sisaGaji < 0) $sisaGaji = 0;

            $user->update(['gaji_bulanan' => $sisaGaji]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil disimpan dan gaji bulanan dikurangi.',
                'nota' => $nota->load('items'),
                'sisa_gaji' => $sisaGaji,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan transaksi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // ✅ Update transaksi milik user
    public function updateTransaksi(Request $request, $id)
    {
        $request->validate([
            'tanggal' => 'nullable|date',
            'total' => 'nullable|integer|min:0',
        ]);

        $nota = Pengeluaran::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$nota) {
            return response()->json(['success' => false, 'message' => 'Transaksi tidak ditemukan.'], 404);
        }

        $nota->update($request->only(['tanggal', 'total']));

        return response()->json(['success' => true, 'nota' => $nota]);
    }

    // ✅ Hapus transaksi milik user
    public function deleteTransaksi(Request $request, $id)
    {
        $nota = Pengeluaran::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$nota) {
            return response()->json(['success' => false, 'message' => 'Transaksi tidak ditemukan.'], 404);
        }

        $nota->delete();

        return response()->json(['success' => true, 'message' => 'Transaksi berhasil dihapus.']);
    }

    // ✅ Ambil semua nota milik user login
    public function getTransaksi(Request $request)
    {
        $notas = Pengeluaran::with('items')
            ->where('user_id', $request->user()->id)
            ->orderBy('tanggal', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'notas' => $notas,
        ]);
    }
}
