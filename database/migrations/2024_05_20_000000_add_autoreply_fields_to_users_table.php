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
            $table->boolean('is_autoreply_enabled')->default(false);
            $table->text('autoreply_message')->nullable();
            $table->integer('autoreply_credits')->default(0);
            $table->boolean('has_claimed_autoreply_bonus')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_autoreply_enabled', 'autoreply_message', 'autoreply_credits', 'has_claimed_autoreply_bonus']);
        });
    }
};