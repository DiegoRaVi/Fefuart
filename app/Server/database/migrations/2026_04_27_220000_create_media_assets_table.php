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
        Schema::create('media_assets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('context_type', 60)->nullable();
            $table->unsignedBigInteger('context_id')->nullable();
            $table->string('disk', 30)->default('public');
            $table->string('path');
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->enum('visibility', ['public', 'private'])->default('public');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['context_type', 'context_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
