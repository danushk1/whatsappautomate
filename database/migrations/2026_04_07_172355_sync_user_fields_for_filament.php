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
            // Uniqueness for phone number id
            // $table->string('whatsapp_phone_number_id')->unique()->change();
            
            if (!Schema::hasColumn('users', 'whatsapp_business_account_id')) {
                $table->string('whatsapp_business_account_id')->nullable()->after('whatsapp_phone_number_id');
            }
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('status');
            }
            
            // If target_mode was string, we can try to change it to enum if database supports it
            // Otherwise we just keep it as string but validate in Filament.
            // For now, let's just make sure it has a default.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_business_account_id', 'is_active']);
            // Drop unique index if needed (tricky with SQLite sometimes)
        });
    }
};
