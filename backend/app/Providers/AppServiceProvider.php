<?php

namespace App\Providers;

use App\Chatbot\Actions\ActionRegistry;
use App\Chatbot\Actions\Handlers\AddTag;
use App\Chatbot\Actions\Handlers\BookAppointment;
use App\Chatbot\Actions\Handlers\Handoff;
use App\Chatbot\Actions\Handlers\OptOut;
use App\Chatbot\Actions\Handlers\SetVariable;
use App\Chatbot\Channels\ChannelRegistry;
use App\Chatbot\Channels\SmsAdapter;
use App\Chatbot\Channels\TelegramAdapter;
use App\Chatbot\Channels\WebAdapter;
use App\Chatbot\Channels\WhatsAppAdapter;
use App\Chatbot\Contracts\BookingGateway;
use App\Chatbot\Contracts\OutboundGateway;
use App\Chatbot\Fakes\FakeBookingGateway;
use App\Chatbot\Fakes\FakeOutboundGateway;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** New step actions or channels are one class + one line here. */
    private const ACTIONS = [SetVariable::class, AddTag::class, BookAppointment::class, Handoff::class, OptOut::class];

    private const CHANNELS = [WebAdapter::class, TelegramAdapter::class, WhatsAppAdapter::class, SmsAdapter::class];

    public function register(): void
    {
        // Integrations: interfaces with fake implementations (swap for real ones here).
        $this->app->bind(BookingGateway::class, FakeBookingGateway::class);
        $this->app->bind(OutboundGateway::class, FakeOutboundGateway::class);

        $this->app->tag(self::ACTIONS, 'chatbot.actions');
        $this->app->tag(self::CHANNELS, 'chatbot.channels');

        $this->app->singleton(ActionRegistry::class, fn (Application $app) => new ActionRegistry($app->tagged('chatbot.actions')));
        $this->app->singleton(ChannelRegistry::class, fn (Application $app) => new ChannelRegistry($app->tagged('chatbot.channels')));
    }
}
