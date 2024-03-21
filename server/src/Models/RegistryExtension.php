<?php

namespace Fleetbase\RegistryBridge\Models;

use Fleetbase\Casts\Json;
use Fleetbase\Models\Company;
use Fleetbase\Models\File;
use Fleetbase\Models\Model;
use Fleetbase\Models\User;
use Fleetbase\Traits\HasApiModelBehavior;
use Fleetbase\Traits\HasMetaAttributes;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\Searchable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class RegistryExtension extends Model
{
    use HasUuid;
    use HasPublicId;
    use HasMetaAttributes;
    use HasApiModelBehavior;
    use HasSlug;
    use Searchable;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'registry_extensions';

    /**
     * The type of public Id to generate.
     *
     * @var string
     */
    protected $publicIdType = 'extension';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'created_by_uuid',
        'registry_user_uuid',
        'icon_uuid',
        'public_id',
        'name',
        'subtitle',
        'payment_required',
        'price',
        'sale_price',
        'on_sale',
        'subscription_required',
        'subscription_billing_period',
        'subscription_model',
        'subscription_amount',
        'subscription_tiers',
        'slug',
        'version',
        'fa_icon',
        'description',
        'promotional_text',
        'website_url',
        'repo_url',
        'support_url',
        'privacy_policy_url',
        'tos_url',
        'copyright',
        'primary_language',
        'tags',
        'languages',
        'meta',
        'core_extension',
        'status',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'payment_required'      => 'boolean',
        'on_sale'               => 'boolean',
        'subscription_required' => 'boolean',
        'subscription_tiers'    => Json::class,
        'tags'                  => Json::class,
        'languages'             => Json::class,
        'meta'                  => Json::class,
        'core_extension'        => 'boolean',
    ];

    /**
     * Dynamic attributes that are appended to object.
     *
     * @var array
     */
    protected $appends = [
        'icon_url',
    ];

    /**
     * Searchable columns.
     *
     * @var array
     */
    protected $searchableColumns = ['name'];

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_uuid', 'uuid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function registryUser()
    {
        return $this->belongsTo(RegistryUser::class, 'registry_user_uuid', 'uuid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function icon()
    {
        return $this->belongsTo(File::class, 'icon_uuid', 'uuid');
    }

    /**
     * Get avatar URL attribute.
     *
     * @return string
     */
    public function getIconUrlAttribute()
    {
        if ($this->icon instanceof File) {
            return $this->icon->url;
        }

        return data_get($this, 'icon.url', 'https://flb-assets.s3.ap-southeast-1.amazonaws.com/static/default-extension-icon.svg');
    }
}
