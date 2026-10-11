<?php

namespace App\Enums;

enum ChannelType: string
{
    case Web = 'web';
    case Telegram = 'telegram';
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web widget',
            self::Telegram => 'Telegram',
            self::WhatsApp => 'WhatsApp',
            self::Sms => 'SMS',
        };
    }
}
