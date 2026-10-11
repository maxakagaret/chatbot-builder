<?php

use App\Jobs\DeliverReplies;
use App\Models\ChannelBinding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

function webhook(string $channel, array $payload, ?string $secret = null): TestResponse
{
    $body = json_encode($payload);

    return test()->call('POST', "/api/channels/{$channel}/webhook", [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, $secret ?? config('chatbot.webhook_secret')),
    ], $body);
}

beforeEach(function () {
    $this->bot = publishedBot();
    foreach (['web', 'telegram', 'whatsapp'] as $channel) {
        ChannelBinding::create(['channel' => $channel, 'chatbot_id' => $this->bot->id]);
    }
});

it('answers the web widget synchronously without a signature', function () {
    $this->postJson('/api/channels/web/webhook', ['visitor_id' => 'v-1', 'text' => 'hi'])
        ->assertOk()
        ->assertJsonPath('replies.1.text', 'What can I do for you?')
        ->assertJsonPath('replies.1.buttons.1.id', 'prices');
});

it('rejects unsigned messenger webhooks', function () {
    webhook('telegram', ['message' => ['chat' => ['id' => 1], 'text' => 'hi']], 'wrong')->assertUnauthorized();
});

it('acknowledges Telegram fast and delivers replies through the queue', function () {
    Queue::fake();

    webhook('telegram', ['message' => ['chat' => ['id' => 99], 'text' => 'hi', 'from' => ['first_name' => 'Kim']]])
        ->assertAccepted();

    Queue::assertPushedOn('chatbot', DeliverReplies::class, fn ($job) => $job->externalId === '99' && count($job->replies) === 2);
});

it('formats queued replies in the provider wire format', function () {
    webhook('whatsapp', ['from' => '+15550100009', 'body' => 'hi'])->assertAccepted();

    $payloads = DB::table('outbound_log')->where('channel', 'whatsapp')->pluck('payload')->map(fn ($p) => json_decode($p, true));

    expect($payloads)->toHaveCount(2)
        ->and($payloads[1]['to'])->toBe('+15550100009')
        ->and($payloads[1]['text']['body'])->toContain('1. Book a visit');
});

it('continues the conversation from a Telegram button press', function () {
    webhook('telegram', ['message' => ['chat' => ['id' => 5], 'text' => 'hi']]);
    webhook('telegram', ['callback_query' => ['data' => 'prices', 'message' => ['chat' => ['id' => 5]]]]);

    $last = json_decode(DB::table('outbound_log')->latest('id')->value('payload'), true);

    expect($last['text'])->toBe('Shall I book you in?')
        ->and($last['reply_markup']['inline_keyboard'][0][0]['callback_data'])->toBe('yes');
});

it('acknowledges messages for channels without a published bot', function () {
    ChannelBinding::where('channel', 'web')->delete();

    $this->postJson('/api/channels/web/webhook', ['visitor_id' => 'v', 'text' => 'hi'])
        ->assertAccepted()
        ->assertJsonPath('reason', 'no_published_bot_for_channel');
});

it('validates channel payloads', function () {
    webhook('whatsapp', ['body' => 'no sender'])->assertUnprocessable()->assertJsonValidationErrors('from');
});
