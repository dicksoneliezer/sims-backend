<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_number' => 'S'.fake()->unique()->numerify('######'),
            'full_name' => fake()->name(),
            'class' => fake()->randomElement(['Form 1A', 'Form 2B', 'Form 3C', 'Standard 5A']),
            'school_level' => fake()->randomElement(['primary', 'secondary']),
            'parent_phone' => fake()->numerify('+2557########'),
            'email' => fake()->unique()->safeEmail(),
            'residence' => fake()->city(),
            'gender' => fake()->randomElement(['male', 'female']),
            'date_of_birth' => fake()->dateTimeBetween('-18 years', '-5 years')->format('Y-m-d'),
            'opening_balance' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
