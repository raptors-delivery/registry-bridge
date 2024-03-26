<?php

namespace Fleetbase\RegistryBridge\Models;

use Fleetbase\Casts\Json;
use Fleetbase\Casts\Money;
use Fleetbase\Models\Category;
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
        'category_uuid',
        'registry_user_uuid',
        'current_bundle_uuid',
        'next_bundle_uuid',
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
        'currency',
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
        'price'                 => Money::class,
        'sale_price'            => Money::class,
        'subscription_amount'   => Money::class,
    ];

    /**
     * Dynamic attributes that are appended to object.
     *
     * @var array
     */
    protected $appends = [
        'icon_url',
        'current_bundle_filename',
        'current_bundle_id',
        'current_bundle_public_id',
        'next_bundle_filename',
        'next_bundle_id',
        'next_bundle_public_id',
    ];

    /**
     * Relations that should be loaded with model.
     *
     * @var array
     */
    protected $with = [
        'category',
    ];

    /**
     * Relations that should not be loaded.
     *
     * @var array
     */
    protected $without = [
        'current_bundle',
        'next_bundle',
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
    public function currentBundle()
    {
        return $this->belongsTo(RegistryExtensionBundle::class, 'current_bundle_uuid', 'uuid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function nextBundle()
    {
        return $this->belongsTo(RegistryExtensionBundle::class, 'next_bundle_uuid', 'uuid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function icon()
    {
        return $this->belongsTo(File::class, 'icon_uuid', 'uuid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_uuid', 'uuid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function screenshots()
    {
        return $this->hasMany(File::class, 'subject_uuid', 'uuid')->where('type', 'extension_screenshot');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function bundles()
    {
        return $this->hasMany(RegistryExtensionBundle::class, 'extension+uuid', 'uuid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function bundleFiles()
    {
        return $this->hasMany(File::class, 'subject_uuid', 'uuid')->where('type', 'extension_bundle');
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

    /**
     * Get the current bundle public ID.
     *
     * @return string
     */
    public function getCurrentBundlePublicIdAttribute()
    {
        if ($this->currentBundle instanceof RegistryExtensionBundle) {
            return $this->currentBundle->public_id;
        }

        return data_get($this, 'currentBundle.public_id');
    }

    /**
     * Get the current bundle ID.
     *
     * @return string
     */
    public function getCurrentBundleIdAttribute()
    {
        if ($this->currentBundle instanceof RegistryExtensionBundle) {
            return $this->currentBundle->bundle_id;
        }

        return data_get($this, 'currentBundle.bundle_id');
    }

    /**
     * Get the current bundle original filename.
     *
     * @return string
     */
    public function getCurrentBundleFilenameAttribute()
    {
        if ($this->currentBundle instanceof RegistryExtensionBundle) {
            return $this->currentBundle->bundle_filename;
        }

        return data_get($this, 'currentBundle.bundle_filename');
    }

    /**
     * Get the next bundle public ID.
     *
     * @return string
     */
    public function getNextBundlePublicIdAttribute()
    {
        if ($this->nextBundle instanceof RegistryExtensionBundle) {
            return $this->nextBundle->public_id;
        }

        return data_get($this, 'nextBundle.public_id');
    }

    /**
     * Get the next bundle ID.
     *
     * @return string
     */
    public function getNextBundleIdAttribute()
    {
        if ($this->nextBundle instanceof RegistryExtensionBundle) {
            return $this->nextBundle->bundle_id;
        }

        return data_get($this, 'nextBundle.bundle_id');
    }

    /**
     * Get the current bundle original filename.
     *
     * @return string
     */
    public function getNextBundleFilenameAttribute()
    {
        if ($this->nextBundle instanceof RegistryExtensionBundle) {
            return $this->nextBundle->bundle_filename;
        }

        return data_get($this, 'nextBundle.bundle_filename');
    }

    /**
     * Finds a RegistryExtension by package name in the associated currentBundle.
     *
     * This method searches for a RegistryExtension where the associated currentBundle's
     * 'meta' JSON column contains the specified package name either in the 'api' field
     * or the 'engine' field. It returns the first matching RegistryExtension or null
     * if no matches are found. The method leverages Eloquent's relationship querying
     * capabilities to efficiently filter the results.
     *
     * @param string $packageName the name of the package to search for in the 'api' or 'engine' fields
     *
     * @return RegistryExtension|null the first RegistryExtension that matches the search criteria, or null if no match is found
     */
    public static function findByPackageName(string $packageName): ?RegistryExtension
    {
        return static::whereHas('currentBundle', function ($query) use ($packageName) {
            $query->where('meta->package.json->name', $packageName)->orWhere('meta->composer.json->name', $packageName);
        })->first();
    }

    /**
     * Determines if the current extension instance is ready for submission.
     *
     * This method is an instance method that internally calls the static method
     * `isExtensionReadyForSubmission` to perform the validation on the current instance.
     * It checks various fields of the extension for certain criteria such as minimum
     * string lengths, presence of necessary fields, and URL validation.
     *
     * @return bool returns true if the extension instance passes all validations, false otherwise
     */
    public function isReadyForSubmission(): bool
    {
        return static::isExtensionReadyForSubmission($this);
    }

    /**
     * Validates if an extension, identified by its ID or instance, is ready for submission.
     *
     * This method accepts either an extension ID or an instance of `RegistryExtension`. It then
     * performs various validations on fields like 'name', 'description', 'tags', etc., to
     * determine if the extension meets the criteria for submission. URL fields are validated
     * to ensure they contain proper URLs. The method is designed to be flexible, handling
     * validation for both an extension ID and an extension object.
     *
     * @param int|RegistryExtension $extensionId the ID of the extension or an instance of `RegistryExtension`
     *
     * @return bool returns true if the extension passes all validations, false otherwise
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException if the extension with the given ID is not found
     */
    public static function isExtensionReadyForSubmission($extensionId): bool
    {
        if ($extensionId instanceof RegistryExtension) {
            $extension = $extensionId;
        } else {
            $extension = self::find($extensionId);
        }
        if (!$extension) {
            return false;
        }

        $validations = [
            'name' => function ($value) {
                return is_string($value) && strlen($value) > 3;
            },
            'description' => function ($value) {
                return is_string($value) && strlen($value) > 12;
            },
            'tags' => function ($value) {
                return !empty($value);
            },
            'promotional_text' => function ($value) {
                return !empty($value);
            },
            'subtitle' => function ($value) {
                return !empty($value);
            },
            'copyright' => function ($value) {
                return !empty($value);
            },
            'website_url' => function ($value) {
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
            },
            'support_url' => function ($value) {
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
            },
            'privacy_policy_url' => function ($value) {
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
            },
            'icon_uuid' => function ($value) {
                return !empty($value);
            },
            'category_uuid' => function ($value) {
                return !empty($value);
            },
            'next_bundle_uuid' => function ($value) {
                return !empty($value);
            },
        ];

        // Should have a new bundle for submission
        $isNewBundle = $extension->next_bundle_uuid !== $extension->current_bundle_uuid;
        if (!$isNewBundle) {
            return false;
        }

        // Check validations
        foreach ($validations as $field => $validationFunction) {
            if (isset($extension->$field)) {
                $value = $extension->$field;
                if (!$validationFunction($value)) {
                    return false;
                }
            } else {
                return false;
            }
        }

        return true;
    }
}
