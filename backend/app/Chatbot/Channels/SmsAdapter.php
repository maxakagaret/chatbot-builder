<?php

namespace App\Chatbot\Channels;

use App\Chatbot\Engine\InboundMessage;
use App\Chatbot\Engine\Reply;
use App\Enums\ChannelType;

/** SMS: compact numbered options, GSM-friendly (no emoji), long texts are cut. */
class SmsAdapter implements ChannelAdapter
{
    private const MAX_LENGTH = 459; // three concatenated GSM-7 segments

    public function channel(): ChannelType
    {
        return ChannelType::Sms;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:32'],
            'text' => ['present', 'nullable', 'string', 'max:1600'],
        ];
    }

    public function parse(array $payload): InboundMessage
    {
        return new InboundMessage(ChannelType::Sms, (string) $payload['phone'], (string) ($payload['text'] ?? ''));
    }

    public function format(Reply $reply, string $externalId): array
    {
        $text = $reply->text;

        if ($reply->buttons) {
            $options = array_map(fn (int $i, array $b) => ($i + 1).'-'.$b['label'], array_keys($reply->buttons), $reply->buttons);
            $text .= ' Reply '.implode(', ', $options);
        }

        $text = trim(preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $text));

        return ['phone' => $externalId, 'text' => mb_strimwidth($text, 0, self::MAX_LENGTH, '…')];
    }
}
