<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soil_loss_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('province');
            $table->string('municipality');
            $table->string('barangay')->nullable();
            $table->integer('year');
            $table->decimal('soil_loss_tonnes_per_ha', 10, 2);
            $table->string('risk_level', 20)->default('medium'); // low, medium, high
            $table->string('model_used')->nullable();
            $table->json('parameters')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soil_loss_records');
    }
};
