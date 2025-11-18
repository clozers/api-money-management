<?php

namespace App\Http\Controllers;

use App\Models\KategoriPengeluaran;
use Illuminate\Http\Request;

class KategoripengeluaranController extends Controller
{
    // CREATE
    public function store(Request $request)
    {
        $request->validate([
            'nama_kategori' => 'required|string|max:255',
            'deskripsi'     => 'nullable|string',
        ]);

        $kategori = KategoriPengeluaran::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dibuat',
            'data'    => $kategori
        ], 201);
    }

    // READ ALL
    public function index()
    {
        return response()->json(KategoriPengeluaran::all(), 200);
    }

    // READ BY ID
    public function show($id)
    {
        $kategori = KategoriPengeluaran::find($id);

        if (!$kategori) {
            return response()->json(['message' => 'Kategori tidak ditemukan'], 404);
        }

        return response()->json($kategori, 200);
    }

    // UPDATE
    public function update(Request $request, $id)
    {
        $kategori = KategoriPengeluaran::find($id);

        if (!$kategori) {
            return response()->json(['message' => 'Kategori tidak ditemukan'], 404);
        }

        $kategori->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil diupdate',
            'data'    => $kategori
        ], 200);
    }

    // DELETE
    public function destroy($id)
    {
        $kategori = KategoriPengeluaran::find($id);

        if (!$kategori) {
            return response()->json(['message' => 'Kategori tidak ditemukan'], 404);
        }

        $kategori->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dihapus'
        ], 200);
    }
}
