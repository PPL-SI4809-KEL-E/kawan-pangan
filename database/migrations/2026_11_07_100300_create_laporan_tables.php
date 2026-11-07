<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PSKE-28 · Tabel laporan, detail_laporan, dan lampiran_laporan sesuai ERD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_laporan', 50)->unique();
            $table->foreignId('pelapor_id')->constrained('users')->restrictOnDelete();
            $table->string('kode_wilayah', 20)->index();
            $table->string('jenis', 50)->index();
            $table->text('deskripsi')->nullable();
            $table->string('urgensi', 50)->index();
            $table->string('status', 50)->default('DRAF')->index();
            $table->dateTime('dibuat_pada')->nullable();
            $table->dateTime('diajukan_pada')->nullable();
            $table->foreignId('verifikator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('diverifikasi_pada')->nullable();
            $table->string('alasan_penolakan', 255)->nullable();
            $table->string('alasan_penutupan', 255)->nullable();
            $table->boolean('is_contoh')->default(false)->index();
            $table->timestamps();

            $table->foreign('kode_wilayah')->references('kode')->on('wilayah')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('detail_laporan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('laporan')->cascadeOnDelete();
            $table->foreignId('komoditas_id')->constrained('komoditas')->restrictOnDelete();
            $table->decimal('jumlah_dibutuhkan', 14, 3);
            $table->decimal('stok_dilaporkan', 14, 3)->nullable();
            $table->timestamps();

            $table->unique(['laporan_id', 'komoditas_id']);
        });

        Schema::create('lampiran_laporan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('laporan')->cascadeOnDelete();
            $table->string('path_file', 255);
            $table->string('jenis_file', 100)->nullable();
            $table->dateTime('diunggah_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lampiran_laporan');
        Schema::dropIfExists('detail_laporan');
        Schema::dropIfExists('laporan');
    }
};

