<?php

namespace App\Http\Controllers\Api;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Models\Chatbot;
use App\Models\ConversationSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index(Request $request, Chatbot $chatbot): JsonResponse
    {
        $sessions = $chatbot->sessions()
            ->with(['contact:id,name', 'version:id,version'])
            ->withCount('messages')
            ->when(! $request->boolean('include_tests'), fn ($q) => $q->where('is_test', false))
            ->latest('id')
            ->paginate(min(100, $request->integer('per_page', 20)));

        return response()->json([
            'data' => $sessions->getCollection()->map(fn (ConversationSession $s) => [
                'id' => $s->id,
                'contact' => $s->contact->name,
                'channel' => $s->channel->value,
                'version' => $s->version?->version,
                'status' => $s->status->value,
                'current_node_id' => $s->current_node_id,
                'messages_count' => $s->messages_count,
                'started_at' => $s->started_at->toIso8601String(),
                'last_activity_at' => $s->last_activity_at->toIso8601String(),
            ]),
            'meta' => ['total' => $sessions->total(), 'current_page' => $sessions->currentPage(), 'last_page' => $sessions->lastPage()],
            'stats' => $chatbot->sessions()->where('is_test', false)
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(ConversationSession $session): JsonResponse
    {
        $session->load(['contact', 'messages', 'version']);

        return response()->json(['data' => [
            'id' => $session->id,
            'contact' => ['id' => $session->contact->id, 'name' => $session->contact->name, 'tags' => $session->contact->tags],
            'channel' => $session->channel->value,
            'status' => $session->status->value,
            'version' => $session->version?->version,
            'variables' => $session->variables,
            'messages' => $session->messages->map(fn ($m) => [
                'direction' => $m->direction,
                'text' => $m->text,
                'buttons' => $m->buttons,
                'node_id' => $m->node_id,
                'created_at' => $m->created_at->toIso8601String(),
            ]),
        ]]);
    }

    /** Operator closes a hand-off; the bot answers again on the next message. */
    public function close(ConversationSession $session): JsonResponse
    {
        if ($session->status === SessionStatus::Handoff || $session->status === SessionStatus::Active) {
            $session->update(['status' => SessionStatus::Completed, 'ended_at' => now()]);
        }

        return response()->json(['data' => ['status' => $session->status->value]]);
    }
}
