<?php

namespace App\Chatbot\Fakes;

use App\Chatbot\Contracts\BookingGateway;
use App\Models\Booking;
use App\Models\Contact;
use Illuminate\Support\Str;

/** Stores the request locally instead of calling an external booking system. */
class FakeBookingGateway implements BookingGateway
{
    public function request(Contact $contact, string $service, ?string $preferredTime, ?int $sessionId): string
    {
        $reference = 'BK-'.Str::upper(Str::random(6));

        Booking::create([
            'contact_id' => $contact->id,
            'conversation_session_id' => $sessionId,
            'service' => $service,
            'preferred_time' => $preferredTime,
            'reference' => $reference,
        ]);

        return $reference;
    }
}
