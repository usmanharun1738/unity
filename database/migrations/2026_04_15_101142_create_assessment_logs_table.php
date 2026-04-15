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
        Schema::create('assessment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->enum('assessment_type', ['ca', 'test', 'assignment', 'quiz', 'project', 'exam']);
            $table->string('assessment_name');
            $table->decimal('score', 5, 2);
            $table->decimal('max_score', 5, 2);
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullifyOnDelete();
            $table->dateTime('assessed_at');
            $table->text('notes')->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'course_id']);
            $table->index(['assessment_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_logs');
    }
};
