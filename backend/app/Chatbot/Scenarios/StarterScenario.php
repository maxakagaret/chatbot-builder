<?php

namespace App\Chatbot\Scenarios;

/** Ready-made graphs: an empty starter for new bots and a full demo scenario. */
final class StarterScenario
{
    public static function minimal(): array
    {
        return [
            'nodes' => [
                ['id' => 'start', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'hello', 'type' => 'message', 'position' => ['x' => 0, 'y' => 120], 'data' => ['text' => 'Hi {{contact.first_name}}! How can I help?']],
            ],
            'edges' => [
                ['id' => 'start:next', 'source' => 'start', 'sourceHandle' => 'next', 'target' => 'hello'],
            ],
        ];
    }

    /**
     * Appointment assistant: greets regulars differently, books a visit
     * (service → contact email → booking action), answers price questions,
     * hands off to a human or unsubscribes.
     */
    public static function appointmentAssistant(): array
    {
        // Coordinates are a grid sketch; scaled so tall steps do not overlap on the canvas.
        $n = fn (string $id, string $type, int $x, int $y, array $data = []) => compact('id', 'type')
            + ['position' => ['x' => (int) round($x * 1.15), 'y' => (int) round($y * 1.45)], 'data' => $data];
        $e = fn (string $source, string $handle, string $target) => ['id' => "{$source}:{$handle}", 'source' => $source, 'sourceHandle' => $handle, 'target' => $target];

        return [
            'nodes' => [
                $n('start', 'start', 380, 0),
                $n('is_regular', 'condition', 380, 110, ['match' => 'all', 'rules' => [['field' => 'contact.visits', 'operator' => 'gte', 'value' => '5']]]),
                $n('hello_regular', 'message', 160, 240, ['text' => 'Welcome back, {{contact.first_name}}! Always great to see you.']),
                $n('tag_vip', 'action', 160, 350, ['action' => 'add_tag', 'params' => ['tag' => 'vip']]),
                $n('hello_new', 'message', 600, 240, ['text' => 'Hi {{contact.first_name}}! I am the virtual assistant.']),
                $n('menu', 'question', 380, 470, ['text' => 'What can I do for you?', 'retry_text' => 'Please pick one of the options below.', 'options' => [
                    ['id' => 'book', 'label' => 'Book a visit', 'synonyms' => ['book', 'appointment', 'visit']],
                    ['id' => 'prices', 'label' => 'Prices', 'synonyms' => ['price', 'cost', 'how much']],
                    ['id' => 'human', 'label' => 'Talk to a person', 'synonyms' => ['human', 'operator', 'person']],
                    ['id' => 'stop', 'label' => 'Unsubscribe', 'synonyms' => ['stop', 'unsubscribe']],
                ]]),
                $n('service', 'question', 40, 640, ['text' => 'Which service would you like?', 'options' => [
                    ['id' => 'haircut', 'label' => 'Haircut'],
                    ['id' => 'coloring', 'label' => 'Coloring'],
                    ['id' => 'manicure', 'label' => 'Manicure'],
                ]]),
                $n('set_haircut', 'action', -200, 790, ['action' => 'set_variable', 'params' => ['name' => 'service', 'value' => 'Haircut']]),
                $n('set_coloring', 'action', 40, 790, ['action' => 'set_variable', 'params' => ['name' => 'service', 'value' => 'Coloring']]),
                $n('set_manicure', 'action', 280, 790, ['action' => 'set_variable', 'params' => ['name' => 'service', 'value' => 'Manicure']]),
                $n('when', 'input', 40, 920, ['text' => 'When would suit you? (e.g. "Friday after 5pm")', 'variable' => 'preferred_time', 'validation' => 'text']),
                $n('email', 'input', 40, 1040, ['text' => 'Your email for the confirmation?', 'variable' => 'email', 'validation' => 'email', 'retry_text' => 'That does not look like an email address.']),
                $n('book', 'action', 40, 1160, ['action' => 'book_appointment', 'params' => ['service' => '{{vars.service}}', 'preferred_time' => '{{vars.preferred_time}}']]),
                $n('booked', 'end', 40, 1270, ['text' => 'Done! Request {{vars.booking_ref}}: {{vars.service}}, {{vars.preferred_time}}. We will confirm by email ({{vars.email}}).']),
                $n('prices_text', 'message', 420, 640, ['text' => 'Haircut from $35, coloring from $80, manicure from $25.']),
                $n('after_prices', 'question', 420, 760, ['text' => 'Shall I book you in?', 'options' => [
                    ['id' => 'yes', 'label' => 'Yes', 'synonyms' => ['sure', 'ok', 'yep']],
                    ['id' => 'no', 'label' => 'No', 'synonyms' => ['nope', 'later']],
                ]]),
                $n('bye', 'end', 520, 900, ['text' => 'No problem — just write when you are ready.']),
                $n('handoff', 'action', 700, 640, ['action' => 'handoff', 'params' => ['note' => 'Asked for a person from the main menu']]),
                $n('handoff_text', 'end', 700, 760, ['text' => 'A team member will reply here shortly.']),
                $n('optout', 'action', 920, 640, ['action' => 'opt_out']),
                $n('optout_text', 'end', 920, 760, ['text' => 'You will not receive promotions anymore.']),
            ],
            'edges' => [
                $e('start', 'next', 'is_regular'),
                $e('is_regular', 'true', 'hello_regular'),
                $e('is_regular', 'false', 'hello_new'),
                $e('hello_regular', 'next', 'tag_vip'),
                $e('tag_vip', 'next', 'menu'),
                $e('hello_new', 'next', 'menu'),
                $e('menu', 'option:book', 'service'),
                $e('menu', 'option:prices', 'prices_text'),
                $e('menu', 'option:human', 'handoff'),
                $e('menu', 'option:stop', 'optout'),
                $e('service', 'option:haircut', 'set_haircut'),
                $e('service', 'option:coloring', 'set_coloring'),
                $e('service', 'option:manicure', 'set_manicure'),
                $e('set_haircut', 'next', 'when'),
                $e('set_coloring', 'next', 'when'),
                $e('set_manicure', 'next', 'when'),
                $e('when', 'next', 'email'),
                $e('email', 'next', 'book'),
                $e('book', 'next', 'booked'),
                $e('prices_text', 'next', 'after_prices'),
                $e('after_prices', 'option:yes', 'service'),
                $e('after_prices', 'option:no', 'bye'),
                $e('handoff', 'next', 'handoff_text'),
                $e('optout', 'next', 'optout_text'),
            ],
        ];
    }
}
