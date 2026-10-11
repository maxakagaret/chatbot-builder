<?php

use App\Chatbot\ChatbotPublisher;
use App\Chatbot\Engine\ConversationEngine;
use App\Chatbot\Engine\EngineResult;
use App\Chatbot\Engine\InboundMessage;
use App\Chatbot\Graph\Issue;
use App\Enums\ChannelType;
use App\Models\Chatbot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/** Compact graph builder for tests: node(id, type, data), edge(source, handle, target). */
function node(string $id, string $type, array $data = []): array
{
    return ['id' => $id, 'type' => $type, 'position' => ['x' => 0, 'y' => 0], 'data' => $data];
}

function edge(string $source, string $handle, string $target): array
{
    return ['id' => "{$source}:{$handle}", 'source' => $source, 'sourceHandle' => $handle, 'target' => $target];
}

function graph(array $nodes, array $edges): array
{
    return ['nodes' => $nodes, 'edges' => $edges];
}

function publishedBot(?array $graph = null): Chatbot
{
    $bot = $graph ? Chatbot::factory()->graph($graph)->create() : Chatbot::factory()->create();
    app(ChatbotPublisher::class)->publish($bot);

    return $bot->refresh();
}

/** Sends one user message to the bot through the engine and returns the reply texts. */
function say(Chatbot $bot, string $text, string $user = 'u1', ChannelType $channel = ChannelType::Web, ?string $button = null): EngineResult
{
    return app(ConversationEngine::class)->handle($bot, new InboundMessage($channel, $user, $text, $button, 'Alex Doe'));
}

function texts(EngineResult $result): array
{
    return array_map(fn ($r) => $r->text, $result->replies);
}

/** @param list<Issue> $issues */
function codes(array $issues, ?string $severity = null): array
{
    return array_values(array_map(fn (Issue $i) => $i->code, array_filter($issues, fn (Issue $i) => ! $severity || $i->severity === $severity)));
}
