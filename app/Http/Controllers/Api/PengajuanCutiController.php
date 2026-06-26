<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengajuan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class PengajuanCutiController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'id_karyawan'     => 'required|integer',
            'kategori_cuti'   => 'required|in:Tahunan,Sakit,Melahirkan',
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'bukti_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $mulai = Carbon::parse($request->tanggal_mulai);
        $selesai = Carbon::parse($request->tanggal_selesai);
        $totalHari = $mulai->diffInDays($selesai) + 1;

       $urlServiceKaryawan = env('LAYANAN_KARYAWAN_URL') . $request->id_karyawan;

        try {
            $response = Http::get($urlServiceKaryawan);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Karyawan tidak ditemukan di Service Karyawan!'
                ], 404);
            }

            $dataKaryawan = $response->json()['data'];
            $sisaCutiKaryawan = $dataKaryawan['sisa_jatah_cuti'];

            if ($request->kategori_cuti === 'Tahunan') {
                if ($sisaCutiKaryawan < $totalHari) {
                    return response()->json([
                        'success' => false,
                        'message' => "Jatah cuti tahunan tidak cukup! Sisa jatah Anda: {$sisaCutiKaryawan} hari, Anda mengajukan: {$totalHari} hari."
                    ], 400);
                }
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal terhubung ke Service Karyawan. Pastikan service tersebut aktif.'
            ], 500);
        }

        $namaFile = null;
        if ($request->hasFile('bukti_pendukung')) {
            $file = $request->file('bukti_pendukung');
            $namaFile = 'bukti-' . time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/bukti', $namaFile);
        }

        $pengajuan = Pengajuan::create([
            'id_karyawan'     => $request->id_karyawan,
            'kategori_cuti'   => $request->kategori_cuti,
            'tanggal_mulai'   => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'total_hari'      => $totalHari,
            'bukti_pendukung' => $namaFile,
            'status'          => 'Pending',
        ]);

        $payload = [
            'id_pengajuan'  => $pengajuan->id,
            'id_karyawan'   => $pengajuan->id_karyawan,
            'kategori_cuti' => $pengajuan->kategori_cuti,
            'total_hari'    => $pengajuan->total_hari,
        ];

        \App\Jobs\NotifikasiCuti::dispatch($payload);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan cuti berhasil dibuat!',
            'data'    => $pengajuan
        ], 201);
    }

    public function show($id)
    {
        $pengajuan = \App\Models\Pengajuan::find($id);

        if (!$pengajuan) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengajuan cuti tidak ditemukan!'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail pengajuan cuti berhasil diambil.',
            'data'    => $pengajuan
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:Disetujui,Ditolak',
        ]);

        $pengajuan = Pengajuan::find($id);

        if (!$pengajuan) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengajuan cuti tidak ditemukan!'
            ], 404);
        }

        $pengajuan->update([
            'status' => $request->status
        ]);

        return response()->json([
            'success' => true,
            'message' => "Status pengajuan cuti berhasil diperbarui menjadi {$request->status}!",
            'data'    => $pengajuan
        ], 200);
    }
}
