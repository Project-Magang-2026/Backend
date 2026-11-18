<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip_address', 45)->index();
            $table->unsignedSmallInteger('api_port')->default(8728);
            $table->string('api_username');
            $table->text('api_password'); // Wajib TEXT untuk menampung hasil Crypt::encryptString()
            $table->enum('ros_version', ['v6', 'v7'])->default('v7');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routers');
    }
};