<?php

namespace App\Modules\Analytics\Domain\Models;

use App\Modules\Library\Domain\Models\Ebook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EbookDailyMetric extends Model
{
    protected $fillable = [
        'metric_date',
        'ebook_id',
        'detail_views',
        'reader_opens',
        'downloads',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'detail_views' => 'integer',
            'reader_opens' => 'integer',
            'downloads' => 'integer',
        ];
    }

    public function ebook(): BelongsTo
    {
        return $this->belongsTo(Ebook::class);
    }
}
