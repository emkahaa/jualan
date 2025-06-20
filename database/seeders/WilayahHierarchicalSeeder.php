<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Models\Province;
use App\Models\Regency;
use App\Models\District;
use App\Models\Village;

class WilayahHierarchicalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Memulai WilayahHierarchicalSeeder...');

        // Menonaktifkan pengecekan foreign key untuk import data besar
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        $this->command->info('Foreign key checks dinonaktifkan.');

        // Mengosongkan tabel-tabel terlebih dahulu
        // Urutan TRUNCATE harus dari yang paling bawah hirarki
        Village::truncate();
        District::truncate();
        Regency::truncate();
        Province::truncate();
        $this->command->info('Semua tabel wilayah telah dikosongkan.');

        $sqlContent = File::get(database_path('seeders/wilayah.sql'));
        $this->command->info('File wilayah.sql berhasil dibaca.');

        // Regex untuk mengekstrak kode dan nama dari baris INSERT
        // Kita hanya tertarik pada bagian VALUES dari INSERT statement
        // Pastikan regex menangkap semua baris VALUES
        preg_match_all("/\('([^']*)','([^']*)'\)/", $sqlContent, $matches, PREG_SET_ORDER);

        $this->command->info('Ditemukan ' . count($matches) . ' pasangan (kode, nama) di file SQL.');
        // dd($matches); // Uncomment this to see all parsed matches if you suspect regex issue

        $insertedCount = [
            'provinces' => 0,
            'regencies' => 0,
            'districts' => 0,
            'villages' => 0,
        ];

        // Cache untuk ID parent agar tidak perlu query ulang
        $provinceIdMap = []; // kode_provinsi => id_provinsi (integer)
        $regencyIdMap = [];   // kode_regency => id_regency (integer)
        $districtIdMap = [];  // kode_district => id_district (integer)

        foreach ($matches as $index => $match) {
            $kode = $match[1];
            $nama = $match[2];
            $length = strlen($kode);

            // Output setiap baris yang diproses untuk debugging
            // $this->command->info("Processing: [{$index}] Kode: {$kode}, Nama: {$nama}, Panjang: {$length}");

            try {
                if ($length == 2) { // Provinsi
                    $province = Province::create([
                        'code' => $kode,
                        'name' => $nama,
                    ]);
                    $provinceIdMap[$kode] = $province->id;
                    $insertedCount['provinces']++;
                    // $this->command->info("  Inserted Provinsi: {$nama} (ID: {$province->id}, Code: {$kode})");
                } elseif ($length == 5) { // Kabupaten/Kota
                    $provinceCode = substr($kode, 0, 2);
                    $provinceId = $provinceIdMap[$provinceCode] ?? null;

                    // $this->command->info("  Processing Kabupaten/Kota: {$nama} (Kode: {$kode}). Parent Province Code: {$provinceCode}, Parent ID in map: " . ($provinceId ?? 'N/A'));

                    if ($provinceId) {
                        $regency = Regency::create([
                            'code' => $kode,
                            'province_id' => $provinceId,
                            'name' => $nama,
                        ]);
                        $regencyIdMap[$kode] = $regency->id;
                        $insertedCount['regencies']++;
                        // $this->command->info("    Inserted Kabupaten/Kota: {$nama} (ID: {$regency->id}, Code: {$kode})");
                    } else {
                        $this->command->warn("    Provinsi dengan kode {$provinceCode} tidak ditemukan untuk Kabupaten/Kota {$kode}. Baris ini dilewati.");
                    }
                } elseif ($length == 8) { // Kecamatan
                    $regencyCode = substr($kode, 0, 5);
                    $regencyId = $regencyIdMap[$regencyCode] ?? null;

                    // $this->command->info("  Processing Kecamatan: {$nama} (Kode: {$kode}). Parent Regency Code: {$regencyCode}, Parent ID in map: " . ($regencyId ?? 'N/A'));

                    if ($regencyId) {
                        $district = District::create([
                            'code' => $kode,
                            'regency_id' => $regencyId,
                            'name' => $nama,
                        ]);
                        $districtIdMap[$kode] = $district->id;
                        $insertedCount['districts']++;
                        // $this->command->info("    Inserted Kecamatan: {$nama} (ID: {$district->id}, Code: {$kode})");
                    } else {
                        $this->command->warn("    Kabupaten/Kota dengan kode {$regencyCode} tidak ditemukan untuk Kecamatan {$kode}. Baris ini dilewati.");
                    }
                } elseif ($length == 13) { // Kelurahan
                    $districtCode = substr($kode, 0, 8);
                    $districtId = $districtIdMap[$districtCode] ?? null;

                    // $this->command->info("  Processing Kelurahan: {$nama} (Kode: {$kode}). Parent District Code: {$districtCode}, Parent ID in map: " . ($districtId ?? 'N/A'));

                    if ($districtId) {
                        Village::create([
                            'code' => $kode,
                            'district_id' => $districtId,
                            'name' => $nama,
                        ]);
                        $insertedCount['villages']++;
                        // $this->command->info("    Inserted Kelurahan: {$nama} (ID: {$village->id}, Code: {$kode})");
                    } else {
                        $this->command->warn("    Kecamatan dengan kode {$districtCode} tidak ditemukan untuk Kelurahan {$kode}. Baris ini dilewati.");
                    }
                } else {
                    $this->command->warn("Kode {$kode} dengan panjang {$length} tidak cocok dengan level manapun. Baris ini dilewati.");
                }
            } catch (\Exception $e) {
                $this->command->error("Gagal mengimpor kode {$kode} - {$nama}: " . $e->getMessage());
                // dd($e); // Uncomment to get full exception details
            }
        }

        // Mengaktifkan kembali pengecekan foreign key
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        $this->command->info('Foreign key checks diaktifkan kembali.');

        $this->command->info('Import data wilayah selesai!');
        $this->command->info('Jumlah data diimpor:');
        $this->command->info('  Provinsi: ' . $insertedCount['provinces']);
        $this->command->info('  Kabupaten/Kota: ' . $insertedCount['regencies']);
        $this->command->info('  Kecamatan: ' . $insertedCount['districts']);
        $this->command->info('  Kelurahan: ' . $insertedCount['villages']);
    }
}
