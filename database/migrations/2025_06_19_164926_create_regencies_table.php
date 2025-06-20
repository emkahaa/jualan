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
        Schema::create('regencies', function (Blueprint $table) {
            $table->id(); // ID auto-incrementing
            $table->char('code', 4)->unique(); // Kolom kode Kota/Kabupaten, unik
            $table->foreignId('province_id')->constrained('provinces')->onDelete('cascade'); // Foreign Key ke ID Provinsi
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
        Schema::dropIfExists('regencies');
    }
};
