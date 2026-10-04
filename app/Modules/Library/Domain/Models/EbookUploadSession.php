<?php

namespace App\Modules\Library\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EbookUploadSession extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'ebook_id',
        'user_id',
        'original_name',
        'total_size',
        'chunk_size',
        'total_chunks',
        'received_chunks',
        'status',
        'expected_sha256',
        'computed_sha256',
        'temp_directory',
        'expires_at',
        'completed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'total_size' => 'integer',
            'chunk_size' => 'integer',
            'total_chunks' => 'integer',
            'received_chunks' => 'integer',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function ebook(): BelongsTo
    {
        return $this->belongsTo(Ebook::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(EbookUploadChunk::class, 'upload_session_id');
    }
}
