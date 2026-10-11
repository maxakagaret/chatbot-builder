<?php

namespace App\Chatbot\Channels;

use App\Chatbot\Engine\InboundMessage;
use App\Chatbot\Engine\Reply;
use App\Enums\ChannelType;

/**
 * The only channel-specific code: parse the provider's webhook payload into an
 * InboundMessage, and turn a neutral Reply into the provider's wire format
 * (buttons become an inline keyboard, a numbered list, or JSON for the widget).
 */
interface ChannelAdapter
{
    public function channel(): ChannelType;

    /** @return array<string, mixed> Laravel validation rules for the webhook payload */
    public function rules(): array;

    public function parse(array $payload): InboundMessage;

    /** @return array<string, mixed> provider request body */
    public function format(Reply $reply, string $externalId): array;
}
