<?php

namespace App\Chatbot\Conditions;

use App\Models\ConversationSession;
use Carbon\CarbonImmutable;

/**
 * Data a condition can look at: the contact's CRM attributes, variables the
 * user entered earlier in the conversation (vars.*), and the clock.
 */
class FieldResolver
{
    /** Fields the editor offers in its dropdown, with a human label. */
    public const FIELDS = [
        'contact.name' => 'Contact name',
        'contact.visits' => 'Number of visits',
        'contact.tags' => 'Contact tags',
        'contact.email' => 'Contact email',
        'contact.phone' => 'Contact phone',
        'session.channel' => 'Channel',
        'time.hour' => 'Hour of day (0–23)',
        'time.weekday' => 'Weekday (1 = Monday … 7 = Sunday)',
    ];

    public static function isKnown(string $field): bool
    {
        return isset(self::FIELDS[$field]) || preg_match('/^vars\.[a-z][a-z0-9_]{0,31}$/', $field) === 1;
    }

    public function resolve(string $field, ConversationSession $session, ?CarbonImmutable $now = null): mixed
    {
        $now ??= CarbonImmutable::now();
        $contact = $session->contact;

        if (str_starts_with($field, 'vars.')) {
            return $session->variables[substr($field, 5)] ?? null;
        }

        return match ($field) {
            'contact.name' => $contact->name,
            'contact.visits' => $contact->visits,
            'contact.tags' => $contact->tags ?? [],
            'contact.email' => $contact->email ?? ($session->variables['email'] ?? null),
            'contact.phone' => $contact->phone,
            'session.channel' => $session->channel->value,
            'time.hour' => $now->hour,
            'time.weekday' => $now->dayOfWeekIso,
            default => null,
        };
    }
}
