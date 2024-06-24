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

    public function chats() {
        return $this->belongsToMany(Chat::class);
    }

    public function templates() {
        return $this->belongsToMany(Template::class);
    }

    public function logs() {
        return $this->hasMany(Log::class);
    }

    public function fiches() {
        return $this->hasMany(Fiche::class);
    }
}
