<?php

namespace App\Chatbot\Fakes;

use App\Chatbot\Contracts\OutboundGateway;
use App\Enums\ChannelType;
use Illuminate\Support\Facades\DB;

/**
 * Writes what would have been POSTed to the provider into outbound_log, so the
 * UI can show the exact wire payload per channel without any real integration.
 */
class FakeOutboundGateway implements OutboundGateway
{
    public function send(ChannelType $channel, string $externalId, array $payload): void
    {
        DB::table('outbound_log')->insert([
            'channel' => $channel->value,
            'external_id' => $externalId,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }
}
