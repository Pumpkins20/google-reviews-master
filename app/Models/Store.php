<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Store master data (table `store` in the application database).
 */
class Store extends Model
{
    protected $table = 'store';

    // The table holds credentials (username/password); only these are ever selected.
    const COLUMNS = [
        'id', 'kode_toko', 'nama_toko', 'alamat_toko', 'kelurahan', 'kecamatan',
        'kota_kab', 'provinsi', 'kode_pos', 'telp', 'whatsapp', 'nohp_delivery', 'webmail',
        'link_gmaps', 'lat', 'lng', 'area', 'status_toko',
    ];

    public function scopeSafe($query)
    {
        return $query->select(self::COLUMNS);
    }
}
