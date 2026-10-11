<?php

namespace App\Chatbot\Actions\Handlers;

use App\Chatbot\Actions\ActionHandler;
use App\Models\ConversationSession;

class AddTag implements ActionHandler
{
    public function key(): string
    {
        return 'add_tag';
    }

    public function label(): string
    {
        return 'Tag the contact';
    }

    public function params(): array
    {
        return ['tag' => ['label' => 'Tag', 'required' => true]];
    }

    public function validate(array $params): array
    {
        return trim((string) ($params['tag'] ?? '')) === '' ? ['Tag is required.'] : [];
    }

    public function handle(ConversationSession $session, array $params): ?string
    {
        $session->contact->addTag(mb_strtolower(trim($params['tag'])));

        return null;
    }
}
