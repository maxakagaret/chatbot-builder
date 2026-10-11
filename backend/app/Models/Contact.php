<?php

namespace App\Models;

use App\Enums\ChannelType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'opted_out' => 'boolean',
            'is_test' => 'boolean',
            'visits' => 'integer',
        ];
    }

    public function identities(): HasMany
    {
        return $this->hasMany(ContactIdentity::class);
    }

    public function firstName(): string
    {
        return strtok($this->name, ' ') ?: $this->name;
    }

    /** Finds the contact behind a channel identity or creates a new one. */
    public static function resolve(ChannelType $channel, string $externalId, ?string $name = null, bool $isTest = false): self
    {
        $identity = ContactIdentity::where('channel', $channel)->where('external_id', $externalId)->first();

        if ($identity) {
            return $identity->contact;
        }

        $contact = self::create([
            'name' => $name ?: 'Guest',
            'phone' => in_array($channel, [ChannelType::WhatsApp, ChannelType::Sms], true) ? $externalId : null,
            'is_test' => $isTest,
            'tags' => [],
        ]);
        $contact->identities()->create(['channel' => $channel, 'external_id' => $externalId]);

        return $contact;
    }

    public function addTag(string $tag): void
    {
        $this->tags = array_values(array_unique([...($this->tags ?? []), $tag]));
        $this->save();
    }
}
