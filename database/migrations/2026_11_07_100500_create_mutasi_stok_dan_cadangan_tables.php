<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PSKE-28 · Tabel mutasi_stok dan cadangan sesuai ERD.
 * Waktu mutasi memakai datetime(3), ref_operasi wajib unik, dan saldo tidak boleh negatif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutasi_stok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stok_id')->constrained('stok')->restrictOnDelete();
            $table->string('jenis', 50)->index();
            $table->decimal('jumlah', 14, 3);
            $table->decimal('saldo_setelah', 14, 3);
            $table->dateTime('waktu', 3)->index();
            $table->string('ref_operasi', 191)->unique();
            $table->foreignId('pengiriman_id')->nullable()->constrained('pengiriman')->nullOnDelete();
            $table->foreignId('pencatat_id')->constrained('users')->restrictOnDelete();
            $table->string('keterangan', 255)->nullable();
            $table->boolean('is_contoh')->default(false)->index();
            $table->timestamps(3);

            $table->index(['stok_id', 'jenis', 'waktu']);
        });

        Schema::create('cadangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stok_id')->constrained('stok')->restrictOnDelete();
            $table->foreignId('pengiriman_id')->constrained('pengiriman')->cascadeOnDelete();
            $table->decimal('jumlah', 14, 3);
            $table->string('status', 50)->default('AKTIF')->index();
            $table->dateTime('dibuat_pada');
            $table->dateTime('selesai_pada')->nullable();
            $table->boolean('is_contoh')->default(false)->index();
            $table->timestamps();

            $table->index(['stok_id', 'status']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared("
                CREATE TRIGGER trg_mutasi_stok_non_negatif_ins
                BEFORE INSERT ON mutasi_stok
                FOR EACH ROW
                WHEN NEW.saldo_setelah < 0
                BEGIN
                    SELECT RAISE(ABORT, 'Saldo setelah mutasi tidak boleh negatif.');
                END;
            ");
            DB::unprepared("
                CREATE TRIGGER trg_mutasi_stok_non_negatif_upd
                BEFORE UPDATE ON mutasi_stok
                FOR EACH ROW
                WHEN NEW.saldo_setelah < 0
                BEGIN
                    SELECT RAISE(ABORT, 'Saldo setelah mutasi tidak boleh negatif.');
                END;
            ");
            DB::unprepared("
                CREATE TRIGGER trg_cadangan_non_negatif_ins
                BEFORE INSERT ON cadangan
                FOR EACH ROW
                WHEN NEW.jumlah < 0
                BEGIN
                    SELECT RAISE(ABORT, 'Jumlah cadangan tidak boleh negatif.');
                END;
            ");
            DB::unprepared("
                CREATE TRIGGER trg_cadangan_non_negatif_upd
                BEFORE UPDATE ON cadangan
                FOR EACH ROW
                WHEN NEW.jumlah < 0
                BEGIN
                    SELECT RAISE(ABORT, 'Jumlah cadangan tidak boleh negatif.');
                END;
            ");
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE mutasi_stok ADD CONSTRAINT chk_mutasi_saldo_non_negatif CHECK (saldo_setelah >= 0)');
            DB::statement('ALTER TABLE cadangan ADD CONSTRAINT chk_cadangan_jumlah_non_negatif CHECK (jumlah >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cadangan');
        Schema::dropIfExists('mutasi_stok');
    }
};

