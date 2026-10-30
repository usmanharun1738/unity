<?php

namespace App\Services\Grading;

use App\Models\Grade;

class GradeCalculatorService
{
    public const float WEIGHT_CA = 0.25;

    public const float WEIGHT_TEST = 0.15;

    public const float WEIGHT_QUIZ = 0.10;

    public const float WEIGHT_ASSIGNMENT = 0.10;

    public const float WEIGHT_PROJECT = 0.10;

    public const float WEIGHT_EXAM = 0.30;

    /**
     * Recalculate final grade and letter for a given Grade model.
     */
    public function recalculate(Grade $grade, bool $invalidateApproval = true): Grade
    {
        $final = ((float) ($grade->ca_score ?? 0) * self::WEIGHT_CA)
            + ((float) ($grade->test_score ?? 0) * self::WEIGHT_TEST)
            + ((float) ($grade->quiz_score ?? 0) * self::WEIGHT_QUIZ)
            + ((float) ($grade->assignment_score ?? 0) * self::WEIGHT_ASSIGNMENT)
            + ((float) ($grade->project_score ?? 0) * self::WEIGHT_PROJECT)
            + ((float) ($grade->exam_score ?? 0) * self::WEIGHT_EXAM);

        $grade->final_grade = round($final, 2);
        $grade->grade_letter = $this->determineLetter($grade->final_grade);

        if ($invalidateApproval) {
            $grade->is_approved_by_admin = false;
            $grade->approved_by = null;
            $grade->approved_at = null;
        }

        $grade->save();

        return $grade;
    }

    /**
     * Calculate score and letter from raw array without persisting to database.
     *
     * @param  array{ca?: float|int|string|null, test?: float|int|string|null, quiz?: float|int|string|null, assignment?: float|int|string|null, project?: float|int|string|null, exam?: float|int|string|null}  $scores
     * @return array{final_grade: float, grade_letter: string}
     */
    public function calculate(array $scores): array
    {
        $final = ((float) ($scores['ca'] ?? 0) * self::WEIGHT_CA)
            + ((float) ($scores['test'] ?? 0) * self::WEIGHT_TEST)
            + ((float) ($scores['quiz'] ?? 0) * self::WEIGHT_QUIZ)
            + ((float) ($scores['assignment'] ?? 0) * self::WEIGHT_ASSIGNMENT)
            + ((float) ($scores['project'] ?? 0) * self::WEIGHT_PROJECT)
            + ((float) ($scores['exam'] ?? 0) * self::WEIGHT_EXAM);

        $rounded = round($final, 2);

        return [
            'final_grade' => $rounded,
            'grade_letter' => $this->determineLetter($rounded),
        ];
    }

    /**
     * Determine letter grade according to institutional scale.
     */
    public function determineLetter(float $score): string
    {
        return match (true) {
            $score >= 80.0 => 'A',
            $score >= 70.0 => 'B',
            $score >= 60.0 => 'C',
            $score >= 50.0 => 'D',
            default => 'F',
        };
    }
}
