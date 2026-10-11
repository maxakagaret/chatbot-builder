<?php

namespace App\Chatbot\Actions\Handlers;

use App\Chatbot\Actions\ActionHandler;
use App\Models\ConversationSession;

class SetVariable implements ActionHandler
{
    public function key(): string
    {
        return 'set_variable';
    }

    public function label(): string
    {
        return 'Set variable';
    }

    public function params(): array
    {
        return [
            'name' => ['label' => 'Variable name', 'required' => true],
            'value' => ['label' => 'Value (templates allowed)', 'required' => true],
        ];
    }

    public function validate(array $params): array
    {
        return preg_match('/^[a-z][a-z0-9_]{0,31}$/', (string) ($params['name'] ?? '')) === 1
            ? []
            : ['Variable name must be snake_case.'];
    }

    public function handle(ConversationSession $session, array $params): ?string
    {
        $session->variables = [...($session->variables ?? []), $params['name'] => (string) ($params['value'] ?? '')];

        return null;
    }
}
