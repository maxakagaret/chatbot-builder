<?php

use App\Chatbot\Graph\GraphValidator;
use App\Chatbot\Graph\Issue;
use App\Chatbot\Scenarios\StarterScenario;

function issuesOf(array $graph): array
{
    return app(GraphValidator::class)->validate($graph);
}

it('accepts the demo scenario without errors or warnings', function () {
    expect(issuesOf(StarterScenario::appointmentAssistant()))->toBe([]);
});

it('requires exactly one start step', function () {
    expect(codes(issuesOf(graph([node('m', 'message', ['text' => 'hi'])], []))))->toContain('no_start')
        ->and(codes(issuesOf(graph([node('a', 'start'), node('b', 'start')], []))))->toContain('multiple_starts');
});

it('reports dangling edges, unknown handles and ambiguous outputs', function () {
    $issues = issuesOf(graph(
        [node('s', 'start'), node('m', 'message', ['text' => 'hi']), node('x', 'message', ['text' => 'x'])],
        [edge('s', 'next', 'm'), edge('s', 'next', 'x'), edge('m', 'true', 'x'), edge('m', 'next', 'ghost')],
    ));

    expect(codes($issues, Issue::ERROR))->toContain('ambiguous_output', 'unknown_handle', 'dangling_edge');
});

it('requires both branches of a condition', function () {
    $issues = issuesOf(graph(
        [node('s', 'start'), node('c', 'condition', ['rules' => [['field' => 'contact.visits', 'operator' => 'gt', 'value' => '1']]]), node('e', 'end')],
        [edge('s', 'next', 'c'), edge('c', 'true', 'e')],
    ));

    expect(collect($issues)->first(fn (Issue $i) => $i->code === 'unconnected_output'))
        ->nodeId->toBe('c')
        ->severity->toBe(Issue::ERROR);
});

it('detects a loop that never waits for the user', function () {
    $issues = issuesOf(graph(
        [node('s', 'start'), node('a', 'message', ['text' => 'a']), node('b', 'action', ['action' => 'add_tag', 'params' => ['tag' => 'x']])],
        [edge('s', 'next', 'a'), edge('a', 'next', 'b'), edge('b', 'next', 'a')],
    ));

    expect(codes($issues, Issue::ERROR))->toContain('infinite_loop');
});

it('allows a loop that passes through a question', function () {
    $issues = issuesOf(graph(
        [node('s', 'start'), node('q', 'question', ['text' => 'Again?', 'options' => [['id' => 'y', 'label' => 'Yes'], ['id' => 'n', 'label' => 'No']]]),
            node('m', 'message', ['text' => 'ok']), node('e', 'end')],
        [edge('s', 'next', 'q'), edge('q', 'option:y', 'm'), edge('m', 'next', 'q'), edge('q', 'option:n', 'e')],
    ));

    expect(codes($issues, Issue::ERROR))->toBe([]);
});

it('warns about unreachable steps and options that lead nowhere', function () {
    $issues = issuesOf(graph(
        [node('s', 'start'), node('q', 'question', ['text' => '?', 'options' => [['id' => 'a', 'label' => 'A'], ['id' => 'b', 'label' => 'B']]]),
            node('e', 'end'), node('lost', 'end')],
        [edge('s', 'next', 'q'), edge('q', 'option:a', 'e')],
    ));

    expect(codes($issues, Issue::WARNING))->toContain('unreachable', 'unconnected_option')
        ->and(GraphValidator::hasErrors($issues))->toBeFalse();
});

it('validates step content', function () {
    $issues = issuesOf(graph([
        node('s', 'start'),
        node('m', 'message', ['text' => '  ']),
        node('q', 'question', ['text' => '?', 'options' => [['id' => 'a', 'label' => 'Same'], ['id' => 'b', 'label' => 'same']]]),
        node('i', 'input', ['text' => '?', 'variable' => 'Bad Name', 'validation' => 'zip']),
        node('c', 'condition', ['rules' => [['field' => 'contact.shoe_size', 'operator' => 'eq', 'value' => '1'], ['field' => 'vars.x', 'operator' => 'gt']]]),
        node('a', 'action', ['action' => 'book_appointment', 'params' => []]),
        node('a2', 'action', ['action' => 'launch_rocket']),
    ], [edge('s', 'next', 'm')]));

    expect(codes($issues, Issue::ERROR))->toContain(
        'empty_text', 'duplicate_option', 'bad_variable', 'bad_validation', 'bad_field', 'missing_value', 'bad_action_params', 'bad_action',
    );
});

it('reports duplicate node ids', function () {
    expect(codes(issuesOf(graph([node('s', 'start'), node('s', 'end')], []))))->toContain('duplicate_node');
});
