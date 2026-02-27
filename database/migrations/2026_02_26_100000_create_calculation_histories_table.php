<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculation_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_equation_id')->nullable()->constrained('saved_equations')->nullOnDelete();
            $table->string('equation_name');
            $table->longText('formula_snapshot');
            $table->json('inputs');
            $table->decimal('result', 18, 4);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('calculation_histories', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculation_histories');
    }
};
