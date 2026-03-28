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
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Lab404\Impersonate\Models\Impersonate as ImpersonateTrait;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, ImpersonateTrait, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'current_store_id',
        'email_verified_at',
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
        } elseif (app()->environment('development')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Only super admins can impersonate other users.
     */
    public function canImpersonate(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Users can be impersonated unless they are super admins.
     */
    public function canBeImpersonated(): bool
    {
        return ! $this->isSuperAdmin();
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

    /**
     * Get the user's current store
     */
    public function currentStore(): ?Store
    {
        if ($this->current_store_id) {
            // Super admins can access any store
            if ($this->isSuperAdmin()) {
                return Store::find($this->current_store_id);
            }

            return $this->stores()->where('stores.id', $this->current_store_id)->first();
        }

        // Fallback to first store if no current store is set
        return $this->stores()->first();
    }

    /**
     * Set the current store for the user
     */
    public function setCurrentStore(Store $store): bool
    {
        // Super admins can set any store as current
        if (! $this->isSuperAdmin() && ! $this->stores->contains($store)) {
            return false;
        }

        $this->current_store_id = $store->id;

        return $this->save();
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
     * Check whether the user has the global super admin role.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasGlobalSuperAdminRole();
    }

    /**
     * Resolve global super-admin status without tenant-scoped role queries.
     */
    public function hasGlobalSuperAdminRole(): bool
    {
        $superAdminRoleName = (string) config('filament-shield.super_admin.name', 'super_admin');

        return $this->roles()
            ->withoutGlobalScopes()
            ->where('name', $superAdminRoleName)
            ->exists();
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        // Return the user's current store, or first store if not set, or null if they have no stores
        return $this->currentStore();
    }
}
