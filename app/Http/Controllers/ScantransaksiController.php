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
            'nota' => 'required|image|mimes:jpg,jpeg,png',
            'catatan' => 'nullable|string'
        ]);

        $storedPath  = $request->file('nota')->store('notas', 'public');
        $filePath    = $request->file('nota')->getRealPath();
        $mimeType    = $request->file('nota')->getMimeType();
        $base64Image = base64_encode(file_get_contents($filePath));

        // 🔹 Panggil Gemini API
        $response = Http::post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . env('GEMINI_API_KEY'),
            [
                "contents" => [[
                    "parts" => [
                        [
                            "text" => "Kamu adalah sistem OCR untuk struk belanja.
                        Ekstrak detail dari gambar ini dan kembalikan HANYA JSON valid.
                        Format:
                        {
                          \"total\": number,
                          \"items\": [
                            {\"nama\": string, \"qty\": number, \"harga\": number}
                          ]
                        }"
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
                'message' => 'Gagal memproses OCR.'
            ], 500);
        }

        $raw = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        $raw = preg_replace('/```(json)?/i', '', $raw);
        $raw = trim(str_replace('```', '', $raw));

        preg_match('/\{.*\}/s', $raw, $matches);
        $aiData = json_decode($matches[0] ?? '{}', true);

        if (!$aiData) {
            $aiData = ["total" => 0, "items" => []];
        }

        // 🔹 Koreksi harga kecil
        foreach ($aiData['items'] ?? [] as &$item) {
            if (($item['harga'] ?? 0) < 1000 && ($item['harga'] ?? 0) > 0) {
                $item['harga'] *= 1000;
            }
        }

        // 🔹 Deteksi kategori otomatis
        $kategori_id = 3;
        $itemsText = strtolower(json_encode($aiData['items'] ?? []));

        if (preg_match('/(nasi|ayam|kopi|coffee|teh|minum|makan|warung|resto|es)/i', $itemsText)) {
            $kategori_id = 1;
        } elseif (preg_match('/(pertalite|pertamax|bensin|bbm|spbu|fuel)/i', $itemsText)) {
            $kategori_id = 2;
        }

        // 🔹 Judul otomatis
        $judul = $aiData['items'][0]['nama'] ?? 'Scan Nota';

        try {
            DB::beginTransaction();

            // 🔹 Simpan pengeluaran
            $nota = Pengeluaran::create([
                'user_id' => $request->user()->id,
                'kategori_id' => $kategori_id,
                'filename' => $storedPath,
                'tanggal'  => now()->format('Y-m-d'),
                'total'    => $aiData['total'] ?? 0,
                'judul'    => $judul,
                'catatan'  => $request->catatan,
            ]);

            // 🔹 Simpan item
            foreach ($aiData['items'] ?? [] as $item) {
                PengeluaranItem::create([
                    'pengeluaran_id' => $nota->id,
                    'nama'  => $item['nama'] ?? '-',
                    'qty'   => $item['qty'] ?? 1,
                    'harga' => $item['harga'] ?? 0,
                ]);
            }

            // 🔽 KURANGI sisa_gaji (bukan gaji_bulanan)
            $user = $request->user();

            // fallback user lama
            if (is_null($user->sisa_gaji)) {
                $user->sisa_gaji = (int) $user->gaji_bulanan;
            }

            $totalPengeluaran = (int) ($aiData['total'] ?? 0);
            $user->sisa_gaji = max(0, $user->sisa_gaji - $totalPengeluaran);
            $user->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Scan nota berhasil & sisa_gaji dikurangi.',
                'nota' => $nota->load('items'),
                'sisa_gaji' => $user->sisa_gaji,
                'kategori_id' => $kategori_id
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
