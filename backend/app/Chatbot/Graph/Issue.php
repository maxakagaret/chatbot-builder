<?php

namespace App\Chatbot\Graph;

use JsonSerializable;

final readonly class Issue implements JsonSerializable
{
    public const ERROR = 'error';
    public const WARNING = 'warning';

    public function __construct(
        public string $severity,
        public string $code,
        public string $message,
        public ?string $nodeId = null,
    ) {}

    public function jsonSerialize(): array
    {
        return ['severity' => $this->severity, 'code' => $this->code, 'message' => $this->message, 'node_id' => $this->nodeId];
    }
}
