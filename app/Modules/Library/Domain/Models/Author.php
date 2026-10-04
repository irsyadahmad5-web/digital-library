<?php

namespace App\Modules\Library\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Author extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'bio', 'website', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function ebooks(): BelongsToMany
    {
        return $this->belongsToMany(Ebook::class, 'ebook_author')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }
}
