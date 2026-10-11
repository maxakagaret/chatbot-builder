<?php

namespace App\Chatbot\Graph;

use App\Chatbot\Actions\ActionRegistry;
use App\Chatbot\Conditions\FieldResolver;
use App\Chatbot\Engine\InputValidator;
use App\Enums\NodeType;
use App\Enums\Operator;
use InvalidArgumentException;

/**
 * Semantic checks on top of the structural FormRequest validation. The editor
 * shows the issues on the canvas; publishing is blocked while any error is left.
 *
 * Errors make the scenario unrunnable or ambiguous (dangling edges, a branch
 * without a target, a loop that never waits for the user). Warnings are legal
 * but probably unintended (unreachable steps, options leading nowhere).
 */
class GraphValidator
{
    public function __construct(private readonly ActionRegistry $actions) {}

    /** @return list<Issue> */
    public function validate(array $json): array
    {
        try {
            $graph = Graph::fromArray($json);
        } catch (InvalidArgumentException $e) {
            return [new Issue(Issue::ERROR, 'malformed', $e->getMessage())];
        }

        return [
            ...$this->checkUniqueIds($json),
            ...$this->checkStart($graph),
            ...$this->checkEdges($graph),
            ...$this->checkNodeData($graph),
            ...$this->checkReachability($graph),
            ...$this->checkLoops($graph),
        ];
    }

    /** @param list<Issue> $issues */
    public static function hasErrors(array $issues): bool
    {
        return collect($issues)->contains(fn (Issue $i) => $i->severity === Issue::ERROR);
    }

    private function checkUniqueIds(array $json): array
    {
        $ids = array_column($json['nodes'] ?? [], 'id');
        $duplicates = array_keys(array_filter(array_count_values(array_map('strval', $ids)), fn ($n) => $n > 1));

        return array_map(fn ($id) => new Issue(Issue::ERROR, 'duplicate_node', "Node id \"{$id}\" is used more than once.", $id), $duplicates);
    }

    private function checkStart(Graph $graph): array
    {
        $starts = array_filter($graph->nodes(), fn (Node $n) => $n->type === NodeType::Start);

        return match (count($starts)) {
            0 => [new Issue(Issue::ERROR, 'no_start', 'The scenario needs a start step.')],
            1 => [],
            default => [new Issue(Issue::ERROR, 'multiple_starts', 'Only one start step is allowed.')],
        };
    }

    private function checkEdges(Graph $graph): array
    {
        $issues = [];
        $seen = [];

        foreach ($graph->rawEdges() as $edge) {
            $source = $graph->node($edge['source']);
            if (! $source || ! $graph->node($edge['target'])) {
                $issues[] = new Issue(Issue::ERROR, 'dangling_edge', 'A connection points to a step that does not exist.', $edge['source'] ?: null);
                continue;
            }

            if (! in_array($edge['sourceHandle'], $source->handles(), true)) {
                $issues[] = new Issue(Issue::ERROR, 'unknown_handle', "Step has no output \"{$edge['sourceHandle']}\".", $source->id);
                continue;
            }

            $key = $edge['source'].'|'.$edge['sourceHandle'];
            if (isset($seen[$key])) {
                $issues[] = new Issue(Issue::ERROR, 'ambiguous_output', "Output \"{$edge['sourceHandle']}\" is connected twice.", $source->id);
            }
            $seen[$key] = true;
        }

        foreach ($graph->nodes() as $node) {
            $connected = $graph->outgoing($node->id);
            foreach ($node->handles() as $handle) {
                if (isset($connected[$handle])) {
                    continue;
                }
                $issues[] = match (true) {
                    $node->type === NodeType::Condition, $node->type === NodeType::Start =>
                        new Issue(Issue::ERROR, 'unconnected_output', "Output \"{$handle}\" must be connected.", $node->id),
                    $node->type === NodeType::Question =>
                        new Issue(Issue::WARNING, 'unconnected_option', 'This option ends the conversation.', $node->id),
                    default =>
                        new Issue(Issue::WARNING, 'conversation_ends', 'The conversation ends after this step.', $node->id),
                };
            }
        }

        return $issues;
    }

    private function checkNodeData(Graph $graph): array
    {
        $issues = [];

        foreach ($graph->nodes() as $node) {
            // A regular closure: arrow functions capture by value and could not append.
            $error = function (string $code, string $message) use (&$issues, $node): void {
                $issues[] = new Issue(Issue::ERROR, $code, $message, $node->id);
            };

            if (in_array($node->type, [NodeType::Message, NodeType::Question, NodeType::Input], true) && trim($node->text()) === '') {
                $error('empty_text', 'Text is required.');
            }

            if ($node->type === NodeType::Question) {
                $options = $node->options();
                $labels = array_map(fn ($o) => mb_strtolower(trim((string) ($o['label'] ?? ''))), $options);
                $ids = array_map(fn ($o) => (string) ($o['id'] ?? ''), $options);

                if (count($options) === 0) {
                    $error('no_options', 'A question needs at least one option.');
                } elseif (in_array('', $labels, true) || in_array('', $ids, true)) {
                    $error('empty_option', 'Every option needs an id and a label.');
                } elseif (count(array_unique($labels)) !== count($labels) || count(array_unique($ids)) !== count($ids)) {
                    $error('duplicate_option', 'Options must be unique.');
                }
            }

            if ($node->type === NodeType::Input) {
                if (! preg_match('/^[a-z][a-z0-9_]{0,31}$/', (string) ($node->data['variable'] ?? ''))) {
                    $error('bad_variable', 'Variable name must be snake_case, e.g. "email".');
                }
                if (! in_array($node->data['validation'] ?? 'text', InputValidator::TYPES, true)) {
                    $error('bad_validation', 'Unknown input validation.');
                }
            }

            if ($node->type === NodeType::Condition) {
                $rules = $node->data['rules'] ?? [];
                if ($rules === []) {
                    $error('no_rules', 'A condition needs at least one rule.');
                }
                foreach ($rules as $rule) {
                    $operator = Operator::tryFrom((string) ($rule['operator'] ?? ''));
                    if (! FieldResolver::isKnown((string) ($rule['field'] ?? ''))) {
                        $error('bad_field', 'Unknown field "'.($rule['field'] ?? '').'".');
                    } elseif (! $operator) {
                        $error('bad_operator', 'Unknown operator.');
                    } elseif ($operator->needsValue() && ($rule['value'] ?? '') === '') {
                        $error('missing_value', "Rule on \"{$rule['field']}\" needs a value.");
                    }
                }
            }

            if ($node->type === NodeType::Action) {
                $type = (string) ($node->data['action'] ?? '');
                if (! $this->actions->has($type)) {
                    $error('bad_action', "Unknown action \"{$type}\".");
                } else {
                    foreach ($this->actions->get($type)->validate((array) ($node->data['params'] ?? [])) as $message) {
                        $error('bad_action_params', $message);
                    }
                }
            }
        }

        return $issues;
    }

    private function checkReachability(Graph $graph): array
    {
        $start = $graph->start();
        if (! $start) {
            return [];
        }

        $visited = [$start->id => true];
        $queue = [$start->id];
        while ($queue) {
            foreach ($graph->outgoing(array_shift($queue)) as $target) {
                if (! isset($visited[$target]) && $graph->node($target)) {
                    $visited[$target] = true;
                    $queue[] = $target;
                }
            }
        }

        return collect($graph->nodes())
            ->reject(fn (Node $n) => isset($visited[$n->id]))
            ->map(fn (Node $n) => new Issue(Issue::WARNING, 'unreachable', 'This step can never be reached.', $n->id))
            ->values()->all();
    }

    /**
     * A cycle is fine as long as it passes through a waiting step (the user
     * answers on every lap). A cycle made only of instant steps would spin the
     * engine forever, so it is an error — found with DFS over instant steps only.
     */
    private function checkLoops(Graph $graph): array
    {
        $state = [];
        $issues = [];

        $visit = function (string $id) use (&$visit, &$state, &$issues, $graph): void {
            $state[$id] = 'open';
            foreach ($graph->outgoing($id) as $target) {
                $node = $graph->node($target);
                if (! $node || $node->type->isWaiting()) {
                    continue;
                }
                if (($state[$target] ?? null) === 'open') {
                    $issues[$target] = new Issue(Issue::ERROR, 'infinite_loop', 'Loop without a question or input: the bot would spin forever.', $target);
                } elseif (! isset($state[$target])) {
                    $visit($target);
                }
            }
            $state[$id] = 'done';
        };

        foreach ($graph->nodes() as $node) {
            if (! $node->type->isWaiting() && ! isset($state[$node->id])) {
                $visit($node->id);
            }
        }

        return array_values($issues);
    }
}
