<?php

namespace App\Http\Controllers\Api;

use App\Chatbot\ChatbotPublisher;
use App\Chatbot\Exceptions\InvalidScenarioException;
use App\Chatbot\Graph\GraphValidator;
use App\Chatbot\Scenarios\StarterScenario;
use App\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveGraphRequest;
use App\Models\ChannelBinding;
use App\Models\Chatbot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChatbotController extends Controller
{
    public function __construct(private readonly GraphValidator $validator) {}

    public function index(): JsonResponse
    {
        $bots = Chatbot::with(['publishedVersion:id,version,published_at', 'bindings'])
            ->withCount('sessions')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $bots->map(fn (Chatbot $b) => $this->summary($b))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $bot = Chatbot::create($data + ['draft_graph' => StarterScenario::minimal()]);

        return response()->json(['data' => $this->detail($bot)], 201);
    }

    public function show(Chatbot $chatbot): JsonResponse
    {
        return response()->json(['data' => $this->detail($chatbot)]);
    }

    public function update(Request $request, Chatbot $chatbot): JsonResponse
    {
        $chatbot->update($request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]));

        return response()->json(['data' => $this->summary($chatbot)]);
    }

    public function destroy(Chatbot $chatbot): JsonResponse
    {
        $chatbot->delete();

        return response()->json(null, 204);
    }

    /** Saves the draft even when it has issues; issues come back for the canvas. */
    public function saveGraph(SaveGraphRequest $request, Chatbot $chatbot): JsonResponse
    {
        $graph = $request->graph();
        $chatbot->update(['draft_graph' => $graph]);

        return response()->json(['data' => ['saved_at' => now()->toIso8601String(), 'issues' => $this->validator->validate($graph)]]);
    }

    public function validateGraph(SaveGraphRequest $request): JsonResponse
    {
        return response()->json(['data' => ['issues' => $this->validator->validate($request->graph())]]);
    }

    public function publish(Chatbot $chatbot, ChatbotPublisher $publisher): JsonResponse
    {
        try {
            $version = $publisher->publish($chatbot);
        } catch (InvalidScenarioException $e) {
            return response()->json(['message' => $e->getMessage(), 'issues' => $e->issues], 422);
        }

        return response()->json(['data' => ['version' => $version->version, 'published_at' => $version->published_at->toIso8601String()]]);
    }

    public function versions(Chatbot $chatbot): JsonResponse
    {
        return response()->json(['data' => $chatbot->versions()->get(['id', 'version', 'published_at'])->map(fn ($v) => [
            'id' => $v->id,
            'version' => $v->version,
            'published_at' => $v->published_at->toIso8601String(),
            'is_live' => $v->id === $chatbot->published_version_id,
            'active_sessions' => $chatbot->sessions()->where('chatbot_version_id', $v->id)->where('status', 'active')->count(),
        ])]);
    }

    /** Binds the bot to channels; a channel served by another bot is moved over. */
    public function channels(Request $request, Chatbot $chatbot): JsonResponse
    {
        $data = $request->validate([
            'channels' => ['present', 'array'],
            'channels.*' => ['distinct', Rule::enum(ChannelType::class)],
        ]);

        DB::transaction(function () use ($chatbot, $data) {
            $chatbot->bindings()->whereNotIn('channel', $data['channels'])->delete();
            foreach ($data['channels'] as $channel) {
                ChannelBinding::updateOrCreate(['channel' => $channel], ['chatbot_id' => $chatbot->id]);
            }
        });

        return response()->json(['data' => $this->summary($chatbot->fresh(['bindings', 'publishedVersion']))]);
    }

    private function summary(Chatbot $bot): array
    {
        return [
            'id' => $bot->id,
            'name' => $bot->name,
            'description' => $bot->description,
            'published_version' => $bot->publishedVersion?->version,
            'published_at' => $bot->publishedVersion?->published_at?->toIso8601String(),
            'channels' => $bot->bindings->pluck('channel')->map->value->values(),
            'sessions_count' => $bot->sessions_count ?? $bot->sessions()->count(),
            'updated_at' => $bot->updated_at?->toIso8601String(),
        ];
    }

    private function detail(Chatbot $bot): array
    {
        $bot->loadMissing(['bindings', 'publishedVersion']);

        return $this->summary($bot) + [
            'graph' => $bot->draft_graph,
            'issues' => $this->validator->validate($bot->draft_graph),
            'has_unpublished_changes' => $bot->publishedVersion?->graph !== $bot->draft_graph,
        ];
    }
}
