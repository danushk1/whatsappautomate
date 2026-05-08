<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_settings', function (Blueprint $table) {
            $table->integer('free_signup_credits')->default(100)->after('suspended_message');
            $table->decimal('free_signup_balance', 10, 4)->default(250.0000)->after('free_signup_credits');
        });
    }

    public function down(): void
    {
        Schema::table('admin_settings', function (Blueprint $table) {
            $table->dropColumn(['free_signup_credits', 'free_signup_balance']);
        });
    }
};
