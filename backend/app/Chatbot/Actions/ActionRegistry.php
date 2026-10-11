<?php

namespace App\Chatbot\Actions;

use InvalidArgumentException;

class ActionRegistry
{
    /** @var array<string, ActionHandler> */
    private array $handlers = [];

    /** @param iterable<ActionHandler> $handlers */
    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $handler) {
            $this->handlers[$handler->key()] = $handler;
        }
    }

    public function has(string $key): bool
    {
        return isset($this->handlers[$key]);
    }

    public function get(string $key): ActionHandler
    {
        return $this->handlers[$key] ?? throw new InvalidArgumentException("Unknown action [{$key}].");
    }

    /** @return array<string, ActionHandler> */
    public function all(): array
    {
        return $this->handlers;
    }
}
