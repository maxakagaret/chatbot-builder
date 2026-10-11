<?php

namespace Database\Seeders;

use App\Chatbot\ChatbotPublisher;
use App\Chatbot\Engine\ConversationEngine;
use App\Chatbot\Engine\InboundMessage;
use App\Chatbot\Scenarios\StarterScenario;
use App\Enums\ChannelType;
use App\Models\ChannelBinding;
use App\Models\Chatbot;
use App\Models\Contact;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Synthetic demo data. Conversation history is not inserted row by row: scripted
 * users talk to the real engine through every channel, so transcripts, variables,
 * bookings and hand-offs are exactly what the bot would have produced.
 */
class DemoSeeder extends Seeder
{
    /** Scripts of user messages (the first message opens the conversation). */
    private const SCRIPTS = [
        'booking' => ['hi', 'book', 'haircut', 'Friday after 5pm', '{email}'],
        'booking_typo' => ['hello', 'I want an appointment', '2', 'tomorrow morning', 'not-an-email', '{email}'],
        'prices_then_book' => ['hey', 'how much is it?', 'yes', 'manicure', 'Saturday', '{email}'],
        'prices_only' => ['hi', 'prices', 'later'],
        'human' => ['hello', 'can I talk to a person?', 'are you there?'],
        'unsubscribe' => ['hi', 'stop'],
        'confused' => ['hi', 'banana', 'what?', '???'],
        'abandoned' => ['hi', 'book'],
    ];

    public function run(ConversationEngine $engine, ChatbotPublisher $publisher): void
    {
        fake()->seed(2026);

        $assistant = Chatbot::create([
            'name' => 'Appointment assistant',
            'description' => 'Greets regulars, books visits, answers prices, hands off to a human.',
            'draft_graph' => StarterScenario::appointmentAssistant(),
        ]);
        $publisher->publish($assistant);

        foreach (ChannelType::cases() as $channel) {
            ChannelBinding::create(['channel' => $channel, 'chatbot_id' => $assistant->id]);
        }

        // A second bot left as a draft with deliberate problems, to show the validator.
        Chatbot::create([
            'name' => 'Feedback survey (draft)',
            'description' => 'Work in progress — open it to see validation issues on the canvas.',
            'draft_graph' => $this->brokenSurvey(),
        ]);

        $this->simulateHistory($engine, $assistant);
    }

    private function simulateHistory(ConversationEngine $engine, Chatbot $bot): void
    {
        $channels = ChannelType::cases();
        $start = CarbonImmutable::now()->subDays(6);

        for ($i = 0; $i < 60; $i++) {
            $channel = $channels[$i % count($channels)];
            $contact = Contact::factory()->create(['visits' => fake()->randomElement([0, 1, 2, 6, 9])]);
            $externalId = match ($channel) {
                ChannelType::Telegram => (string) fake()->unique()->numberBetween(100000000, 999999999),
                ChannelType::Web => 'visitor-'.fake()->unique()->bothify('????####'),
                default => $contact->phone,
            };
            $contact->identities()->create(['channel' => $channel, 'external_id' => $externalId]);

            $script = fake()->randomElement(self::SCRIPTS);
            $moment = $start->addMinutes($i * 137);

            foreach ($script as $text) {
                CarbonImmutable::setTestNow($moment = $moment->addSeconds(fake()->numberBetween(5, 90)));
                $engine->handle($bot, new InboundMessage(
                    $channel,
                    $externalId,
                    str_replace('{email}', $contact->email, $text),
                    null,
                    $contact->name,
                ));
            }
        }

        CarbonImmutable::setTestNow();
    }

    private function brokenSurvey(): array
    {
        return [
            'nodes' => [
                ['id' => 'start', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'rate', 'type' => 'question', 'position' => ['x' => 0, 'y' => 120], 'data' => [
                    'text' => 'How was your last visit?',
                    'options' => [['id' => 'good', 'label' => 'Great'], ['id' => 'bad', 'label' => 'Not great']],
                ]],
                ['id' => 'thanks', 'type' => 'end', 'position' => ['x' => -150, 'y' => 260], 'data' => ['text' => 'Thank you!']],
                ['id' => 'check', 'type' => 'condition', 'position' => ['x' => 150, 'y' => 260], 'data' => [
                    'rules' => [['field' => 'contact.visits', 'operator' => 'gt', 'value' => '3']],
                ]],
                ['id' => 'orphan', 'type' => 'message', 'position' => ['x' => 400, 'y' => 120], 'data' => ['text' => 'Nobody links to me.']],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start', 'sourceHandle' => 'next', 'target' => 'rate'],
                ['id' => 'e2', 'source' => 'rate', 'sourceHandle' => 'option:good', 'target' => 'thanks'],
                ['id' => 'e3', 'source' => 'rate', 'sourceHandle' => 'option:bad', 'target' => 'check'],
                ['id' => 'e4', 'source' => 'check', 'sourceHandle' => 'true', 'target' => 'thanks'],
            ],
        ];
    }
}
