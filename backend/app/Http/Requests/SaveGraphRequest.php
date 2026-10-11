<?php

namespace App\Http\Requests;

use App\Enums\NodeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Structural validation of the graph JSON (shape and types). Semantic checks —
 * reachability, loops, dangling edges — live in GraphValidator and are returned
 * as issues instead of 422s, so a half-finished draft can still be saved.
 */
class SaveGraphRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'graph' => ['required', 'array'],
            'graph.nodes' => ['present', 'array', 'max:300'],
            'graph.nodes.*.id' => ['required', 'string', 'max:64'],
            'graph.nodes.*.type' => ['required', Rule::enum(NodeType::class)],
            'graph.nodes.*.position.x' => ['required', 'numeric'],
            'graph.nodes.*.position.y' => ['required', 'numeric'],
            'graph.nodes.*.data' => ['present', 'array'],
            'graph.nodes.*.data.text' => ['nullable', 'string', 'max:2000'],
            'graph.nodes.*.data.options' => ['sometimes', 'array', 'max:10'],
            'graph.nodes.*.data.options.*.id' => ['required', 'string', 'max:64'],
            'graph.nodes.*.data.options.*.label' => ['required', 'string', 'max:64'],
            'graph.nodes.*.data.options.*.synonyms' => ['sometimes', 'array', 'max:20'],
            'graph.nodes.*.data.options.*.synonyms.*' => ['string', 'max:64'],
            'graph.nodes.*.data.rules' => ['sometimes', 'array', 'max:10'],
            'graph.nodes.*.data.params' => ['sometimes', 'array'],
            'graph.edges' => ['present', 'array', 'max:600'],
            'graph.edges.*.id' => ['nullable', 'string', 'max:128'],
            'graph.edges.*.source' => ['required', 'string', 'max:64'],
            'graph.edges.*.sourceHandle' => ['required', 'string', 'max:80'],
            'graph.edges.*.target' => ['required', 'string', 'max:64'],
        ];
    }

    /** Only the keys the engine and editor need — no arbitrary client blobs stored. */
    public function graph(): array
    {
        $graph = $this->input('graph');

        return [
            'nodes' => array_map(fn (array $n) => [
                'id' => $n['id'],
                'type' => $n['type'],
                'position' => ['x' => (float) $n['position']['x'], 'y' => (float) $n['position']['y']],
                'data' => $n['data'] ?? [],
            ], $graph['nodes'] ?? []),
            'edges' => array_map(fn (array $e) => [
                'id' => $e['id'] ?? "{$e['source']}:{$e['sourceHandle']}",
                'source' => $e['source'],
                'sourceHandle' => $e['sourceHandle'],
                'target' => $e['target'],
            ], $graph['edges'] ?? []),
        ];
    }
}
