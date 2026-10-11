<?php

namespace App\Chatbot\Actions\Handlers;

use App\Chatbot\Actions\ActionHandler;
use App\Enums\SessionStatus;
use App\Models\ConversationSession;

/**
 * Passes the conversation to a human. The session switches to "handoff": the
 * engine records further messages but stays silent until an operator closes it.
 */
class Handoff implements ActionHandler
{
    public function key(): string
    {
        return 'handoff';
    }

    public function label(): string
    {
        return 'Hand off to an operator';
    }

    public function params(): array
    {
        return ['note' => ['label' => 'Note for the operator', 'required' => false]];
    }

    public function validate(array $params): array
    {
        return [];
    }

    public function handle(ConversationSession $session, array $params): ?string
    {
        $session->status = SessionStatus::Handoff;
        $session->variables = [...($session->variables ?? []), 'handoff_note' => (string) ($params['note'] ?? '')];

        return null;
    }
}
