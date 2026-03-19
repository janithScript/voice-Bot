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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('patient_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->foreignId('doctor_id')->constrained('doctors');
            $table->date('appointment_date');
            $table->time('appointment_time');
            $table->enum('status', ['pending','confirmed','cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->string('booking_source')->default('voicebot'); // voicebot or web
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
