<?php

namespace App\Models;

use App\Enums\CorpusProfile;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'owner_token_hash',
        'profile',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profile' => CorpusProfile::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation): void {
            if (empty($conversation->public_id)) {
                $conversation->public_id = (string) Str::ulid();
            }
        });
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
