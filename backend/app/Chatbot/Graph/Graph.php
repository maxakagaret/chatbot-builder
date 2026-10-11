<?php

namespace App\Chatbot\Graph;

use App\Enums\NodeType;
use InvalidArgumentException;

/**
 * Immutable, indexed view of a scenario graph.
 *
 * The JSON format is the same one the visual editor (Vue Flow) works with:
 *
 *   {
 *     "nodes": [{"id": "q1", "type": "question", "position": {"x": 0, "y": 0},
 *                "data": {"text": "...", "options": [{"id": "yes", "label": "Yes"}]}}],
 *     "edges": [{"id": "e1", "source": "q1", "sourceHandle": "option:yes", "target": "n2"}]
 *   }
 *
 * Positions are presentation-only and ignored by the engine.
 */
final class Graph
{
    /** @var array<string, Node> */
    private array $nodes = [];

    /** @var array<string, array<string, string>> source node → handle → target node */
    private array $edges = [];

    /** @var list<array{id: ?string, source: string, sourceHandle: string, target: string}> */
    private array $rawEdges = [];

    public function __construct(private readonly array $json)
    {
        foreach ($json['nodes'] ?? [] as $node) {
            $type = NodeType::tryFrom((string) ($node['type'] ?? ''));
            if (! isset($node['id']) || ! $type) {
                throw new InvalidArgumentException('Every node needs an id and a known type.');
            }
            $this->nodes[(string) $node['id']] = new Node((string) $node['id'], $type, (array) ($node['data'] ?? []));
        }

        foreach ($json['edges'] ?? [] as $edge) {
            $source = (string) ($edge['source'] ?? '');
            $handle = (string) ($edge['sourceHandle'] ?? 'next');
            $target = (string) ($edge['target'] ?? '');
            $this->rawEdges[] = ['id' => $edge['id'] ?? null, 'source' => $source, 'sourceHandle' => $handle, 'target' => $target];
            $this->edges[$source][$handle] ??= $target;
        }
    }

    public static function fromArray(array $json): self
    {
        return new self($json);
    }

    public function toArray(): array
    {
        return $this->json;
    }

    public function node(string $id): ?Node
    {
        return $this->nodes[$id] ?? null;
    }

    /** @return array<string, Node> */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /** @return list<array{id: ?string, source: string, sourceHandle: string, target: string}> */
    public function rawEdges(): array
    {
        return $this->rawEdges;
    }

    public function start(): ?Node
    {
        foreach ($this->nodes as $node) {
            if ($node->type === NodeType::Start) {
                return $node;
            }
        }

        return null;
    }

    public function next(Node $from, string $handle): ?Node
    {
        $target = $this->edges[$from->id][$handle] ?? null;

        return $target !== null ? $this->node($target) : null;
    }

    /** @return array<string, string> handle → target id */
    public function outgoing(string $nodeId): array
    {
        return $this->edges[$nodeId] ?? [];
    }
}
