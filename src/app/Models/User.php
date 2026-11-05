<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\OperationalAccess;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = ['role' => 'worker', 'is_active' => true];

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            validator($user->getAttributes(), ['role' => ['required', Rule::in(array_keys(OperationalAccess::ROLES))], 'is_active' => ['required', 'boolean']])->validate();
            if ($user->exists && $user->id === auth()->id() && (! $user->is_active || $user->role !== $user->getRawOriginal('role'))) {
                throw ValidationException::withMessages(['role' => 'No puede desactivar ni cambiar el perfil de su propia sesión. Use otro administrador.']);
            }
        });
        static::saved(function (self $user): void {
            if ($user->wasChanged(['role', 'is_active'])) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->exists && $this->is_active && $panel->getId() === 'admin';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
