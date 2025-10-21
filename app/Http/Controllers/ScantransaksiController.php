<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pengeluaran;
use App\Models\PengeluaranItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ScantransaksiController extends Controller
{
    public function scanNota(Request $request)
    {
        $request->validate([
            'nota' => 'required|image|mimes:jpg,jpeg,png'
        ]);

        $storedPath  = $request->file('nota')->store('notas', 'public');
        $filePath    = $request->file('nota')->getRealPath();
        $mimeType    = $request->file('nota')->getMimeType();
        $base64Image = base64_encode(file_get_contents($filePath));

        // 🔹 Panggil Gemini API
        $response = Http::post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . env('GEMINI_API_KEY'),
            [
                "contents" => [[
                    "parts" => [
                        [
                            "text" => "Kamu adalah sistem OCR untuk struk belanja.
                            Ekstrak detail dari gambar ini dan kembalikan HANYA JSON valid (tanpa teks tambahan).
                            Format:
                            {
                              \"total\": number,
                              \"items\": [
                                {\"nama\": string, \"qty\": number, \"harga\": number}
                              ]
                            }

                            Aturan:
                            - Semua angka harga gunakan format ribuan Indonesia (contoh: 53.050 → 53050).
                            - Jangan ubah titik ribuan menjadi koma desimal.
                            - Ambil nilai TOTAL/Jumlah Bayar di baris paling bawah struk untuk field 'total'.
                            - Hanya ambil item utama yang memiliki harga.
                            - Abaikan sub-item/bawaan paket (seperti rice, egg, chili sauce) yang harganya 0.
                            - Jangan tulis penjelasan apapun selain JSON."
                        ],
                        [
                            "inline_data" => [
                                "mime_type" => $mimeType,
                                "data" => $base64Image
                            ]
                        ]
                    ]
                ]]
            ]
        );

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses OCR. Silakan coba lagi.'
            ], 500);
        }

        $result = $response->json();
        $raw    = $result['candidates'][0]['content']['parts'][0]['text'] ?? '{}';

        $raw = preg_replace('/```(json)?/i', '', $raw);
        $raw = str_replace('```', '', $raw);
        $raw = trim($raw);

        if (preg_match('/\{.*\}/s', $raw, $matches)) {
            $jsonText = $matches[0];
        } else {
            $jsonText = '{}';
        }

        $aiData = json_decode($jsonText, true);
        if (!$aiData) {
            \Log::error("Gemini JSON parse gagal:", [$raw]);
            $aiData = ["total" => 0, "items" => []];
        }

        // Koreksi harga terlalu kecil (misal hasil OCR 53 → jadi 53000)
        foreach ($aiData['items'] ?? [] as &$item) {
            if (($item['harga'] ?? 0) < 1000 && ($item['harga'] ?? 0) > 0) {
                $item['harga'] = $item['harga'] * 1000;
            }
        }

        try {
            DB::beginTransaction();

            // 🔹 Simpan ke tabel nota
            $nota = Pengeluaran::create([
                'user_id' => $request->user()->id,
                'filename' => $storedPath,
                'tanggal'  => now()->format('Y-m-d'),
                'total'    => $aiData['total'] ?? 0,
            ]);

            // 🔹 Simpan item nota
            foreach ($aiData['items'] ?? [] as $item) {
                PengeluaranItem::create([
                    'nota_id' => $nota->id,
                    'nama'    => $item['nama'] ?? '-',
                    'qty'     => $item['qty'] ?? 1,
                    'harga'   => $item['harga'] ?? 0,
                ]);
            }

            // 🔽 Kurangi gaji user sesuai total pengeluaran
            $user = $request->user();
            $totalPengeluaran = $aiData['total'] ?? 0;
            $sisaGaji = $user->gaji_bulanan - $totalPengeluaran;

            if ($sisaGaji < 0) $sisaGaji = 0;

            $user->update(['gaji_bulanan' => $sisaGaji]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Scan nota berhasil, data disimpan dan gaji dikurangi.',
                'nota' => $nota->load('items'),
                'sisa_gaji' => $sisaGaji,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan hasil scan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
