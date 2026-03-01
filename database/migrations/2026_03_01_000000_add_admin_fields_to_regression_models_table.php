<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regression_models', function (Blueprint $table) {
            $table->boolean('is_approved')->default(false)->after('is_default');
            $table->boolean('is_official')->default(false)->after('is_approved');
            $table->boolean('is_archived')->default(false)->after('is_official');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('regression_models', function (Blueprint $table) {
            $table->dropColumn(['is_approved', 'is_official', 'is_archived']);
            $table->dropSoftDeletes();
        });
    }
};
