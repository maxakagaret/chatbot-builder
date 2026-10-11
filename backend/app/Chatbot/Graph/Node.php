<?php

namespace App\Chatbot\Graph;

use App\Enums\NodeType;

final readonly class Node
{
    public function __construct(
        public string $id,
        public NodeType $type,
        public array $data,
    ) {}

    /** @return list<string> every output handle this node exposes */
    public function handles(): array
    {
        if ($this->type === NodeType::Question) {
            return array_map(fn (array $o) => 'option:'.$o['id'], $this->options());
        }

        return $this->type->staticHandles();
    }

    /** @return list<array{id: string, label: string, synonyms?: list<string>}> */
    public function options(): array
    {
        return array_values($this->data['options'] ?? []);
    }

    public function text(): string
    {
        return (string) ($this->data['text'] ?? '');
    }
}
