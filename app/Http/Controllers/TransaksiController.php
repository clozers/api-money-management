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
            'tanggal' => 'nullable|date',
            'total' => 'required|integer|min:0',
            'kategori_id' => 'required|exists:kategori_pengeluarans,id',
            'judul' => 'nullable|string|max:255', // ⬅️ TAMBAH
            'catatan' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.nama' => 'required_with:items|string|max:255',
            'items.*.qty' => 'required_with:items|integer|min:1',
            'items.*.harga' => 'required_with:items|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            $tanggal = $request->tanggal ?? now()->format('Y-m-d');

            // Simpan pengeluaran utama
            $pengeluaran = Pengeluaran::create([
                'user_id' => $request->user()->id,
                'kategori_id' => $request->kategori_id,
                'filename' => '',
                'tanggal' => $tanggal,
                'total' => $request->total,
                'judul' => $request->judul, // ⬅️ TAMBAH
                'catatan' => $request->catatan,
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

            // Kurangi gaji bulanan user
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

    // ✅ Update transaksi milik user (dengan update item)
    public function updateTransaksi(Request $request, $id)
    {
        $request->validate([
            'tanggal' => 'nullable|date',
            'total' => 'nullable|integer|min:0',
            'kategori_id' => 'nullable|exists:kategori_pengeluarans,id',
            'judul' => 'nullable|string|max:255',
            'catatan' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.id' => 'nullable|integer|exists:pengeluaran_items,id',
            'items.*.nama' => 'required_with:items|string|max:255',
            'items.*.qty' => 'required_with:items|integer|min:1',
            'items.*.harga' => 'required_with:items|integer|min:0',
        ]);

        $pengeluaran = Pengeluaran::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$pengeluaran) {
            return response()->json(['success' => false, 'message' => 'Transaksi tidak ditemukan.'], 404);
        }

        try {
            DB::beginTransaction();

            $oldTotal = (int) $pengeluaran->total;

            // Jika ada items di-request, kita proses items:
            if ($request->has('items')) {
                $incomingItems = $request->items;
                $processedIds = [];

                // Hitung total baru berdasarkan items (kebijakan: total dihitung dari items)
                $calculatedTotal = 0;
                foreach ($incomingItems as $it) {
                    $calculatedTotal += ((int)$it['qty']) * ((int)$it['harga']);
                }

                // Update / create items
                $existingItems = PengeluaranItem::where('pengeluaran_id', $pengeluaran->id)->get()->keyBy('id');

                foreach ($incomingItems as $it) {
                    if (isset($it['id']) && $it['id'] && $existingItems->has($it['id'])) {
                        // update existing
                        $itemModel = $existingItems->get($it['id']);
                        $itemModel->update([
                            'nama' => $it['nama'],
                            'qty' => $it['qty'],
                            'harga' => $it['harga'],
                        ]);
                        $processedIds[] = $itemModel->id;
                    } else {
                        // create new
                        $newItem = PengeluaranItem::create([
                            'pengeluaran_id' => $pengeluaran->id,
                            'nama' => $it['nama'],
                            'qty' => $it['qty'],
                            'harga' => $it['harga'],
                        ]);
                        $processedIds[] = $newItem->id;
                    }
                }

                // Hapus item yang tidak dikirim lagi (dianggap dihapus oleh user)
                $toDelete = $existingItems->keys()->filter(function ($existingId) use ($processedIds) {
                    return !in_array($existingId, $processedIds);
                })->all();

                if (!empty($toDelete)) {
                    PengeluaranItem::whereIn('id', $toDelete)->delete();
                }

                // Set total baru berdasarkan perhitungan items
                $newTotal = $calculatedTotal;
            } else {
                // Jika tidak ada items yang dikirim, gunakan total dari request jika ada, atau tetap total lama
                $newTotal = $request->has('total') ? (int)$request->total : (int)$pengeluaran->total;
            }

            // Update pengeluaran (tanggal, kategori, judul, catatan, total)
            $updateData = array_filter([
                'tanggal' => $request->tanggal ?? $pengeluaran->tanggal,
                'total' => $newTotal,
                'kategori_id' => $request->kategori_id ?? $pengeluaran->kategori_id,
                'judul' => $request->judul ?? $pengeluaran->judul,
                'catatan' => $request->catatan ?? $pengeluaran->catatan,
            ], function ($v) { return $v !== null; });

            $pengeluaran->update($updateData);

            // Sesuaikan gaji bulanan user berdasarkan selisih total
            $user = $request->user();
            $delta = $newTotal - $oldTotal; // jika positif -> kurangi gaji, jika negatif -> tambahkan kembali
            $sisaGaji = $user->gaji_bulanan - $delta;

            if ($sisaGaji < 0) $sisaGaji = 0;
            $user->update(['gaji_bulanan' => $sisaGaji]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil diperbarui.',
                'pengeluaran' => $pengeluaran->load('items', 'kategori'),
                'sisa_gaji' => $sisaGaji,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui transaksi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // ❤️ Tidak diubah sama sekali
    public function deleteTransaksi(Request $request, $id)
    {
        $pengeluaran = Pengeluaran::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$pengeluaran) {
            return response()->json(['success' => false, 'message' => 'Transaksi tidak ditemukan.'], 404);
        }

        try {
            DB::beginTransaction();

            $user = $request->user();

            // Kembalikan gaji yang pernah dikurangi
            $user->gaji_bulanan += (int)$pengeluaran->total;
            $user->save();

            // Hapus item dulu (biar rapi meskipun cascading bisa saja)
            $pengeluaran->items()->delete();

            // Hapus transaksi
            $pengeluaran->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil dihapus & gaji dikembalikan.',
                'sisa_gaji' => $user->gaji_bulanan
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus transaksi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // ❤️ Tidak diubah sama sekali
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
