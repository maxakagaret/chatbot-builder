<?php

namespace App\Chatbot\Channels;

use App\Enums\ChannelType;

class ChannelRegistry
{
    /** @var array<string, ChannelAdapter> */
    private array $adapters = [];

    /** @param iterable<ChannelAdapter> $adapters */
    public function __construct(iterable $adapters)
    {
        foreach ($adapters as $adapter) {
            $this->adapters[$adapter->channel()->value] = $adapter;
        }
    }

    public function for(ChannelType $channel): ChannelAdapter
    {
        return $this->adapters[$channel->value];
    }
}
