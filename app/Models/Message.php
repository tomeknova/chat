<?php

namespace App\Models;

use App\Enums\CorpusProfile;
use App\Enums\MessageRole;
use App\Enums\ProductStatus;
use App\Enums\Rating;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'profile',
        'role',
        'content',
        'normalized_question_hash',
        'product_status',
        'rating',
        'rating_reason_code',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profile' => CorpusProfile::class,
            'role' => MessageRole::class,
            'product_status' => ProductStatus::class,
            'rating' => Rating::class,
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return HasMany<Generation, $this>
     */
    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }
}
