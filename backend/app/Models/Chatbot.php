<?php

namespace App\Models;

use App\Chatbot\Graph\Graph;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chatbot extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['draft_graph' => 'array'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ChatbotVersion::class)->orderByDesc('version');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(ChatbotVersion::class, 'published_version_id');
    }

    public function bindings(): HasMany
    {
        return $this->hasMany(ChannelBinding::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ConversationSession::class);
    }

    public function draft(): Graph
    {
        return Graph::fromArray($this->draft_graph);
    }
}
