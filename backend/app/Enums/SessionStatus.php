<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Handoff = 'handoff';   // a human operator took over, the bot stays silent
    case Expired = 'expired';
    case Failed = 'failed';     // runtime guard tripped (e.g. step limit)
}
