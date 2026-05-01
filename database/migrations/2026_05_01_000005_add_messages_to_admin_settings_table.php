<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_settings', function (Blueprint $table) {
            $table->text('low_balance_message')->nullable()->after('bank_branch');
            $table->text('suspended_message')->nullable()->after('low_balance_message');
        });
    }

    public function down(): void
    {
        Schema::table('admin_settings', function (Blueprint $table) {
            $table->dropColumn(['low_balance_message', 'suspended_message']);
        });
    }
};
