<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pengeluaran;
use App\Models\PengeluaranItem;
use Illuminate\Support\Facades\DB;

class TransaksiController extends Controller
{
    // ✅ Tambah transaksi baru
    public function storeTransaksi(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'total' => 'required|integer|min:0',
            'kategori_id' => 'required|exists:kategori_pengeluarans,id',
            'catatan' => 'nullable|string', // ⬅️ DITAMBAH
            'items' => 'nullable|array',
            'items.*.nama' => 'required_with:items|string|max:255',
            'items.*.qty' => 'required_with:items|integer|min:1',
            'items.*.harga' => 'required_with:items|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Simpan pengeluaran utama
            $pengeluaran = Pengeluaran::create([
                'user_id' => $request->user()->id,
                'kategori_id' => $request->kategori_id,
                'filename' => '', // kosong karena input manual
                'tanggal' => $request->tanggal,
                'total' => $request->total,
                'catatan' => $request->catatan, // ⬅️ DITAMBAH
            ]);

            // Simpan item jika ada
            if ($request->has('items')) {
                foreach ($request->items as $item) {
                    PengeluaranItem::create([
                        'pengeluaran_id' => $pengeluaran->id,
                        'nama' => $item['nama'],
                        'qty' => $item['qty'],
                        'harga' => $item['harga'],
                    ]);
                }
            }

            // 🔽 Kurangi gaji bulanan user
            $user = $request->user();
            $sisaGaji = $user->gaji_bulanan - $request->total;

            if ($sisaGaji < 0) $sisaGaji = 0;
            $user->update(['gaji_bulanan' => $sisaGaji]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil disimpan dan gaji bulanan dikurangi.',
                'pengeluaran' => $pengeluaran->load('items', 'kategori'),
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
            'kategori_id' => 'nullable|exists:kategori_pengeluarans,id',
            'catatan' => 'nullable|string', // ⬅️ DITAMBAH
        ]);

        $pengeluaran = Pengeluaran::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$pengeluaran) {
            return response()->json(['success' => false, 'message' => 'Transaksi tidak ditemukan.'], 404);
        }

        $pengeluaran->update($request->only(['tanggal', 'total', 'kategori_id', 'catatan'])); // ⬅️ `catatan` ditambah

        return response()->json(['success' => true, 'pengeluaran' => $pengeluaran]);
    }

    // ✅ Hapus transaksi milik user
    public function deleteTransaksi(Request $request, $id)
    {
        $pengeluaran = Pengeluaran::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$pengeluaran) {
            return response()->json(['success' => false, 'message' => 'Transaksi tidak ditemukan.'], 404);
        }

        $pengeluaran->delete();

        return response()->json(['success' => true, 'message' => 'Transaksi berhasil dihapus.']);
    }

    // ✅ Ambil semua transaksi milik user login
    public function getTransaksi(Request $request)
    {
        $pengeluarans = Pengeluaran::with(['items', 'kategori'])
            ->where('user_id', $request->user()->id)
            ->orderBy('tanggal', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'pengeluarans' => $pengeluarans,
        ]);
    }
}
