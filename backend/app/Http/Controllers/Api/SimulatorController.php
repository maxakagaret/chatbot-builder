<?php

namespace App\Http\Controllers\Api;

use App\Chatbot\Channels\ChannelRegistry;
use App\Chatbot\Engine\ConversationEngine;
use App\Chatbot\Engine\InboundMessage;
use App\Chatbot\Engine\Reply;
use App\Enums\ChannelType;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Models\Chatbot;
use App\Models\ConversationSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The editor's playground: runs the current *draft* in a test session and
 * returns both the neutral replies and the exact payload each channel adapter
 * would send, plus the current step id so the canvas can highlight it.
 */
class SimulatorController extends Controller
{
    public function message(Request $request, Chatbot $chatbot, ConversationEngine $engine, ChannelRegistry $channels): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::enum(ChannelType::class)],
            'tester_id' => ['required', 'string', 'max:64'],
            'text' => ['present', 'nullable', 'string', 'max:2000'],
            'button_id' => ['nullable', 'string', 'max:64'],
        ]);

        $channel = ChannelType::from($data['channel']);
        $externalId = 'test-'.$data['tester_id'];

        $result = $engine->handle($chatbot, new InboundMessage(
            $channel, $externalId, (string) ($data['text'] ?? ''), $data['button_id'] ?? null, 'Tester',
        ), test: true);

        $adapter = $channels->for($channel);

        return response()->json(['data' => [
            'replies' => array_map(fn (Reply $r) => [
                ...$r->jsonSerialize(),
                'wire' => $adapter->format($r, $externalId),
            ], $result->replies),
            'session' => [
                'id' => $result->session->id,
                'status' => $result->session->status->value,
                'current_node_id' => $result->session->current_node_id,
                'variables' => $result->session->variables,
            ],
        ]]);
    }

    /** Ends the tester's session so the next message starts from the beginning. */
    public function reset(Request $request, Chatbot $chatbot): JsonResponse
    {
        $data = $request->validate(['tester_id' => ['required', 'string', 'max:64']]);

        ConversationSession::where('chatbot_id', $chatbot->id)
            ->where('is_test', true)
            ->whereHas('contact.identities', fn ($q) => $q->where('external_id', 'test-'.$data['tester_id']))
            ->whereIn('status', [SessionStatus::Active, SessionStatus::Handoff])
            ->update(['status' => SessionStatus::Completed, 'ended_at' => now()]);

        return response()->json(['data' => ['reset' => true]]);
    }
}
