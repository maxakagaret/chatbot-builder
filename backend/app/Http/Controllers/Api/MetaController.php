<?php

namespace App\Http\Controllers\Api;

use App\Chatbot\Actions\ActionHandler;
use App\Chatbot\Actions\ActionRegistry;
use App\Chatbot\Conditions\FieldResolver;
use App\Chatbot\Engine\InputValidator;
use App\Enums\ChannelType;
use App\Enums\NodeType;
use App\Enums\Operator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** Everything the editor palette needs, generated from the backend registries. */
class MetaController extends Controller
{
    public function __invoke(ActionRegistry $actions): JsonResponse
    {
        return response()->json(['data' => [
            'node_types' => array_map(fn (NodeType $t) => [
                'type' => $t->value,
                'waiting' => $t->isWaiting(),
                'handles' => $t->staticHandles(),
            ], NodeType::cases()),
            'actions' => array_values(array_map(fn (ActionHandler $a) => [
                'key' => $a->key(),
                'label' => $a->label(),
                'params' => $a->params(),
            ], $actions->all())),
            'condition_fields' => collect(FieldResolver::FIELDS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'operators' => array_map(fn (Operator $o) => ['key' => $o->value, 'needs_value' => $o->needsValue()], Operator::cases()),
            'input_validations' => InputValidator::TYPES,
            'channels' => array_map(fn (ChannelType $c) => ['key' => $c->value, 'label' => $c->label()], ChannelType::cases()),
        ]]);
    }
}
