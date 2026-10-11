<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contact> */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+1555'.fake()->unique()->numerify('01#####'),
            'email' => fake()->unique()->userName().'@example.test',
            'visits' => fake()->numberBetween(0, 12),
            'tags' => [],
        ];
    }
}
