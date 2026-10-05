<?php

namespace App\Modules\Analytics\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SiteDailyMetric extends Model
{
    protected $fillable = [
        'metric_date',
        'page_views',
        'home_views',
        'catalog_views',
        'directory_views',
        'book_views',
        'reader_opens',
        'info_views',
        'downloads',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'page_views' => 'integer',
            'home_views' => 'integer',
            'catalog_views' => 'integer',
            'directory_views' => 'integer',
            'book_views' => 'integer',
            'reader_opens' => 'integer',
            'info_views' => 'integer',
            'downloads' => 'integer',
        ];
    }
}
