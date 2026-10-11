<?php

namespace App\Chatbot\Actions;

use App\Models\ConversationSession;

/**
 * A side effect a scenario can trigger. Handlers are registered in
 * ActionRegistry by key; the editor reads their labels and parameter
 * schema from GET /api/meta, the validator calls validate() on publish.
 */
interface ActionHandler
{
    public function key(): string;

    public function label(): string;

    /** @return array<string, array{label: string, required: bool}> parameter schema for the editor */
    public function params(): array;

    /** @return list<string> human-readable problems with the configured parameters */
    public function validate(array $params): array;

    /**
     * @param array $params parameters with {{templates}} already rendered
     * @return string|null optional text to send to the user
     */
    public function handle(ConversationSession $session, array $params): ?string;
}
