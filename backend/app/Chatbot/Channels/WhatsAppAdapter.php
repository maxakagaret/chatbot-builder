<?php

namespace App\Chatbot\Channels;

use App\Chatbot\Engine\InboundMessage;
use App\Chatbot\Engine\Reply;
use App\Enums\ChannelType;

/**
 * Plain-text messenger without native buttons: options are rendered as a
 * numbered list and the user answers "2" or with the option's wording —
 * AnswerMatcher understands both.
 */
class WhatsAppAdapter implements ChannelAdapter
{
    public function channel(): ChannelType
    {
        return ChannelType::WhatsApp;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'string', 'max:32'],
            'body' => ['present', 'nullable', 'string', 'max:4096'],
            'profile_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function parse(array $payload): InboundMessage
    {
        return new InboundMessage(
            ChannelType::WhatsApp,
            (string) $payload['from'],
            (string) ($payload['body'] ?? ''),
            null,
            $payload['profile_name'] ?? null,
        );
    }

    public function format(Reply $reply, string $externalId): array
    {
        $text = $reply->text;

        if ($reply->buttons) {
            $lines = array_map(fn (int $i, array $b) => ($i + 1).". {$b['label']}", array_keys($reply->buttons), $reply->buttons);
            $text .= "\n\n".implode("\n", $lines)."\n\nReply with a number.";
        }

        return ['to' => $externalId, 'type' => 'text', 'text' => ['body' => $text]];
    }
}
