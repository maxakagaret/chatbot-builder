<?php

namespace App\Models;

use App\Enums\ChannelType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelBinding extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'channel';

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['channel' => ChannelType::class];
    }

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }
}
