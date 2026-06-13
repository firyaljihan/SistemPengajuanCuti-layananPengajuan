<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Pengajuan extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_karyawan',
        'kategori_cuti',
        'bukti_pendukung',
        'tanggal_mulai',
        'tanggal_selesai',
        'total_hari',
        'status',
    ];
}
