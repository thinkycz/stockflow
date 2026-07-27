<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceBreak>
 */
class AttendanceBreakFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attendance_session_id' => AttendanceSession::factory(),
            'active_session_id' => null,
            'started_at' => $this->faker->dateTimeBetween('-4 hours', '-3 hours'),
            'ended_at' => $this->faker->dateTimeBetween('-2 hours', '-1 hour'),
        ];
    }
}
