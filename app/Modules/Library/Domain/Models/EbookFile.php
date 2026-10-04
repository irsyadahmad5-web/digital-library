<?php

namespace App\Modules\Library\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbookFile extends Model
{
    protected $fillable = [
        'ebook_id',
        'source_type',
        'disk',
        'path',
        'external_url',
        'original_name',
        'mime_type',
        'size_bytes',
        'sha256',
        'etag',
        'last_modified',
        'verification_status',
        'processing_status',
        'page_count',
        'pdf_metadata',
        'preview_path',
        'processing_started_at',
        'processed_at',
        'processing_error',
        'verified_at',
        'last_checked_at',
        'last_error',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'page_count' => 'integer',
            'pdf_metadata' => 'array',
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function ebook(): BelongsTo
    {
        return $this->belongsTo(Ebook::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
