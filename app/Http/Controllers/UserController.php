<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function getUser(Request $request)
    {
        return response()->json($request->user());
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'gaji_bulanan' => 'nullable|integer|min:0',
        ]);

        $user = $request->user();

        try {
            DB::beginTransaction();

            $data = $request->only([
                'name',
                'email',
                'gaji_bulanan',
            ]);

            // Jika gaji_bulanan dikirim, hitung delta dan sesuaikan sisa_gaji
            if (array_key_exists('gaji_bulanan', $data)) {
                $oldGaji = (int) $user->gaji_bulanan;
                $newGaji = (int) $data['gaji_bulanan'];

                // Pastikan sisa_gaji terisi (fallback ke oldGaji bila null)
                if (is_null($user->sisa_gaji)) {
                    $user->sisa_gaji = $oldGaji;
                }

                $delta = $newGaji - $oldGaji; // positif -> tambahkan sisa, negatif -> kurangi sisa
                $user->sisa_gaji = (int)$user->sisa_gaji + $delta;

                // update gaji_bulanan juga
                $user->gaji_bulanan = $newGaji;
            }

            // update name/email kalau ada
            if (isset($data['name'])) $user->name = $data['name'];
            if (isset($data['email'])) $user->email = $data['email'];

            $user->save();

            DB::commit();

            return response()->json([
                'message' => 'User updated successfully',
                'user' => $user,
                'sisa_gaji' => $user->sisa_gaji,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memperbarui user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    // ✅ Reset Sisa Gaji (misal: awal bulan)
    public function resetGaji(Request $request)
    {
        try {
            DB::beginTransaction();

            // Gunakan lockForUpdate biar aman
            $user = \App\Models\User::where('id', $request->user()->id)->lockForUpdate()->first();

            // Reset sisa_gaji ke gaji_bulanan
            // (Opsional: kalau mau fitur "Tabungan", logic-nya beda lagi)
            $user->sisa_gaji = $user->gaji_bulanan;
            $user->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sisa gaji berhasil di-reset ke nilai gaji bulanan awal.',
                'sisa_gaji' => $user->sisa_gaji,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mereset gaji',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
