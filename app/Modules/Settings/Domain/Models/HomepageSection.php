<?php

namespace App\Modules\Settings\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageSection extends Model
{
    protected $fillable = [
        'type',
        'title',
        'config_json',
        'is_enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'is_enabled' => 'boolean',
        ];
    }
}
