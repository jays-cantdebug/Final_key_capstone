<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CounselingSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CounselingSession>
 */
class CounselingSessionFactory extends Factory
{
    protected $model = CounselingSession::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'assessment_id' => null,
            'counselor_id' => User::factory()->guidanceCounselor(),
            'session_datetime' => now()->addDay(),
            'session_notes' => $this->faker->sentence(),
            'session_status' => CounselingSession::STATUS_SCHEDULED,
            'follow_up_required' => false,
            'follow_up_date' => null,
            'confidentiality_level' => CounselingSession::CONFIDENTIALITY_STANDARD,
        ];
    }

    public function restricted(): static
    {
        return $this->state(fn (): array => ['confidentiality_level' => CounselingSession::CONFIDENTIALITY_RESTRICTED]);
    }
}
