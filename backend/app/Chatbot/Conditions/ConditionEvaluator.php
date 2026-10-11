<?php

namespace App\Chatbot\Conditions;

use App\Enums\Operator;
use App\Models\ConversationSession;

/** Evaluates a condition node: all rules (AND) or any rule (OR). */
class ConditionEvaluator
{
    public function __construct(private readonly FieldResolver $fields) {}

    /** @param array{rules?: list<array{field: string, operator: string, value?: string}>, match?: string} $data */
    public function passes(array $data, ConversationSession $session): bool
    {
        $results = array_map(
            fn (array $rule) => Operator::from($rule['operator'])->compare(
                $this->fields->resolve($rule['field'], $session),
                isset($rule['value']) ? (string) $rule['value'] : null,
            ),
            $data['rules'] ?? [],
        );

        if ($results === []) {
            return false;
        }

        return ($data['match'] ?? 'all') === 'any'
            ? in_array(true, $results, true)
            : ! in_array(false, $results, true);
    }
}
