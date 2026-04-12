<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use App\Concerns\HasTeams;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Livewirez\Billing\Traits\IsBillable;
use Livewirez\Billing\Interfaces\Billable;
use Livewirez\Webauthn\PasskeyAuthenticatableTrait;
use Livewirez\Webauthn\PasskeyAuthenticatable;

#[Fillable(['name', 'email', 'password', 'current_team_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements Billable, PasskeyAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasApiTokens, HasTeams, Notifiable, TwoFactorAuthenticatable, IsBillable, PasskeyAuthenticatableTrait {
        // Choose billing version as the main getName()
        IsBillable::getName insteadof PasskeyAuthenticatableTrait;

        // Alias the passkey version
        PasskeyAuthenticatableTrait::getName as getPasskeyName;
    }

    // Now satisfy PasskeyAuthenticatable interface
    public function getName(int $nameType = self::FIRST_NAME): string
    {
        // adapt billing method to expected signature
        return $this->getPasskeyName();
    }
    

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
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}