<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PSKE-28 & PSKE-30 · Penyesuaian kolom ERD dan penanda data simulasi (is_contoh)
 * pada tabel master yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wilayah', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('jumlah_penduduk');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            $table->boolean('is_contoh')->default(false)->after('lng');
        });

        Schema::table('komoditas', function (Blueprint $table) {
            $table->decimal('kebutuhan_per_kapita_harian', 14, 3)->nullable()->after('satuan');
            $table->boolean('aktif')->default(true)->after('kebutuhan_per_kapita_harian');
            $table->boolean('is_contoh')->default(false)->after('aktif');
        });

        Schema::table('instansi', function (Blueprint $table) {
            $table->string('jenis', 100)->nullable()->after('nama');
            $table->string('telepon', 30)->nullable()->after('alamat');
            $table->boolean('aktif')->default(true)->after('longitude');
            $table->boolean('is_contoh')->default(false)->after('aktif');
        });

        Schema::table('gudang', function (Blueprint $table) {
            $table->string('kode_gudang', 50)->nullable()->unique()->after('id');
            $table->decimal('radius_km', 8, 2)->nullable()->after('longitude');
            $table->boolean('is_contoh')->default(false)->after('aktif');
        });

        Schema::table('ambang_status', function (Blueprint $table) {
            $table->dateTime('berlaku_sejak')->nullable()->after('aman_hari');
            $table->foreignId('diubah_oleh')->nullable()->after('berlaku_sejak')->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_contoh')->default(false)->after('alasan_penolakan');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_contoh');
        });

        Schema::table('ambang_status', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diubah_oleh');
            $table->dropColumn('berlaku_sejak');
        });

        Schema::table('gudang', function (Blueprint $table) {
            $table->dropUnique(['kode_gudang']);
            $table->dropColumn(['kode_gudang', 'radius_km', 'is_contoh']);
        });

        Schema::table('instansi', function (Blueprint $table) {
            $table->dropColumn(['jenis', 'telepon', 'aktif', 'is_contoh']);
        });

        Schema::table('komoditas', function (Blueprint $table) {
            $table->dropColumn(['kebutuhan_per_kapita_harian', 'aktif', 'is_contoh']);
        });

        Schema::table('wilayah', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng', 'is_contoh']);
        });
    }
};

