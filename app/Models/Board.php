<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Board extends Model
{
    use HasFactory;

    /**
     * The users that belong to the board.
     */
    public function users() {
        return $this->belongsToMany(User::class)->withPivot('role');
    }
}
