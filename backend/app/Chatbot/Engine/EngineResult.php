<?php

namespace App\Chatbot\Engine;

use App\Models\ConversationSession;

final readonly class EngineResult
{
    /** @param list<Reply> $replies */
    public function __construct(
        public ConversationSession $session,
        public array $replies,
    ) {}
}
