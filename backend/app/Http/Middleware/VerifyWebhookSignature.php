<?php

namespace App\Http\Middleware;

use App\Enums\ChannelType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Messenger/SMS webhooks must carry an HMAC-SHA256 of the raw body. The web
 * widget is a public browser endpoint and is rate limited instead.
 */
class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $channel = $request->route('channel');
        $channel = $channel instanceof ChannelType ? $channel : ChannelType::tryFrom((string) $channel);

        if ($channel === ChannelType::Web) {
            return $next($request);
        }

        $expected = hash_hmac('sha256', $request->getContent(), (string) config('chatbot.webhook_secret'));

        if (! hash_equals($expected, (string) $request->header('X-Signature'))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        return $next($request);
    }
}
