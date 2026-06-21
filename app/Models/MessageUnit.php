<?php

namespace App\Models;

use App\Enums\ValidationStatus;
use Database\Factories\MessageUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageUnit extends Model
{
    /** @use HasFactory<MessageUnitFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'generation_id',
        'answer_unit_id',
        'validation_status',
        'display_ordinal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'validation_status' => ValidationStatus::class,
            'display_ordinal' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Generation, $this>
     */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
