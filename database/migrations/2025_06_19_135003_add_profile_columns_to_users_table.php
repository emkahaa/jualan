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
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_picture')->nullable()->after('status');
            $table->enum('gender', ['male', 'female'])->nullable()->after('profile_picture');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('phone_number', 20)->unique()->nullable()->after('date_of_birth');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['profile_picture', 'gender', 'date_of_birth', 'phone_number']);
        });
    }
};
