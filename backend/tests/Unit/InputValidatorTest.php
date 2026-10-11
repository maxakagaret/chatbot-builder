<?php

use App\Chatbot\Engine\InputValidator;

it('validates and normalises free-text answers', function (string $type, string $input, ?string $expected) {
    expect((new InputValidator)->validate($type, $input))->toBe($expected);
})->with([
    'email lowercased' => ['email', ' Ada@Example.TEST ', 'ada@example.test'],
    'bad email' => ['email', 'ada at example', null],
    'phone digits only' => ['phone', '+1 (555) 010-0001', '+15550100001'],
    'too short phone' => ['phone', '12-34', null],
    'number with comma' => ['number', '3,5', '3.5'],
    'not a number' => ['number', 'three', null],
    'text trimmed' => ['text', '  Friday after 5pm ', 'Friday after 5pm'],
    'empty text' => ['text', '   ', null],
]);
