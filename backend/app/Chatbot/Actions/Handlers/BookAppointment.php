<?php

namespace App\Chatbot\Actions\Handlers;

use App\Chatbot\Actions\ActionHandler;
use App\Chatbot\Contracts\BookingGateway;
use App\Models\ConversationSession;

/**
 * Creates a booking request in the (fake) booking system from values the user
 * entered during the conversation and stores the reference in vars.booking_ref.
 */
class BookAppointment implements ActionHandler
{
    public function __construct(private readonly BookingGateway $bookings) {}

    public function key(): string
    {
        return 'book_appointment';
    }

    public function label(): string
    {
        return 'Create a booking request';
    }

    public function params(): array
    {
        return [
            'service' => ['label' => 'Service (templates allowed)', 'required' => true],
            'preferred_time' => ['label' => 'Preferred time (templates allowed)', 'required' => false],
        ];
    }

    public function validate(array $params): array
    {
        return trim((string) ($params['service'] ?? '')) === '' ? ['Service is required.'] : [];
    }

    public function handle(ConversationSession $session, array $params): ?string
    {
        $reference = $this->bookings->request($session->contact, $params['service'], $params['preferred_time'] ?? null, $session->id);

        $session->variables = [...($session->variables ?? []), 'booking_ref' => $reference];

        return null;
    }
}
