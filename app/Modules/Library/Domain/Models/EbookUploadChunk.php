<?php

namespace App\Modules\Library\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbookUploadChunk extends Model
{
    protected $fillable = [
        'upload_session_id',
        'chunk_index',
        'size_bytes',
        'sha256',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EbookUploadSession::class, 'upload_session_id');
    }
}
