<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PSKE-6 · Status akun dan jejak verifikasi.
 * Default MENUNGGU: akun baru belum bisa login sampai diverifikasi Superadmin (PBI-06).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('MENUNGGU')->index()->after('instansi_id');
            $table->foreignId('verifikator_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable()->after('verifikator_id');
            $table->string('alasan_penolakan')->nullable()->after('diverifikasi_pada');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verifikator_id');
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'diverifikasi_pada', 'alasan_penolakan']);
        });
    }
};
