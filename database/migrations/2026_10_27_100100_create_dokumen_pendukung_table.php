<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PSKE-5 · Surat tugas yang diunggah saat registrasi (entitas dokumen_pendukung di ERD).
 * Berkas disimpan di disk "local" (storage/app/private), bukan folder publik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen_pendukung', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('jenis', 30)->default('SURAT_TUGAS');
            $table->string('nama_file');
            $table->string('path');
            $table->string('mime', 50);
            $table->unsignedInteger('ukuran');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_pendukung');
    }
};
