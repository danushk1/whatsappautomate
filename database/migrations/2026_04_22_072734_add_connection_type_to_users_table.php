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
            $table->string('connection_type', 50)->default('cloud_api')->after('id');
            $table->longText('whatsapp_session')->nullable()->after('connection_type');
            $table->string('whatsapp_qr_code_path')->nullable()->after('whatsapp_session');
            $table->timestamp('whatsapp_connected_at')->nullable()->after('whatsapp_qr_code_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'connection_type',
                'whatsapp_session',
                'whatsapp_qr_code_path',
                'whatsapp_connected_at',
            ]);
        });
    }
};
