<?php

namespace App\Chatbot\Contracts;

use App\Models\Contact;

/** Booking system integration. The demo binds FakeBookingGateway. */
interface BookingGateway
{
    /** @return string booking reference shown to the user */
    public function request(Contact $contact, string $service, ?string $preferredTime, ?int $sessionId): string;
}
