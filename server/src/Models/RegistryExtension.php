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
        'latest_bundle_uuid',
        'icon_uuid',
        'public_id',
        'name',
        'subtitle',
        'package_name',
        'composer_name',
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
        'latest_bundle_filename',
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
        'latest_bundle',
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
    public function latestBundle()
    {
        return $this->belongsTo(File::class, 'latest_bundle_uuid', 'uuid');
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
     * Get the latest bundle original filename.
     *
     * @return string
     */
    public function getLatestBundleFilenameAttribute()
    {
        if ($this->latestBundle instanceof File) {
            return $this->latestBundle->original_filename;
        }

        return data_get($this, 'latestBundle.original_filename');
    }

    /**
     * Retrieves a RegistryExtension instance by package name.
     *
     * This static method searches for a `RegistryExtension` using a given package name. It checks both the
     * 'package_name' and 'composer_name' fields in the database for a match with the provided package name.
     * If a matching record is found, it returns the corresponding `RegistryExtension` instance. Otherwise,
     * it returns null.
     *
     * @param string $packageName the package name used to search for the extension
     *
     * @return RegistryExtension|null returns the `RegistryExtension` instance if found, or null if no match is found
     */
    public static function findByPackageName(string $packageName): ?RegistryExtension
    {
        return static::where('package_name', $packageName)->orWhere('composer_name', $packageName)->first();
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
            'latest_bundle_uuid' => function ($value) {
                return !empty($value);
            },
        ];

        // Should have either composer_name or package_name
        $hasPackageName = !empty($extension->composer_name) || !empty($extension->package_name);
        if (!$hasPackageName) {
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
