<?php

namespace App\Chatbot\Engine;

use App\Chatbot\Actions\ActionRegistry;
use App\Chatbot\Conditions\ConditionEvaluator;
use App\Chatbot\Graph\Graph;
use App\Chatbot\Graph\Node;
use App\Enums\ChannelType;
use App\Enums\NodeType;
use App\Enums\SessionStatus;
use App\Models\Chatbot;
use App\Models\Contact;
use App\Models\ConversationSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Executes a scenario graph, independent of the channel.
 *
 * One call = one inbound message. The engine
 *   1. finds the contact's active session (or starts one at the start step),
 *   2. if the session waits on a question/input, consumes the answer,
 *   3. walks instant steps (message, condition, action) until it reaches the
 *      next waiting step or the end, collecting replies on the way,
 *   4. persists the position, variables and transcript.
 *
 * Runtime guards: a step budget per message (in addition to the validator's loop
 * check), a retry limit on unrecognised answers, and a session TTL.
 */
class ConversationEngine
{
    public const MAX_STEPS = 50;

    public function __construct(
        private readonly AnswerMatcher $matcher,
        private readonly InputValidator $inputs,
        private readonly ConditionEvaluator $conditions,
        private readonly ActionRegistry $actions,
        private readonly TemplateRenderer $templates,
    ) {}

    public function handle(Chatbot $bot, InboundMessage $message, bool $test = false): EngineResult
    {
        return DB::transaction(function () use ($bot, $message, $test) {
            $contact = Contact::resolve($message->channel, $message->externalId, $message->name, $test);
            $session = $this->activeSession($bot, $contact, $message->channel, $test);

            if ($session && $session->status === SessionStatus::Handoff) {
                $this->log($session, 'in', $message->text);
                $session->forceFill(['last_activity_at' => CarbonImmutable::now()])->save();

                return new EngineResult($session, []);
            }

            $replies = [];
            if (! $session) {
                $session = $this->start($bot, $contact, $message->channel, $test);
                $this->log($session, 'in', $message->text);
                $graph = $session->graph();
                $replies = $this->advance($session, $graph, $graph->start());
            } else {
                $this->log($session, 'in', $message->text);
                $replies = $this->answer($session, $session->graph(), $message);
            }

            $session->last_activity_at = CarbonImmutable::now();
            $session->save();

            foreach ($replies as $reply) {
                $this->log($session, 'out', $reply->text, $reply->nodeId, $reply->buttons ?: null);
            }

            return new EngineResult($session->refresh(), $replies);
        });
    }

    /** Consumes the user's answer to the waiting step. */
    private function answer(ConversationSession $session, Graph $graph, InboundMessage $message): array
    {
        $current = $session->current_node_id ? $graph->node($session->current_node_id) : null;

        if (! $current || ! $current->type->isWaiting()) {
            // The step disappeared (e.g. test session after editing the draft): start over.
            return $this->advance($session, $graph, $graph->start());
        }

        if ($current->type === NodeType::Question) {
            $optionId = $this->matcher->match($current, $message->text, $message->payload);

            return $optionId !== null
                ? $this->advance($session, $graph, $graph->next($current, "option:{$optionId}"))
                : $this->retry($session, $current);
        }

        $value = $this->inputs->validate($current->data['validation'] ?? 'text', $message->text);
        if ($value === null) {
            return $this->retry($session, $current);
        }

        $session->variables = [...($session->variables ?? []), $current->data['variable'] => $value];

        return $this->advance($session, $graph, $graph->next($current, 'next'));
    }

    /** Unrecognised answer: re-ask, and give up after max_retries (default 3). */
    private function retry(ConversationSession $session, Node $node): array
    {
        $session->retries++;
        $limit = (int) ($node->data['max_retries'] ?? 3);

        if ($session->retries >= $limit) {
            $this->finish($session, SessionStatus::Completed);

            return [new Reply('Sorry, I could not understand. A team member will get back to you.', [], $node->id)];
        }

        $hint = $node->data['retry_text'] ?? ($node->type === NodeType::Question
            ? 'Please choose one of the options.'
            : 'That does not look right, please try again.');

        return [new Reply($this->templates->render($hint, $session), [], $node->id), $this->prompt($session, $node)];
    }

    /** Walks instant steps from $node until a waiting step or the end. */
    private function advance(ConversationSession $session, Graph $graph, ?Node $node): array
    {
        $replies = [];
        $session->retries = 0;

        for ($steps = 0; $node !== null; $steps++) {
            if ($steps >= self::MAX_STEPS) {
                Log::warning('Chatbot step budget exhausted', ['session' => $session->id, 'node' => $node->id]);
                $this->finish($session, SessionStatus::Failed);

                return $replies;
            }

            $session->current_node_id = $node->id;

            if ($node->type->isWaiting()) {
                $replies[] = $this->prompt($session, $node);

                return $replies;
            }

            [$replies, $handle] = $this->execute($session, $node, $replies);

            // A hand-off still lets the remaining instant steps run (e.g. "an operator
            // will reply shortly"); finished or failed sessions stop right here.
            $stopped = ! in_array($session->status, [SessionStatus::Active, SessionStatus::Handoff], true);
            if ($stopped || $node->type === NodeType::End) {
                break;
            }

            $node = $graph->next($node, $handle);
        }

        if ($session->status === SessionStatus::Active) {
            $this->finish($session, SessionStatus::Completed);
        }

        return $replies;
    }

    /** @return array{0: list<Reply>, 1: string} replies so far and the output handle to follow */
    private function execute(ConversationSession $session, Node $node, array $replies): array
    {
        switch ($node->type) {
            case NodeType::Message:
            case NodeType::End:
                if (trim($node->text()) !== '') {
                    $replies[] = new Reply($this->templates->render($node->text(), $session), [], $node->id);
                }

                return [$replies, 'next'];

            case NodeType::Condition:
                return [$replies, $this->conditions->passes($node->data, $session) ? 'true' : 'false'];

            case NodeType::Action:
                $handler = $this->actions->get((string) $node->data['action']);
                $text = $handler->handle($session, $this->templates->renderParams((array) ($node->data['params'] ?? []), $session));
                if ($text) {
                    $replies[] = new Reply($text, [], $node->id);
                }

                return [$replies, 'next'];

            default: // start
                return [$replies, 'next'];
        }
    }

    private function prompt(ConversationSession $session, Node $node): Reply
    {
        $buttons = $node->type === NodeType::Question
            ? array_map(fn ($o) => ['id' => $o['id'], 'label' => $o['label']], $node->options())
            : [];

        return new Reply($this->templates->render($node->text(), $session), $buttons, $node->id);
    }

    private function activeSession(Chatbot $bot, Contact $contact, ChannelType $channel, bool $test): ?ConversationSession
    {
        $session = ConversationSession::where('chatbot_id', $bot->id)
            ->where('contact_id', $contact->id)
            ->where('channel', $channel)
            ->where('is_test', $test)
            ->whereIn('status', [SessionStatus::Active, SessionStatus::Handoff])
            ->latest('id')
            ->first();

        if ($session && $session->status === SessionStatus::Active
            && $session->last_activity_at->lt(CarbonImmutable::now()->subMinutes((int) config('chatbot.session_ttl_minutes')))) {
            $this->finish($session, SessionStatus::Expired);
            $session->save();

            return null;
        }

        return $session;
    }

    private function start(Chatbot $bot, Contact $contact, ChannelType $channel, bool $test): ConversationSession
    {
        return ConversationSession::create([
            'chatbot_id' => $bot->id,
            'chatbot_version_id' => $test ? null : $bot->published_version_id,
            'contact_id' => $contact->id,
            'channel' => $channel,
            'is_test' => $test,
            'status' => SessionStatus::Active,
            'variables' => [],
            'started_at' => CarbonImmutable::now(),
            'last_activity_at' => CarbonImmutable::now(),
        ]);
    }

    private function finish(ConversationSession $session, SessionStatus $status): void
    {
        $session->status = $status;
        $session->ended_at = CarbonImmutable::now();
    }

    private function log(ConversationSession $session, string $direction, string $text, ?string $nodeId = null, ?array $buttons = null): void
    {
        $session->messages()->create([
            'direction' => $direction,
            'node_id' => $nodeId,
            'text' => $text,
            'buttons' => $buttons,
            'created_at' => CarbonImmutable::now(),
        ]);
    }
}
