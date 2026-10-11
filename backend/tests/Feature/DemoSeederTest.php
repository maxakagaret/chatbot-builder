<?php

use App\Enums\SessionStatus;
use App\Models\Booking;
use App\Models\Chatbot;
use App\Models\ConversationSession;
use Database\Seeders\DemoSeeder;

it('seeds bots and real engine-generated conversation history', function () {
    $this->seed(DemoSeeder::class);

    $assistant = Chatbot::where('name', 'Appointment assistant')->sole();
    $statuses = ConversationSession::pluck('status')->map->value->unique();

    expect($assistant->published_version_id)->not->toBeNull()
        ->and(ConversationSession::count())->toBe(60)
        ->and(Booking::count())->toBeGreaterThan(0)
        ->and($statuses)->toContain(SessionStatus::Completed->value, SessionStatus::Handoff->value);

    $this->getJson('/api/chatbots/'.Chatbot::where('name', 'like', 'Feedback%')->value('id'))
        ->assertOk()
        ->assertJsonFragment(['code' => 'unconnected_output'])
        ->assertJsonFragment(['code' => 'unreachable']);
});
