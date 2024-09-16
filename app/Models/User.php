<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function chats() {
        return $this->hasMany(Chat::class);
    }

    public function fiches() {
        return $this->hasMany(Fiche::class);
    }

    /**
     * The boards that belong to the user.
     */
    public function boards() {
        return $this->belongsToMany(Board::class)->withPivot('role_id');
    }

    public function templates() {
        return $this->belongsToMany(Template::class);
    }

    /**
     * Get the roles for the user.
     */
    public function roles() {
        return $this->belongsToMany(Role::class);
    }

    public function hasRolePermission($permissionName)
    {
        // Récupère toutes les permissions des rôles de l'utilisateur
        $permissions = $this->roles->pluck('permissions')->flatten()->pluck('name');

        // Vérifie si l'utilisateur a la permission demandée
        return $permissions->contains($permissionName);
    }
}
