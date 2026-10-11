<?php

use App\Enums\Operator;

it('compares values the way a non-programmer expects', function (Operator $op, mixed $actual, ?string $expected, bool $result) {
    expect($op->compare($actual, $expected))->toBe($result);
})->with([
    'numeric gte' => [Operator::GreaterOrEqual, 5, '5', true],
    'numeric gt false' => [Operator::Greater, 4, '5', false],
    'numeric strings' => [Operator::Less, '10', '9.5', false],
    'non-numeric is never greater' => [Operator::Greater, 'abc', '5', false],
    'case-insensitive equals' => [Operator::Equals, 'WhatsApp', 'whatsapp', true],
    'numbers equal across types' => [Operator::Equals, 3, '3.0', true],
    'not equals' => [Operator::NotEquals, 'a', 'b', true],
    'tag list contains' => [Operator::Contains, ['vip', 'new'], 'VIP', true],
    'tag list does not contain' => [Operator::NotContains, ['vip'], 'lapsed', true],
    'substring contains' => [Operator::Contains, 'Friday evening', 'friday', true],
    'is set' => [Operator::IsSet, 'x', null, true],
    'empty array is empty' => [Operator::IsEmpty, [], null, true],
    'null is empty' => [Operator::IsEmpty, null, null, true],
]);
