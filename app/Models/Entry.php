<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['diagram' => 'array', 'blocks' => 'array', 'tags' => 'array', 'pinned' => 'boolean', 'archived' => 'boolean'];
    }

    public function media()
    {
        return $this->hasMany(Media::class);
    }

    public function sharedUsers()
    {
        return $this->belongsToMany(User::class)->withPivot('permission');
    }
}
