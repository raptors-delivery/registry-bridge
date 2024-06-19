<?php

namespace Fleetbase\RegistryBridge\Models;

use Fleetbase\Casts\Json;
use Fleetbase\Models\Company;
use Fleetbase\Models\Model;
use Fleetbase\Models\User;
use Fleetbase\Traits\Expirable;
use Fleetbase\Traits\HasMetaAttributes;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\HasUuid;

class RegistryUser extends Model
{
    use HasUuid;
    use HasMetaAttributes;
    use HasPublicId;
    use Expirable;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'registry_users';

    /**
     * The type of public Id to generate.
     *
     * @var string
     */
    protected $publicIdType = 'registry_user';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'company_uuid',
        'user_uuid',
        'token',
        'scope',
        'expires_at',
        'last_used_at',
        'name',
        'metadata',
        'revoked',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'meta'         => Json::class,
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    /**
     * Dynamic attributes that are appended to object.
     *
     * @var array
     */
    protected $appends = [];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The "booting" method of the model.
     *
     * This method is called when the model is being booted and is used to define
     * model event hooks. In this model, it is used to automatically generate and
     * assign a unique token to the `token` field when creating a new RegistryUser
     * instance. The token is generated only if it hasn't been set already, ensuring
     * that manually specified tokens are respected.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Hook into the 'creating' event to set the token for new models
        static::creating(function ($model) {
            // Set the token only if it's not already set
            if (empty($model->token)) {
                $model->token = self::generateToken();
            }
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generates a unique token for authenticating with the registry.
     *
     * This method creates a token prefixed with 'flb_' and ensures its uniqueness
     * within the `registry_users` table. The token is generated using secure random
     * bytes, converted to a hexadecimal string.
     *
     * @param int the length of the unique token string
     *
     * @return string the unique, generated token
     */
    public static function generateToken(int $length = 18): string
    {
        do {
            // Generate a random string and prepend with 'flb_'
            $token = 'flb_' . bin2hex(random_bytes($length));

            // Check if the token is unique in the database
            $tokenExists = self::where('token', $token)->exists();
        } while ($tokenExists);

        return $token;
    }
}
