<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PSKE-4 · Kolom akun untuk registrasi Kepala Wilayah dan Badan Resmi.
 *
 * kode_wilayah dan instansi_id belum diberi foreign key karena tabel wilayah (PBI-08)
 * dan instansi (PBI-09) belum ada. Tambahkan constraint saat tabel tersebut dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->nullable()->after('email');
            $table->string('no_hp', 20)->nullable()->after('role');
            $table->string('kode_wilayah', 20)->nullable()->index()->after('no_hp');
            $table->unsignedBigInteger('instansi_id')->nullable()->index()->after('kode_wilayah');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['kode_wilayah']);
            $table->dropIndex(['instansi_id']);
            $table->dropColumn(['role', 'no_hp', 'kode_wilayah', 'instansi_id']);
        });
    }
};
