<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create subjects catalog master table
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('credits')->default(3);
            $table->timestamps();

            $table->index('department_id');
        });

        // 2. Add subject_id, section_code, academic_year to courses (offerings) table
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->cascadeOnDelete();
            $table->string('section_code')->nullable()->default('Section A');
            $table->string('academic_year')->nullable();
            $table->index('faculty_profile_id');
        });

        // 3. Add course_id index to enrollments
        Schema::table('enrollments', function (Blueprint $table) {
            $table->index('course_id');
        });

        // 4. Add department_id to student_profiles
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->index('department_id');
        });

        // 5. Backfill existing courses into subjects catalog master
        $courses = DB::table('courses')->get();
        foreach ($courses as $course) {
            $subjectId = DB::table('subjects')->where('code', $course->code)->value('id');
            if (! $subjectId) {
                $subjectId = DB::table('subjects')->insertGetId([
                    'department_id' => $course->department_id,
                    'code' => $course->code,
                    'title' => $course->title,
                    'description' => $course->description,
                    'credits' => $course->credits ?? 3,
                    'created_at' => $course->created_at ?? now(),
                    'updated_at' => $course->updated_at ?? now(),
                ]);
            }
            DB::table('courses')->where('id', $course->id)->update(['subject_id' => $subjectId]);
        }

        // 6. Backfill student_profiles.department_id from major string where matched
        $departments = DB::table('departments')->get();
        foreach ($departments as $dept) {
            DB::table('student_profiles')
                ->whereNull('department_id')
                ->where('major', 'like', "%{$dept->name}%")
                ->update(['department_id' => $dept->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['course_id']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropIndex(['faculty_profile_id']);
            $table->dropColumn(['subject_id', 'section_code', 'academic_year']);
        });

        Schema::dropIfExists('subjects');
    }
};
