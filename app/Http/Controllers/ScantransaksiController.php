<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Nota;
use App\Models\NotaItem;
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

        // 🔹 Kirim ke Gemini API
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

        // 🔹 Ambil hasil dari Gemini
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

        // 🔹 Perbaiki harga (kalau ada yang ribuan salah)
        foreach ($aiData['items'] ?? [] as &$item) {
            if (($item['harga'] ?? 0) < 1000 && ($item['harga'] ?? 0) > 0) {
                $item['harga'] = $item['harga'] * 1000;
            }
        }

        // 🔹 Simpan ke database
        DB::transaction(function () use ($aiData, $storedPath, &$nota, $request) {
            $nota = Nota::create([
                'user_id' => $request->user()->id,
                'filename' => $storedPath,
                'tanggal'  => now()->format('Y-m-d'), // ✅ langsung pakai tanggal saat scan
                'total'    => $aiData['total'] ?? 0,
            ]);

            foreach ($aiData['items'] ?? [] as $item) {
                NotaItem::create([
                    'nota_id' => $nota->id,
                    'nama'    => $item['nama'] ?? '-',
                    'qty'     => $item['qty'] ?? 1,
                    'harga'   => $item['harga'] ?? 0,
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'nota'    => $nota->load('items')
        ]);
    }
}
