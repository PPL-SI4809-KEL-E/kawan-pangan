<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PSKE-28 · Tabel cakupan_gudang dan stok sesuai ERD.
 * Jumlah stok menggunakan decimal(14,3) dan tidak boleh bernilai negatif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cakupan_gudang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
            $table->string('kode_kecamatan', 20)->index();
            $table->boolean('is_utama')->default(false);
            $table->timestamps();

            $table->unique(['gudang_id', 'kode_kecamatan']);
            $table->foreign('kode_kecamatan')->references('kode')->on('wilayah')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('stok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gudang_id')->constrained('gudang')->restrictOnDelete();
            $table->foreignId('komoditas_id')->constrained('komoditas')->restrictOnDelete();
            $table->decimal('jumlah_fisik_layak', 14, 3)->default(0);
            $table->date('mulai_pencatatan_lengkap')->nullable();
            $table->dateTime('diperbarui_pada')->nullable();
            $table->boolean('is_contoh')->default(false)->index();
            $table->timestamps();

            $table->unique(['gudang_id', 'komoditas_id']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared("
                CREATE TRIGGER trg_stok_non_negatif_ins
                BEFORE INSERT ON stok
                FOR EACH ROW
                WHEN NEW.jumlah_fisik_layak < 0
                BEGIN
                    SELECT RAISE(ABORT, 'Saldo stok fisik tidak boleh negatif.');
                END;
            ");
            DB::unprepared("
                CREATE TRIGGER trg_stok_non_negatif_upd
                BEFORE UPDATE ON stok
                FOR EACH ROW
                WHEN NEW.jumlah_fisik_layak < 0
                BEGIN
                    SELECT RAISE(ABORT, 'Saldo stok fisik tidak boleh negatif.');
                END;
            ");
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE stok ADD CONSTRAINT chk_stok_jumlah_non_negatif CHECK (jumlah_fisik_layak >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stok');
        Schema::dropIfExists('cakupan_gudang');
    }
};

