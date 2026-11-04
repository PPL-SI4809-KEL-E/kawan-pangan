<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wilayah', function (Blueprint $table) {
            $table->string('kode', 20)->primary();
            $table->string('nama', 150);
            $table->unsignedTinyInteger('level')->index();
            $table->string('kode_induk', 20)->nullable()->index();
            // NULL berarti belum tersedia; 0 berarti sudah diketahui tidak ada penduduk.
            $table->unsignedInteger('jumlah_penduduk')->nullable();
            $table->timestamps();
            $table->foreign('kode_induk')->references('kode')->on('wilayah')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wilayah');
    }
};
