<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->id(); // ID auto-incrementing
            $table->char('code', 7)->unique(); // Kolom kode Kecamatan, unik
            $table->foreignId('regency_id')->constrained('regencies')->onDelete('cascade'); // Foreign Key ke ID Kota/Kabupaten
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};
