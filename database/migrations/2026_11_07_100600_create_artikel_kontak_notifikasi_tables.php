<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PSKE-28 · Tabel artikel, kontak, dan notifikasi sesuai ERD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artikel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penulis_id')->constrained('users')->restrictOnDelete();
            $table->string('judul', 255);
            $table->text('isi');
            $table->string('kategori', 100)->nullable()->index();
            $table->boolean('terbit')->default(false)->index();
            $table->dateTime('diterbitkan_pada')->nullable();
            $table->boolean('is_contoh')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('kontak', function (Blueprint $table) {
            $table->id();
            $table->string('kode_wilayah', 20)->index();
            $table->foreignId('instansi_id')->nullable()->constrained('instansi')->nullOnDelete();
            $table->string('nama', 150);
            $table->string('jabatan', 150)->nullable();
            $table->string('telepon', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('is_contoh')->default(false)->index();
            $table->timestamps();

            $table->foreign('kode_wilayah')->references('kode')->on('wilayah')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penerima_id')->constrained('users')->cascadeOnDelete();
            $table->string('jenis', 100)->index();
            $table->string('pesan', 255);
            $table->string('tautan', 255)->nullable();
            $table->boolean('dibaca')->default(false)->index();
            $table->dateTime('dibuat_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
        Schema::dropIfExists('kontak');
        Schema::dropIfExists('artikel');
    }
};

