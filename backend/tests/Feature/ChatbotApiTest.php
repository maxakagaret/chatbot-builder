<?php

use App\Chatbot\Scenarios\StarterScenario;
use App\Models\ChannelBinding;
use App\Models\Chatbot;

it('creates a bot with a starter graph', function () {
    $this->postJson('/api/chatbots', ['name' => 'Support bot'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Support bot')
        ->assertJsonPath('data.graph.nodes.0.type', 'start')
        ->assertJsonPath('data.published_version', null);
});

it('saves a draft with issues instead of rejecting it', function () {
    $bot = Chatbot::factory()->create();

    $this->putJson("/api/chatbots/{$bot->id}/graph", ['graph' => graph([node('m', 'message', ['text' => 'hi'])], [])])
        ->assertOk()
        ->assertJsonPath('data.issues.0.code', 'no_start');

    expect($bot->refresh()->draft_graph['nodes'][0]['id'])->toBe('m');
});

it('rejects structurally broken graphs', function () {
    $bot = Chatbot::factory()->create();

    $this->putJson("/api/chatbots/{$bot->id}/graph", ['graph' => ['nodes' => [['id' => 'x', 'type' => 'teleport']], 'edges' => []]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['graph.nodes.0.type', 'graph.nodes.0.position.x']);
});

it('refuses to publish a draft with errors and lists them', function () {
    $bot = Chatbot::factory()->graph(graph([node('s', 'start')], []))->create();

    $this->postJson("/api/chatbots/{$bot->id}/publish")
        ->assertUnprocessable()
        ->assertJsonPath('issues.0.code', 'unconnected_output');

    expect($bot->refresh()->published_version_id)->toBeNull();
});

it('publishes incrementing immutable versions', function () {
    $bot = Chatbot::factory()->create();

    $this->postJson("/api/chatbots/{$bot->id}/publish")->assertOk()->assertJsonPath('data.version', 1);
    $this->postJson("/api/chatbots/{$bot->id}/publish")->assertOk()->assertJsonPath('data.version', 2);

    $this->getJson("/api/chatbots/{$bot->id}/versions")
        ->assertOk()
        ->assertJsonPath('data.0.version', 2)
        ->assertJsonPath('data.0.is_live', true)
        ->assertJsonPath('data.1.is_live', false);
});

it('flags unpublished changes', function () {
    $bot = publishedBot();
    $this->getJson("/api/chatbots/{$bot->id}")->assertJsonPath('data.has_unpublished_changes', false);

    $bot->update(['draft_graph' => StarterScenario::minimal()]);
    $this->getJson("/api/chatbots/{$bot->id}")->assertJsonPath('data.has_unpublished_changes', true);
});

it('moves a channel from one bot to another', function () {
    $first = Chatbot::factory()->create();
    $second = Chatbot::factory()->create();

    $this->putJson("/api/chatbots/{$first->id}/channels", ['channels' => ['telegram', 'web']])->assertOk();
    $this->putJson("/api/chatbots/{$second->id}/channels", ['channels' => ['telegram']])
        ->assertOk()
        ->assertJsonPath('data.channels', ['telegram']);

    expect(ChannelBinding::find('telegram')->chatbot_id)->toBe($second->id)
        ->and(ChannelBinding::find('web')->chatbot_id)->toBe($first->id);
});

it('simulates the draft and returns the wire format of the chosen channel', function () {
    $bot = Chatbot::factory()->create();

    $response = $this->postJson("/api/chatbots/{$bot->id}/simulate", [
        'channel' => 'telegram', 'tester_id' => 'me', 'text' => 'hi',
    ])->assertOk();

    expect($response->json('data.session.current_node_id'))->toBe('menu')
        ->and($response->json('data.replies.1.wire.reply_markup.inline_keyboard.0.0'))->toBe(['text' => 'Book a visit', 'callback_data' => 'book']);

    $this->postJson("/api/chatbots/{$bot->id}/simulate", [
        'channel' => 'telegram', 'tester_id' => 'me', 'text' => '', 'button_id' => 'prices',
    ])->assertOk()->assertJsonPath('data.session.current_node_id', 'after_prices');

    $this->postJson("/api/chatbots/{$bot->id}/simulate/reset", ['tester_id' => 'me'])->assertOk();
    $this->postJson("/api/chatbots/{$bot->id}/simulate", ['channel' => 'telegram', 'tester_id' => 'me', 'text' => 'x'])
        ->assertJsonPath('data.session.current_node_id', 'menu');
});

it('exposes editor metadata from the backend registries', function () {
    $this->getJson('/api/meta')
        ->assertOk()
        ->assertJsonFragment(['key' => 'book_appointment'])
        ->assertJsonFragment(['key' => 'contact.visits'])
        ->assertJsonFragment(['type' => 'question', 'waiting' => true, 'handles' => []]);
});

it('lists sessions and shows a transcript', function () {
    $bot = publishedBot();
    say($bot, 'hi');
    $session = say($bot, 'prices')->session;

    $this->getJson("/api/chatbots/{$bot->id}/sessions")
        ->assertOk()
        ->assertJsonPath('data.0.id', $session->id)
        ->assertJsonPath('data.0.version', 1);

    $this->getJson("/api/sessions/{$session->id}")
        ->assertOk()
        ->assertJsonCount(6, 'data.messages')
        ->assertJsonPath('data.messages.0.text', 'hi');
});
