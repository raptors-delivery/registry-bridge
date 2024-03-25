<?php

namespace Fleetbase\RegistryBridge\Models;

use Fleetbase\Casts\Json;
use Fleetbase\Models\Company;
use Fleetbase\Models\File;
use Fleetbase\Models\Model;
use Fleetbase\Models\User;
use Fleetbase\Support\Utils;
use Fleetbase\Traits\HasApiModelBehavior;
use Fleetbase\Traits\HasMetaAttributes;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\Searchable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;

class RegistryExtensionBundle extends Model
{
    use HasUuid;
    use HasPublicId;
    use HasMetaAttributes;
    use HasApiModelBehavior;
    use Searchable;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'registry_extension_bundles';

    /**
     * The type of public Id to generate.
     *
     * @var string
     */
    protected $publicIdType = 'bundle';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'company_uuid',
        'created_by_uuid',
        'extension_uuid',
        'bundle_uuid',
        'bundle_id',
        'bundle_number',
        'version',
        'meta',
        'status',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'meta'                  => Json::class,
    ];

    /**
     * Dynamic attributes that are appended to object.
     *
     * @var array
     */
    protected $appends = [
        'bundle_filename',
    ];

    /**
     * Relations that should be loaded with model.
     *
     * @var array
     */
    protected $with = [];

    /**
     * Relations that should not be loaded.
     *
     * @var array
     */
    protected $without = ['company', 'createdBy', 'extension'];

    /**
     * Searchable columns.
     *
     * @var array
     */
    protected $searchableColumns = ['name'];

    /**
     * The "booting" method of the model.
     *
     * This method is called on the model boot and sets up
     * event listeners, such as creating a unique bundle ID
     * when a new model instance is being created.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->bundle_id = self::generateUniqueBundleId();
        });
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
    public function extension()
    {
        return $this->belongsTo(RegistryExtension::class, 'extension_uuid', 'uuid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function bundle()
    {
        return $this->belongsTo(File::class, 'bundle_uuid', 'uuid');
    }

    /**
     * Get the bundle original filename.
     *
     * @return string
     */
    public function getBundleFilenameAttribute()
    {
        if ($this->bundle instanceof File) {
            return $this->bundle->original_filename;
        }

        return data_get($this, 'bundle.original_filename');
    }

    /**
     * Generates a unique bundle ID.
     *
     * This static method constructs a unique bundle identifier (ID) by appending a random string to a fixed prefix.
     * The prefix used is 'EXTBNDL', and the total length of the generated ID is 14 characters, including the prefix.
     * The function ensures uniqueness by checking the generated ID against existing IDs in the database and
     * regenerating if a duplicate is found. The characters used for the random string are upper and lower case
     * alphabets and numerals. The final bundle ID is returned in upper case.
     *
     * @return string a unique, uppercase bundle ID
     */
    public static function generateUniqueBundleId()
    {
        do {
            $prefix          = 'EXTBNDL';
            $remainingLength = 14 - strlen($prefix);
            $characters      = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            $result          = '';

            for ($i = 0; $i < $remainingLength; $i++) {
                $result .= $characters[rand(0, strlen($characters) - 1)];
            }

            $bundleId = strtoupper($prefix . $result);
        } while (self::where('bundle_id', $bundleId)->exists());

        return $bundleId;
    }

    /**
     * Extracts a specified file from a zipped bundle file.
     *
     * This method downloads the file indicated by the File model,
     * unzips it, and looks for a specified filename within the unzipped contents.
     * If the file is found, its contents are returned as a stdClass object.
     * Temporary files are cleaned up after extraction.
     *
     * @param File   $bundle   the File model instance representing the zipped bundle
     * @param string $filename The name of the file to extract from the bundle (default: 'extension.json').
     *
     * @return \stdClass|null the decoded JSON object from the specified file, or null if not found
     */
    protected static function extractBundleFile(File $bundle, string $filename = 'extension.json', $options = []): ?\stdClass
    {
        $shouldParseJson = data_get($options, 'parse_json', true);
        $tempDir         = sys_get_temp_dir() . '/' . str_replace(['.', ','], '_', uniqid('fleetbase_zip_', true));
        mkdir($tempDir);

        // Download the file to a local temporary directory
        $tempFilePath = $tempDir . '/' . basename($bundle->path);
        $contents     = Storage::disk($bundle->disk)->get($bundle->path);
        file_put_contents($tempFilePath, $contents);

        // Unzip the file
        $extractedFilePath = static::_extractAndFindFile($tempFilePath, $tempDir, $filename);
        if (file_exists($extractedFilePath)) {
            $fileContents = file_get_contents($extractedFilePath);

            // Cleanup: Delete the temporary directory
            // Make sure to handle this part carefully to avoid any unintended deletion
            array_map('unlink', glob("$tempDir/*.*"));
            Utils::deleteDirectory($tempDir);

            if ($shouldParseJson) {
                return json_decode($fileContents);
            }

            return $fileContents;
        }

        // Cleanup if 'extension.json' is not found
        array_map('unlink', glob("$tempDir/*.*"));
        Utils::deleteDirectory($tempDir);

        return null;
    }

    private static function _extractAndFindFile($zipFilePath, $tempDir, $targetFile = 'extension.json')
    {
        $zip = new \ZipArchive();

        if ($zip->open($zipFilePath) === true) {
            $zip->extractTo($tempDir);
            $zip->close();

            // Direct check in the tempDir
            $directPath = $tempDir . '/' . $targetFile;
            if (file_exists($directPath)) {
                return $directPath;
            }

            // Check in the first subdirectory
            $files = scandir($tempDir);
            foreach ($files as $file) {
                $invalidDirectories = ['__MACOSX', '.', '..', 'DS_Store', '.DS_Store', '.idea', '.vscode'];
                if (!Str::startsWith($file, ['.', '_']) && !in_array($file, $invalidDirectories) && is_dir($tempDir . '/' . $file)) {
                    $nestedPath = $tempDir . '/' . $file . '/' . $targetFile;
                    if (file_exists($nestedPath)) {
                        return $nestedPath;
                    }
                    break;
                }
            }
        }

        return null;
    }

    /**
     * Extracts 'extension.json' from the bundle file.
     *
     * This method is a specific implementation of extractBundleFile for extracting
     * 'extension.json'. It checks if the 'bundle' property is an instance of File
     * and invokes the extraction process.
     *
     * @return \stdClass|null The decoded JSON object from 'extension.json', or null if not found.
     */
    public function extractExtensionJson(): ?\stdClass
    {
        if ($this->bundle instanceof File) {
            return static::extractBundleFile($this->bundle);
        }
    }

    /**
     * Extracts 'composer.json' from the bundle file.
     *
     * This method is a specific implementation of extractBundleFile for extracting
     * 'composer.json'. It checks if the 'bundle' property is an instance of File
     * and invokes the extraction process.
     *
     * @return \stdClass|null The decoded JSON object from 'composer.json', or null if not found.
     */
    public function extractComposerJson(): ?\stdClass
    {
        if ($this->bundle instanceof File) {
            return static::extractBundleFile($this->bundle, 'composer.json');
        }
    }

    /**
     * Extracts 'package.json' from the bundle file.
     *
     * This method is a specific implementation of extractBundleFile for extracting
     * 'package.json'. It checks if the 'bundle' property is an instance of File
     * and invokes the extraction process.
     *
     * @return \stdClass|null The decoded JSON object from 'package.json', or null if not found.
     */
    public function extractPackageJson(): ?\stdClass
    {
        if ($this->bundle instanceof File) {
            return static::extractBundleFile($this->bundle, 'package.json');
        }
    }
}
