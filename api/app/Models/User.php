<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'is_active', 'organization_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** One role per user. Guests (no account) only see the public catalogue. */
    public const ROLES = ['uploader', 'curator', 'admin'];

    public const ROLE_LABELS = [
        'uploader' => 'Uploader',
        'curator' => 'Curator',
        'admin' => 'Admin',
    ];

    /** 3–30 characters: lowercase letters, digits, dot and underscore. */
    public const USERNAME_PATTERN = '/^[a-z0-9._]{3,30}$/';

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
            'is_active' => 'boolean',
        ];
    }

    /** Usernames and e-mail addresses are stored lowercase, so lookups are case-insensitive. */
    protected function username(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Curators and admins decide on datasets (approve, reject, reopen). */
    public function canCurate(): bool
    {
        return in_array($this->role, ['curator', 'admin'], true);
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
    }

    /** Identity the internal pages need on the client (see layouts/app.blade.php). */
    public function toClient(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'role' => $this->role];
    }
}
