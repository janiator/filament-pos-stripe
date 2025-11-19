<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Store;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasTenants, HasDefaultTenant
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
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
    public function canAccessPanel(Panel $panel): bool
    {
        if (app()->environment('production')) {
            return str_ends_with($this->email, '@visivo.no') && $this->hasVerifiedEmail();
        } elseif (app()->environment('local')) {
            return true;
        }else {
            return false;
        }
    }

    // Tenancy methods
    public function getTenants(Panel $panel): Collection
    {
        // Super admins can access all stores
        if ($this->isSuperAdmin()) {
            return Store::all();
        }
        
        return $this->stores;
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class);
    }

    public function canAccessTenant(Model $tenant): bool
    {
        // Super admins can access all stores
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        return $this->stores->contains($tenant);
    }

    /**
     * Check if user is a super admin, bypassing tenant scoping
     */
    protected function isSuperAdmin(): bool
    {
        try {
            // Use withoutGlobalScopes to bypass tenant scoping for role checks
            $tenant = \Filament\Facades\Filament::getTenant();
            if ($tenant) {
                return $this->roles()->withoutGlobalScopes()->where('name', 'super_admin')->exists();
            }
            return $this->hasRole('super_admin');
        } catch (\Throwable $e) {
            // Fallback to regular check if Filament facade is not available
            return $this->hasRole('super_admin');
        }
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        // Return the user's first store, or null if they have no stores
        return $this->stores()->first();
    }
}
