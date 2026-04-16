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
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('question_type')->default('objective');
            $table->text('prompt');
            $table->boolean('allows_multiple')->default(false);
            $table->json('options');
            $table->json('correct_options');
            $table->decimal('points', 6, 2)->default(1);
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['quiz_id', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
