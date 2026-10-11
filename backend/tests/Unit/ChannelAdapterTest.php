<?php

use App\Chatbot\Channels\SmsAdapter;
use App\Chatbot\Channels\TelegramAdapter;
use App\Chatbot\Channels\WebAdapter;
use App\Chatbot\Channels\WhatsAppAdapter;
use App\Chatbot\Engine\Reply;
use App\Enums\ChannelType;

$reply = new Reply('Pick one <please>', [['id' => 'a', 'label' => 'Yes'], ['id' => 'b', 'label' => 'No']]);

it('renders buttons as an inline keyboard in Telegram and escapes HTML', function () use ($reply) {
    $body = (new TelegramAdapter)->format($reply, '42');

    expect($body['chat_id'])->toBe('42')
        ->and($body['text'])->toBe('Pick one &lt;please&gt;')
        ->and($body['reply_markup']['inline_keyboard'])->toBe([
            [['text' => 'Yes', 'callback_data' => 'a']],
            [['text' => 'No', 'callback_data' => 'b']],
        ]);
});

it('renders buttons as a numbered list in WhatsApp', function () use ($reply) {
    $body = (new WhatsAppAdapter)->format($reply, '+15550100001');

    expect($body['text']['body'])->toContain("1. Yes\n2. No")->toContain('Reply with a number');
});

it('keeps SMS compact, emoji-free and within three segments', function () {
    $long = new Reply(str_repeat('Long text 🙂 ', 80), [['id' => 'a', 'label' => 'Yes']]);
    $body = (new SmsAdapter)->format($long, '+15550100001');

    expect(mb_strlen($body['text']))->toBeLessThanOrEqual(459)
        ->and($body['text'])->not->toContain('🙂');
});

it('passes buttons through as JSON for the web widget', function () use ($reply) {
    expect((new WebAdapter)->format($reply, 'v1'))->toBe(['text' => 'Pick one <please>', 'buttons' => $reply->buttons]);
});

it('parses Telegram callbacks into a button payload', function () {
    $inbound = (new TelegramAdapter)->parse(['callback_query' => [
        'data' => 'book', 'from' => ['first_name' => 'Ada'], 'message' => ['chat' => ['id' => 7], 'text' => 'menu'],
    ]]);

    expect($inbound->channel)->toBe(ChannelType::Telegram)
        ->and($inbound->externalId)->toBe('7')
        ->and($inbound->payload)->toBe('book')
        ->and($inbound->name)->toBe('Ada');
});
