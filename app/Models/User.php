<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Filament\Resources\PosSessions\PosSessionResource;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Facades\Filament;
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
        if (app()->environment(['local', 'development'])) {
            return true;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->stores()->exists()) {
            return false;
        }

        return $this->hasAnyFilamentResourcePermission($panel);
    }

    /**
     * Whether the user holds at least one Shield permission tied to a Filament Resource
     * registered on the given panel (e.g. ViewAny:PosSession), excluding pages/widgets only.
     */
    protected function hasAnyFilamentResourcePermission(Panel $panel): bool
    {
        $previousPanel = Filament::getCurrentPanel();

        try {
            Filament::setCurrentPanel($panel);

            $resourcePermissionKeys = collect(FilamentShield::getAllResourcePermissionsWithLabels())
                ->keys();

            if ($resourcePermissionKeys->isEmpty()) {
                return false;
            }

            $userPermissionNames = $this->getAllPermissions()->pluck('name');

            return $userPermissionNames->intersect($resourcePermissionKeys)->isNotEmpty();
        } finally {
            Filament::setCurrentPanel($previousPanel);
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
        // Verify user has access to this store
        if (! $this->stores->contains($store)) {
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
     * Check if user is a super admin, bypassing tenant scoping
     */
    public function isSuperAdmin(): bool
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
        // Return the user's current store, or first store if not set, or null if they have no stores
        return $this->currentStore();
    }

    /**
     * Target URL after Filament impersonation. Tenant routes require the user to be
     * attached to the store (store_user); redirecting here avoids 404 on the previous URL.
     */
    public function impersonationRedirectUrl(): string
    {
        $panel = \Filament\Facades\Filament::getPanel('app');
        $tenant = $this->getTenants($panel)->first();

        if (! $tenant instanceof Store) {
            return route('filament.app.auth.profile');
        }

        if ($this->can('View:Dashboard')) {
            return route('filament.app.pages.dashboard', ['tenant' => $tenant]);
        }

        if ($this->can('ViewAny:PosSession')) {
            return PosSessionResource::getUrl('index', [], true, 'app', $tenant);
        }

        return route('filament.app.auth.profile');
    }
}
