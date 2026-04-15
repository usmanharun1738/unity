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
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->decimal('ca_score', 5, 2)->nullable();
            $table->decimal('test_score', 5, 2)->nullable();
            $table->decimal('assignment_score', 5, 2)->nullable();
            $table->decimal('quiz_score', 5, 2)->nullable();
            $table->decimal('project_score', 5, 2)->nullable();
            $table->decimal('exam_score', 5, 2)->nullable();
            $table->decimal('final_grade', 5, 2)->nullable()->comment('Calculated: CA(30%) + Tests(20%) + Assignments(10%) + Projects(10%) + Exam(30%)');
            $table->char('grade_letter', 1)->nullable()->comment('A, B, C, D, F');
            $table->boolean('is_approved_by_admin')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullifyOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
