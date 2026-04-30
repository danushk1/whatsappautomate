<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('private_phone')->nullable()->after('balance');
            $table->timestamp('low_balance_notified_at')->nullable()->after('private_phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['private_phone', 'low_balance_notified_at']);
        });
    }
};
