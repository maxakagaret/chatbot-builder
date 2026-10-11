<?php

namespace App\Chatbot\Channels;

use App\Chatbot\Engine\InboundMessage;
use App\Chatbot\Engine\Reply;
use App\Enums\ChannelType;

/** Telegram Bot API shapes: text messages and inline-keyboard callbacks. */
class TelegramAdapter implements ChannelAdapter
{
    public function channel(): ChannelType
    {
        return ChannelType::Telegram;
    }

    public function rules(): array
    {
        return [
            'message' => ['required_without:callback_query', 'array'],
            'message.chat.id' => ['required_with:message'],
            'message.text' => ['nullable', 'string', 'max:4096'],
            'callback_query' => ['required_without:message', 'array'],
            'callback_query.message.chat.id' => ['required_with:callback_query'],
            'callback_query.data' => ['required_with:callback_query', 'string', 'max:64'],
        ];
    }

    public function parse(array $payload): InboundMessage
    {
        if (isset($payload['callback_query'])) {
            $query = $payload['callback_query'];

            return new InboundMessage(
                ChannelType::Telegram,
                (string) $query['message']['chat']['id'],
                (string) ($query['message']['text'] ?? ''),
                (string) $query['data'],
                $query['from']['first_name'] ?? null,
            );
        }

        $message = $payload['message'];

        return new InboundMessage(
            ChannelType::Telegram,
            (string) $message['chat']['id'],
            (string) ($message['text'] ?? ''),
            null,
            $message['from']['first_name'] ?? null,
        );
    }

    public function format(Reply $reply, string $externalId): array
    {
        $body = [
            'chat_id' => $externalId,
            'text' => htmlspecialchars($reply->text, ENT_NOQUOTES),
            'parse_mode' => 'HTML',
        ];

        if ($reply->buttons) {
            $body['reply_markup'] = [
                'inline_keyboard' => array_map(
                    fn (array $b) => [['text' => $b['label'], 'callback_data' => $b['id']]],
                    $reply->buttons,
                ),
            ];
        }

        return $body;
    }
}
