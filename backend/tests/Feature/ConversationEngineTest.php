<?php

use App\Chatbot\ChatbotPublisher;
use App\Chatbot\Engine\ConversationEngine;
use App\Chatbot\Engine\InboundMessage;
use App\Enums\ChannelType;
use App\Enums\SessionStatus;
use App\Models\Booking;
use App\Models\Chatbot;
use App\Models\Contact;
use App\Models\ConversationSession;

it('walks the whole booking path and stores the booking', function () {
    $bot = publishedBot();

    expect(texts(say($bot, 'hi')))->toBe(['Hi Alex! I am the virtual assistant.', 'What can I do for you?']);

    say($bot, 'book');
    say($bot, 'Coloring');
    say($bot, 'Friday after 5pm');
    $last = say($bot, 'alex@example.test');

    $booking = Booking::sole();
    expect($booking->service)->toBe('Coloring')
        ->and($booking->preferred_time)->toBe('Friday after 5pm')
        ->and(texts($last)[0])->toBe("Done! Request {$booking->reference}: Coloring, Friday after 5pm. We will confirm by email (alex@example.test).")
        ->and($last->session->status)->toBe(SessionStatus::Completed)
        ->and($last->session->variables)->toMatchArray(['service' => 'Coloring', 'email' => 'alex@example.test']);
});

it('branches on contact data and runs actions on the way', function () {
    $bot = publishedBot();
    $regular = Contact::factory()->create(['name' => 'Rita Regular', 'visits' => 7]);
    $regular->identities()->create(['channel' => ChannelType::Web, 'external_id' => 'rita']);

    $result = say($bot, 'hello', 'rita');

    expect(texts($result)[0])->toBe('Welcome back, Rita! Always great to see you.')
        ->and($regular->refresh()->tags)->toBe(['vip']);
});

it('offers buttons for questions', function () {
    $result = say(publishedBot(), 'hi');
    $question = $result->replies[array_key_last($result->replies)];

    expect($question->buttons)->toHaveCount(4)
        ->and($question->buttons[0])->toBe(['id' => 'book', 'label' => 'Book a visit']);
});

it('re-asks on an unrecognised answer and gives up after the retry limit', function () {
    $bot = publishedBot();
    say($bot, 'hi');

    expect(texts(say($bot, 'banana')))->toBe(['Please pick one of the options below.', 'What can I do for you?']);
    say($bot, 'what?');
    $final = say($bot, '???');

    expect(texts($final))->toBe(['Sorry, I could not understand. A team member will get back to you.'])
        ->and($final->session->status)->toBe(SessionStatus::Completed);
});

it('validates input steps and keeps waiting on invalid values', function () {
    $bot = publishedBot();
    foreach (['hi', 'book', 'haircut', 'tomorrow'] as $text) {
        say($bot, $text);
    }

    $result = say($bot, 'not-an-email');

    expect(texts($result))->toBe(['That does not look like an email address.', 'Your email for the confirmation?'])
        ->and($result->session->current_node_id)->toBe('email')
        ->and(Booking::count())->toBe(0);
});

it('hands off to a human and then stays silent', function () {
    $bot = publishedBot();
    say($bot, 'hi');

    $handoff = say($bot, 'talk to a person');
    $later = say($bot, 'hello? anyone?');

    expect(texts($handoff))->toBe(['A team member will reply here shortly.'])
        ->and($handoff->session->status)->toBe(SessionStatus::Handoff)
        ->and($later->replies)->toBe([])
        ->and($later->session->messages()->where('direction', 'in')->count())->toBe(3);
});

it('unsubscribes the contact', function () {
    $bot = publishedBot();
    say($bot, 'hi');
    say($bot, 'stop');

    expect(Contact::sole()->opted_out)->toBeTrue();
});

it('works the same over a numbered-list channel', function () {
    $bot = publishedBot();
    say($bot, 'hi', '+15550100001', ChannelType::Sms);

    $result = say($bot, '2', '+15550100001', ChannelType::Sms);

    expect(texts($result))->toBe(['Haircut from $35, coloring from $80, manicure from $25.', 'Shall I book you in?']);
});

it('starts over when the session expired', function () {
    config(['chatbot.session_ttl_minutes' => 30]);
    $bot = publishedBot();
    $first = say($bot, 'hi');

    $this->travel(31)->minutes();
    $second = say($bot, 'book');

    expect($second->session->id)->not->toBe($first->session->id)
        ->and(ConversationSession::find($first->session->id)->status)->toBe(SessionStatus::Expired)
        ->and(texts($second))->toContain('What can I do for you?');
});

it('keeps running conversations on the version they started with', function () {
    $bot = publishedBot();
    say($bot, 'hi');

    // The menu question gets reworded and republished mid-conversation.
    $graph = $bot->draft_graph;
    foreach ($graph['nodes'] as &$n) {
        if ($n['id'] === 'prices_text') {
            $n['data']['text'] = 'NEW PRICES';
        }
    }
    $bot->update(['draft_graph' => $graph]);
    app(ChatbotPublisher::class)->publish($bot);

    expect(texts(say($bot, 'prices'))[0])->toBe('Haircut from $35, coloring from $80, manicure from $25.')
        ->and(texts(say($bot, 'hi', 'someone-new'))[0])->toBe('Hi Alex! I am the virtual assistant.')
        ->and(texts(say($bot, 'prices', 'someone-new'))[0])->toBe('NEW PRICES');
});

it('stops a runaway scenario with the step budget', function () {
    // An infinite loop can only exist in an unpublished draft; test sessions run drafts.
    $bot = Chatbot::factory()->graph(graph(
        [node('s', 'start'), node('a', 'message', ['text' => 'tick']), node('b', 'condition', ['rules' => [['field' => 'contact.visits', 'operator' => 'gte', 'value' => '0']]])],
        [edge('s', 'next', 'a'), edge('a', 'next', 'b'), edge('b', 'true', 'a'), edge('b', 'false', 'a')],
    ))->create();

    $result = app(ConversationEngine::class)->handle($bot, new InboundMessage(ChannelType::Web, 't', 'hi'), test: true);

    expect($result->session->status)->toBe(SessionStatus::Failed)
        ->and(count($result->replies))->toBeLessThanOrEqual(ConversationEngine::MAX_STEPS);
});

it('records the full transcript', function () {
    $bot = publishedBot();
    say($bot, 'hi');
    $result = say($bot, 'prices');

    expect($result->session->messages->pluck('direction')->all())->toBe(['in', 'out', 'out', 'in', 'out', 'out'])
        ->and($result->session->messages->last()->node_id)->toBe('after_prices');
});
