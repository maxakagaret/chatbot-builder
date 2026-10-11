<?php

namespace App\Models;

use App\Chatbot\Graph\Graph;
use App\Enums\ChannelType;
use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversationSession extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'channel' => ChannelType::class,
            'status' => SessionStatus::class,
            'variables' => 'array',
            'is_test' => 'boolean',
            'started_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ChatbotVersion::class, 'chatbot_version_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('id');
    }

    /** Test sessions run the editor's draft; live ones the version they started on. */
    public function graph(): Graph
    {
        return $this->is_test || ! $this->version
            ? $this->chatbot->draft()
            : $this->version->toGraph();
    }
}
