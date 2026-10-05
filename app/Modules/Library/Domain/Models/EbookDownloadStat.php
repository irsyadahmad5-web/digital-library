<?php

namespace App\Modules\Library\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbookDownloadStat extends Model
{
    protected $fillable = [
        'ebook_id',
        'downloads',
        'last_downloaded_at',
    ];

    protected function casts(): array
    {
        return [
            'downloads' => 'integer',
            'last_downloaded_at' => 'datetime',
        ];
    }

    public function ebook(): BelongsTo
    {
        return $this->belongsTo(Ebook::class);
    }
}
