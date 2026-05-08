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
        Schema::create('operational_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->enum('context_type', ['order', 'event']);
            $table->unsignedBigInteger('context_id');
            $table->enum('channel', ['in_app', 'email'])->default('in_app');
            $table->string('title', 150);
            $table->text('body')->nullable();
            $table->string('previous_status', 50)->nullable();
            $table->string('new_status', 50);
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('actor_user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['user_id', 'created_at']);
            $table->index(['context_type', 'context_id']);
            $table->index(['user_id', 'read_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_notifications');
    }
};
