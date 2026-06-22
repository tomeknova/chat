<?php

namespace App\Models;

use App\Enums\InfraStatus;
use App\Enums\ProcessingStatus;
use App\Enums\ResponseType;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'message_id',
        'operation_id',
        'model',
        'response_type',
        'input_tokens',
        'output_tokens',
        'cost',
        'infra_status',
        'status',
        'processing_owner',
        'processing_started_at',
        'lease_expires_at',
        'request_fingerprint',
        'execution_attempt',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_type' => ResponseType::class,
            'infra_status' => InfraStatus::class,
            'status' => ProcessingStatus::class,
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost' => 'decimal:8',
            'execution_attempt' => 'integer',
            'processing_started_at' => 'datetime',
            'lease_expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * What the model saw — basis of validation.
     *
     * @return HasMany<GenerationContext, $this>
     */
    public function context(): HasMany
    {
        return $this->hasMany(GenerationContext::class);
    }

    /**
     * @return HasMany<MessageUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(MessageUnit::class);
    }
}
