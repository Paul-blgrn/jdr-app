<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'content',
        'type',
        'default',
    ];

    public function boards() {
        return $this->belongsToMany(Board::class);
    }

    public function users() {
        return $this->belongsToMany(User::class);
    }
}
