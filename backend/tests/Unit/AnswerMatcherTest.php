<?php

use App\Chatbot\Engine\AnswerMatcher;
use App\Chatbot\Graph\Node;
use App\Enums\NodeType;

beforeEach(function () {
    $this->question = new Node('q', NodeType::Question, ['options' => [
        ['id' => 'book', 'label' => 'Book a visit', 'synonyms' => ['appointment']],
        ['id' => 'prices', 'label' => 'Prices', 'synonyms' => ['cost', 'how much']],
        ['id' => 'stop', 'label' => 'Unsubscribe'],
    ]]);
    $this->matcher = new AnswerMatcher;
});

it('maps answers from every channel style to the same option', function (string $text, ?string $payload, ?string $expected) {
    expect($this->matcher->match($this->question, $text, $payload))->toBe($expected);
})->with([
    'telegram button payload' => ['', 'prices', 'prices'],
    'sms number' => ['2', null, 'prices'],
    'number with spaces' => [' 3 ', null, 'stop'],
    'exact label, any case' => ['book a VISIT', null, 'book'],
    'label with punctuation' => ['Prices!', null, 'prices'],
    'synonym word' => ['I need an appointment please', null, 'book'],
    'multi-word synonym' => ['how much is a haircut?', null, 'prices'],
    'out of range number' => ['7', null, null],
    'unknown text' => ['banana', null, null],
    'empty' => ['   ', null, null],
    'unknown payload falls back to text' => ['Unsubscribe', 'nope', 'stop'],
]);
