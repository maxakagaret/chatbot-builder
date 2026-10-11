<?php

namespace App\Chatbot\Actions\Handlers;

use App\Chatbot\Actions\ActionHandler;
use App\Models\ConversationSession;

class OptOut implements ActionHandler
{
    public function key(): string
    {
        return 'opt_out';
    }

    public function label(): string
    {
        return 'Unsubscribe from marketing';
    }

    public function params(): array
    {
        return [];
    }

    public function validate(array $params): array
    {
        return [];
    }

    public function handle(ConversationSession $session, array $params): ?string
    {
        $session->contact->forceFill(['opted_out' => true])->save();

        return null;
    }
}
