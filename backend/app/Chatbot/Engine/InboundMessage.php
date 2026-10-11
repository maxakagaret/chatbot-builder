<?php

namespace App\Chatbot\Engine;

use App\Enums\ChannelType;

/** Normalised incoming message: what every channel adapter parses its webhook into. */
final readonly class InboundMessage
{
    public function __construct(
        public ChannelType $channel,
        public string $externalId,
        public string $text,
        public ?string $payload = null, // id of a pressed button, when the channel supports buttons
        public ?string $name = null,
    ) {}
}
