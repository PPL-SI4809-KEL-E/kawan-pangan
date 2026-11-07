<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instansi', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150)->index();
            $table->string('alamat', 500);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamps();
        });
        Schema::create('gudang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instansi_id')->constrained('instansi')->restrictOnDelete();
            $table->string('kode_wilayah', 20);
            $table->string('nama', 150)->index();
            $table->string('alamat', 500);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('aktif')->default(true)->index();
            $table->timestamps();
            $table->foreign('kode_wilayah')->references('kode')->on('wilayah')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gudang');
        Schema::dropIfExists('instansi');
    }
};

