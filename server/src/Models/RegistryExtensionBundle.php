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
     * Extracts specified file(s) from a zipped bundle and returns their contents.
     *
     * This method downloads a file indicated by the File model, unzips it, and looks for
     * specified filename(s) within the unzipped contents. The contents of each found file
     * are returned as a stdClass object, with each property corresponding to a filename.
     * If 'parse_json' option is true (default), file contents are decoded as JSON.
     * Temporary files are cleaned up after extraction.
     *
     * @param File         $bundle    the File model instance representing the zipped bundle
     * @param string|array $filenames a single filename or an array of filenames to extract from the bundle
     * @param array        $options   Additional options, e.g., ['parse_json' => false] to get raw file contents.
     *
     * @return \stdClass|null an object containing the contents of each extracted file, or null if files are not found
     */
    protected static function extractBundleFile(File $bundle, $filenames = 'extension.json', $options = []): ?\stdClass
    {
        $shouldParseJson = data_get($options, 'parse_json', true);
        $tempDir         = sys_get_temp_dir() . '/' . str_replace(['.', ','], '_', uniqid('fleetbase_zip_', true));
        mkdir($tempDir);

        // Download the file to a local temporary directory
        $tempFilePath = $tempDir . '/' . basename($bundle->path);
        $contents     = Storage::disk($bundle->disk)->get($bundle->path);
        file_put_contents($tempFilePath, $contents);

        // Extract file paths
        $extractedFilePaths = static::_extractAndFindFile($tempFilePath, $tempDir, $filenames);

        $result = new \stdClass();
        foreach ($extractedFilePaths as $filename => $path) {
            if (file_exists($path)) {
                $fileContents = file_get_contents($path);
                if ($shouldParseJson) {
                    $result->$filename = json_decode($fileContents);
                } else {
                    $result->$filename = $fileContents;
                }
            }
        }

        // Cleanup: Delete the temporary directory
        array_map('unlink', glob("$tempDir/*.*"));
        Utils::deleteDirectory($tempDir);

        return $result;
    }

    /**
     * Extracts and finds the path(s) of specified file(s) within a zipped archive.
     *
     * Opens the specified ZIP archive and extracts it to a temporary directory. It then
     * searches for the given file(s) in both the root and the first valid subdirectory of
     * the unzipped content. It returns an associative array of file paths, indexed by filenames.
     * Invalid directories such as '__MACOSX', '.', '..', etc., are excluded from the search.
     *
     * @param string       $zipFilePath path to the ZIP archive file
     * @param string       $tempDir     temporary directory where the ZIP file is extracted
     * @param string|array $targetFiles a single filename or an array of filenames to search for within the archive
     *
     * @return array associative array of paths for the requested files within the archive
     */
    private static function _extractAndFindFile($zipFilePath, $tempDir, $targetFiles)
    {
        $paths = [];
        $zip   = new \ZipArchive();

        if ($zip->open($zipFilePath) === true) {
            $zip->extractTo($tempDir);
            $zip->close();

            foreach ((array) $targetFiles as $targetFile) {
                // Direct check in the tempDir
                $directPath = $tempDir . '/' . $targetFile;
                if (file_exists($directPath)) {
                    $paths[$targetFile] = $directPath;
                    continue;
                }

                // Check in the first subdirectory
                $files = scandir($tempDir);
                foreach ($files as $file) {
                    $invalidDirectories = ['__MACOSX', '.', '..', 'DS_Store', '.DS_Store', '.idea', '.vscode'];
                    if (!Str::startsWith($file, ['.', '_']) && !in_array($file, $invalidDirectories) && is_dir($tempDir . '/' . $file)) {
                        $nestedPath = $tempDir . '/' . $file . '/' . $targetFile;
                        if (file_exists($nestedPath)) {
                            $paths[$targetFile] = $nestedPath;
                            break;
                        }
                    }
                }
            }
        }

        return $paths;
    }

    /**
     * Extracts multiple configuration files from the bundle.
     *
     * This method leverages the extractBundleFile function to extract an array
     * of specified configuration files ('extension.json', 'composer.json', 'package.json')
     * from the bundle. It returns an object containing the contents of each file,
     * where each property name corresponds to a filename. If a specified file is not found
     * in the bundle, its corresponding property will be absent in the returned object.
     * The method is useful for retrieving multiple configuration files in a single operation.
     *
     * @return \stdClass|null An object containing the contents of each extracted file.
     *                        Properties of the object correspond to the filenames.
     *                        Returns null if the bundle property is not an instance of File.
     */
    public static function extractBundleData(File $bundleFile): ?\stdClass
    {
        return static::extractBundleFile($bundleFile, ['extension.json', 'composer.json', 'package.json']);
    }

    /**
     * Extracts multiple configuration files from the bundle.
     *
     * This method leverages the extractBundleFile function to extract an array
     * of specified configuration files ('extension.json', 'composer.json', 'package.json')
     * from the bundle. It returns an object containing the contents of each file,
     * where each property name corresponds to a filename. If a specified file is not found
     * in the bundle, its corresponding property will be absent in the returned object.
     * The method is useful for retrieving multiple configuration files in a single operation.
     *
     * @return \stdClass|null An object containing the contents of each extracted file.
     *                        Properties of the object correspond to the filenames.
     *                        Returns null if the bundle property is not an instance of File.
     */
    public function extractExtensionData()
    {
        if ($this->bundle instanceof File) {
            return static::extractBundleData($this->bundle);
        }
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
        $filename = 'extension.json';
        if ($this->bundle instanceof File) {
            $data = static::extractBundleFile($this->bundle);

            return Utils::getObjectKeyValue($data, $filename);
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
        $filename = 'composer.json';
        if ($this->bundle instanceof File) {
            $data = static::extractBundleFile($this->bundle, $filename);

            return Utils::getObjectKeyValue($data, $filename);
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
        $filename = 'package.json';
        if ($this->bundle instanceof File) {
            $data = static::extractBundleFile($this->bundle, $filename);

            return Utils::getObjectKeyValue($data, $filename);
        }
    }
}
