<?php

namespace App\Chatbot;

use App\Chatbot\Exceptions\InvalidScenarioException;
use App\Chatbot\Graph\GraphValidator;
use App\Models\Chatbot;
use App\Models\ChatbotVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Draft → immutable version. Running conversations keep the version they
 * started on; new conversations pick up the freshly published one.
 */
class ChatbotPublisher
{
    public function __construct(private readonly GraphValidator $validator) {}

    /** @throws InvalidScenarioException when the draft has validation errors */
    public function publish(Chatbot $bot): ChatbotVersion
    {
        $issues = $this->validator->validate($bot->draft_graph);

        if (GraphValidator::hasErrors($issues)) {
            throw new InvalidScenarioException($issues);
        }

        return DB::transaction(function () use ($bot) {
            $locked = Chatbot::whereKey($bot->id)->lockForUpdate()->firstOrFail();

            $version = $locked->versions()->create([
                'version' => (int) $locked->versions()->max('version') + 1,
                'graph' => $locked->draft_graph,
                'published_at' => CarbonImmutable::now(),
            ]);

            $locked->forceFill(['published_version_id' => $version->id])->save();
            $bot->setRawAttributes($locked->getAttributes(), true);

            return $version;
        });
    }
}
