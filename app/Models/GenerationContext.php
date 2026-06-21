<?php

namespace App\Models;

use Database\Factories\GenerationContextFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GenerationContext extends Model
{
    /** @use HasFactory<GenerationContextFactory> */
    use HasFactory;

    protected $table = 'generation_context';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'generation_id',
        'answer_unit_id',
        'content_hash',
    ];

    /**
     * @return BelongsTo<Generation, $this>
     */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
