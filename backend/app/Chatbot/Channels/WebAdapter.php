<?php

namespace App\Chatbot\Channels;

use App\Chatbot\Engine\InboundMessage;
use App\Chatbot\Engine\Reply;
use App\Enums\ChannelType;

/** Website chat widget: synchronous request/response, real buttons. */
class WebAdapter implements ChannelAdapter
{
    public function channel(): ChannelType
    {
        return ChannelType::Web;
    }

    public function rules(): array
    {
        return [
            'visitor_id' => ['required', 'string', 'max:64'],
            'text' => ['present', 'nullable', 'string', 'max:2000'],
            'button_id' => ['nullable', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function parse(array $payload): InboundMessage
    {
        return new InboundMessage(
            ChannelType::Web,
            (string) $payload['visitor_id'],
            (string) ($payload['text'] ?? ''),
            $payload['button_id'] ?? null,
            $payload['name'] ?? null,
        );
    }

    public function format(Reply $reply, string $externalId): array
    {
        return ['text' => $reply->text, 'buttons' => $reply->buttons];
    }
}
