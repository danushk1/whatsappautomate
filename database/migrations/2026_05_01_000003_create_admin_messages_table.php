<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_messages', function (Blueprint $table) {
            $table->id();
            $table->string('from_number');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamp('received_at');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_messages');
    }
};
