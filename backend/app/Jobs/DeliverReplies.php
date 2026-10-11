<?php

namespace App\Jobs;

use App\Chatbot\Channels\ChannelRegistry;
use App\Chatbot\Contracts\OutboundGateway;
use App\Chatbot\Engine\Reply;
use App\Enums\ChannelType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Asynchronous channels (Telegram, WhatsApp, SMS) get their replies through the
 * queue, so the provider's webhook is acknowledged immediately and a slow or
 * failing provider API is retried without re-running the conversation logic.
 * Replies of one turn stay in one job to keep their order.
 */
class DeliverReplies implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 5;

    /** @param list<array{text: string, buttons: list<array{id: string, label: string}>, node_id: ?string}> $replies */
    public function __construct(
        public readonly ChannelType $channel,
        public readonly string $externalId,
        public readonly array $replies,
    ) {}

    public function backoff(): array
    {
        return [5, 30, 120, 600];
    }

    public function handle(ChannelRegistry $channels, OutboundGateway $gateway): void
    {
        $adapter = $channels->for($this->channel);

        foreach ($this->replies as $reply) {
            $gateway->send($this->channel, $this->externalId, $adapter->format(
                new Reply($reply['text'], $reply['buttons'], $reply['node_id']),
                $this->externalId,
            ));
        }
    }
}
