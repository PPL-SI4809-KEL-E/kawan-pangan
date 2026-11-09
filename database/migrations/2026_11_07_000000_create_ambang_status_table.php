<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambang_status', function (Blueprint $table) {
            // Satu pengaturan global, dibaca melalui AmbangStatus::aktif().
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedSmallInteger('kritis_hari')->default(14);
            $table->unsignedSmallInteger('aman_hari')->default(30);
            $table->timestamps(3);
        });

        DB::table('ambang_status')->insert([
            'id' => 1,
            'kritis_hari' => 14,
            'aman_hari' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ambang_status');
    }
};
