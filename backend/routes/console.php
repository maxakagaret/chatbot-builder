<?php

use App\Enums\SessionStatus;
use App\Models\ConversationSession;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// The engine expires idle sessions lazily on the next message; this sweep keeps
// the session statistics honest for users who never come back.
Artisan::command('chatbot:expire-sessions', function () {
    $expired = ConversationSession::where('status', SessionStatus::Active)
        ->where('last_activity_at', '<', now()->subMinutes((int) config('chatbot.session_ttl_minutes')))
        ->update(['status' => SessionStatus::Expired, 'ended_at' => now()]);

    $this->info("Expired {$expired} session(s).");
})->purpose('Mark idle conversations as expired');

Schedule::command('chatbot:expire-sessions')->everyFiveMinutes()->withoutOverlapping();
