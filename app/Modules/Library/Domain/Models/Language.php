<?php

namespace App\Modules\Library\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Language extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'native_name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function ebooks(): HasMany
    {
        return $this->hasMany(Ebook::class);
    }
}
