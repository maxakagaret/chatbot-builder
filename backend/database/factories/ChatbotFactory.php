<?php

namespace Database\Factories;

use App\Chatbot\Scenarios\StarterScenario;
use App\Models\Chatbot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Chatbot> */
class ChatbotFactory extends Factory
{
    protected $model = Chatbot::class;

    public function definition(): array
    {
        return [
            'name' => 'Assistant '.fake()->unique()->numberBetween(1, 999),
            'description' => null,
            'draft_graph' => StarterScenario::appointmentAssistant(),
        ];
    }

    public function graph(array $graph): static
    {
        return $this->state(['draft_graph' => $graph]);
    }
}
