<?php

namespace App\Chatbot\Engine;

use JsonSerializable;

/** Channel-neutral bot reply. Channel adapters decide how buttons look on the wire. */
final readonly class Reply implements JsonSerializable
{
    /** @param list<array{id: string, label: string}> $buttons */
    public function __construct(
        public string $text,
        public array $buttons = [],
        public ?string $nodeId = null,
    ) {}

    public function jsonSerialize(): array
    {
        return ['text' => $this->text, 'buttons' => $this->buttons, 'node_id' => $this->nodeId];
    }
}
