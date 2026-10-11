<?php

namespace App\Http\Controllers\Api;

use App\Chatbot\Channels\ChannelRegistry;
use App\Chatbot\Engine\ConversationEngine;
use App\Chatbot\Engine\Reply;
use App\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Jobs\DeliverReplies;
use App\Models\ChannelBinding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single entry point for every channel: adapter parses → engine runs the
 * published scenario → replies go back synchronously (web widget) or through
 * the queue (messengers, SMS).
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, ChannelType $channel, ChannelRegistry $channels, ConversationEngine $engine): JsonResponse
    {
        $adapter = $channels->for($channel);
        $inbound = $adapter->parse($request->validate($adapter->rules()));

        $binding = ChannelBinding::with('chatbot')->find($channel->value);

        if (! $binding || ! $binding->chatbot->published_version_id) {
            // Acknowledge anyway: the provider must not retry a message nobody can answer.
            return response()->json(['handled' => false, 'reason' => 'no_published_bot_for_channel'], 202);
        }

        $result = $engine->handle($binding->chatbot, $inbound);

        if ($channel === ChannelType::Web) {
            return response()->json([
                'replies' => array_map(fn (Reply $r) => $adapter->format($r, $inbound->externalId), $result->replies),
                'session_status' => $result->session->status->value,
            ]);
        }

        if ($result->replies) {
            DeliverReplies::dispatch($channel, $inbound->externalId, array_map(fn (Reply $r) => $r->jsonSerialize(), $result->replies))
                ->onQueue(config('chatbot.queue'));
        }

        return response()->json(['handled' => true], 202);
    }
}
