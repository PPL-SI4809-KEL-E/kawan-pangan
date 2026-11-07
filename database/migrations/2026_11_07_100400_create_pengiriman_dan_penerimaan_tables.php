<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PSKE-28 · Tabel pengiriman, detail_pengiriman, checkpoint, penerimaan, dan detail_penerimaan sesuai ERD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengiriman', function (Blueprint $table) {
            $table->id();
            $table->string('no_resi', 50)->unique();
            $table->foreignId('laporan_id')->nullable()->constrained('laporan')->nullOnDelete();
            $table->foreignId('gudang_asal_id')->constrained('gudang')->restrictOnDelete();
            $table->string('kode_wilayah_tujuan', 20)->index();
            $table->foreignId('pembuat_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('penyetuju_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('petugas_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('jadwal_berangkat')->nullable();
            $table->dateTime('estimasi_tiba')->nullable();
            $table->string('status', 50)->default('DIRENCANAKAN')->index();
            $table->dateTime('disetujui_pada')->nullable();
            $table->dateTime('berangkat_pada')->nullable();
            $table->string('alasan_batal', 255)->nullable();
            $table->boolean('is_contoh')->default(false)->index();
            $table->timestamps();

            $table->foreign('kode_wilayah_tujuan')->references('kode')->on('wilayah')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('detail_pengiriman', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengiriman_id')->constrained('pengiriman')->cascadeOnDelete();
            $table->foreignId('komoditas_id')->constrained('komoditas')->restrictOnDelete();
            $table->decimal('jumlah_dikirim', 14, 3);
            $table->timestamps();

            $table->unique(['pengiriman_id', 'komoditas_id']);
        });

        Schema::create('checkpoint', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengiriman_id')->constrained('pengiriman')->cascadeOnDelete();
            $table->string('jenis', 50)->index();
            $table->string('lokasi', 255)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->dateTime('waktu');
            $table->string('foto', 255)->nullable();
            $table->string('catatan', 500)->nullable();
            $table->foreignId('petugas_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('penerimaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengiriman_id')->constrained('pengiriman')->restrictOnDelete();
            $table->foreignId('pencatat_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('waktu_diterima');
            $table->string('bukti_foto', 255)->nullable();
            $table->string('catatan', 500)->nullable();
            $table->string('ref_operasi', 191)->unique();
            $table->timestamps();
        });

        Schema::create('detail_penerimaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penerimaan_id')->constrained('penerimaan')->cascadeOnDelete();
            $table->foreignId('komoditas_id')->constrained('komoditas')->restrictOnDelete();
            $table->decimal('jumlah_dikirim', 14, 3);
            $table->decimal('jumlah_diterima', 14, 3);
            $table->string('alasan_selisih', 255)->nullable();
            $table->timestamps();

            $table->unique(['penerimaan_id', 'komoditas_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_penerimaan');
        Schema::dropIfExists('penerimaan');
        Schema::dropIfExists('checkpoint');
        Schema::dropIfExists('detail_pengiriman');
        Schema::dropIfExists('pengiriman');
    }
};

