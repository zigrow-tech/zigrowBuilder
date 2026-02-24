<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;  // Missing in User.php



class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;  // ✅ Add HasApiTokens
    protected $rememberTokenName = 'remember_token';
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'email','remaining_credits','zoho_id', 'password',
    'phone', 'company', 'bio', 'country', 'state',
    'postal_code', 'gst_number', 'avatar',
    'facebook', 'xcom', 'linkedin', 'instagram','user_id', 'remember_token','role','plan_type', 'plan_name', 'plan_price', 'plan_started_at', 'plan_expires_at','google_id', 'facebook_id'
    ];

    // This will run automatically before a record is created
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            // Only generate if not already set (in case you seed or assign manually)
            if (!$user->user_id) {
                $user->user_id = self::generateUserId();
            }
        });
    }

    /**
     * Generate a new user_id like "Z-000001", "Z-000002", etc.
     */
    protected static function generateUserId() {
        // 1) Get the last user by descending order of the *existing* user_id
        //    or by the primary key if user_id is not strictly guaranteed numeric order.
        $lastUser = self::orderBy('id', 'desc')->first();

        if (!$lastUser || !$lastUser->user_id) {
            // If there's no user or user_id is missing, start at Z-000001
            return 'Z-000001';
        }

        // Example lastUser->user_id = "Z-000015"
        $lastId = $lastUser->user_id;     // "Z-000015"
        $numericPart = (int) substr($lastId, 2); // 15
        $newNumericPart = $numericPart + 1;       // 16

        // Format back to "Z-000016" with zero-padding to 6 digits
        return 'Z-' . str_pad($newNumericPart, 6, '0', STR_PAD_LEFT);
    }


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


    public function planPayments()
{
    return $this->hasMany(PlanPayment::class, 'user_id', 'user_id'); // ✅ Custom key
}

public function publishedDomain()
{
    //return $this->hasOne(PublishedDomain::class, 'user_id', 'id');

     return $this->hasOne(\App\Models\PublishedDomain::class, 'user_id', 'user_id'); 
}


}
	
