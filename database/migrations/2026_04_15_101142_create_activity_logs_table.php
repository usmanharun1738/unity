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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action')->comment('login, submission, grade_view, etc.');
            $table->string('resource_type')->nullable()->comment('course, assignment, quiz');
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('ip_address');
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
