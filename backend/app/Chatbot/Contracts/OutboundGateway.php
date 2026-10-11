<?php

namespace App\Chatbot\Contracts;

use App\Enums\ChannelType;

/** Sends an already formatted provider payload. The demo binds FakeOutboundGateway. */
interface OutboundGateway
{
    public function send(ChannelType $channel, string $externalId, array $payload): void;
}
