<?php

namespace App\Models;

use App\Chatbot\Graph\Graph;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotVersion extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'graph' => 'array',
            'published_at' => 'immutable_datetime',
        ];
    }

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }

    public function toGraph(): Graph
    {
        return Graph::fromArray($this->graph);
    }
}
