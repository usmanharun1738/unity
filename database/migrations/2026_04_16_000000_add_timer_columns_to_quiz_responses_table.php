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
        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->dateTime('started_at')->nullable()->after('score');
            $table->dateTime('expires_at')->nullable()->after('started_at');
            $table->dateTime('submitted_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'expires_at']);
            $table->dateTime('submitted_at')->nullable(false)->change();
        });
    }
};
