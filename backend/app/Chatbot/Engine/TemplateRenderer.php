<?php

namespace App\Chatbot\Engine;

use App\Models\ConversationSession;

/**
 * {{contact.first_name}}, {{contact.name}}, {{vars.email}} … in step texts and
 * action parameters. Unknown placeholders render as an empty string; output is
 * plain text — channel adapters escape it for their own markup.
 */
class TemplateRenderer
{
    public function render(string $template, ConversationSession $session): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\.([a-z0-9_]+)\s*\}\}/i', function (array $m) use ($session) {
            return (string) match ($m[1]) {
                'contact' => match ($m[2]) {
                    'first_name' => $session->contact->firstName(),
                    'name' => $session->contact->name,
                    'visits' => $session->contact->visits,
                    default => '',
                },
                'vars' => is_scalar($session->variables[$m[2]] ?? null) ? $session->variables[$m[2]] : '',
                default => '',
            };
        }, $template);
    }

    /** Renders every string value of an action's parameters. */
    public function renderParams(array $params, ConversationSession $session): array
    {
        return array_map(fn ($v) => is_string($v) ? $this->render($v, $session) : $v, $params);
    }
}
