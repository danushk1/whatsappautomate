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
            $table->string('api_key')->unique()->nullable()->after('password')->comment('SaaS User API Key');
            $table->enum('target_mode', ['EXCEL', 'API'])->default('API')->after('api_key');
            $table->string('target_value')->nullable()->after('target_mode')->comment('For EXCEL: sheet name. For API: URL.');
            $table->unsignedInteger('credits')->default(0)->after('target_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'api_key',
                'target_mode',
                'target_value',
                'credits',
            ]);
        });
    }
};