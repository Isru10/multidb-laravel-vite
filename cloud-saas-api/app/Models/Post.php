<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Post extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'user_id',
        'title',
        'description',
    ];

    /**
     * A post belongs to a user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
