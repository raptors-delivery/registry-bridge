<?php

namespace Fleetbase\RegistryBridge\Models;

use Fleetbase\Casts\Json;
use Fleetbase\Models\Company;
use Fleetbase\Models\File;
use Fleetbase\Models\Model;
use Fleetbase\Models\User;
use Fleetbase\RegistryBridge\Exceptions\InstallFailedException;
use Fleetbase\Support\SocketCluster\SocketClusterService;
use Fleetbase\Support\Utils;
use Fleetbase\Traits\HasApiModelBehavior;
use Fleetbase\Traits\HasMetaAttributes;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\Searchable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;
use Symfony\Component\Process\Process;

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
        $result             = new \stdClass();
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
        try {
            array_map('unlink', glob("$tempDir/*.*"));
        } catch (\Throwable $e) {
            // Probably a directory ...
        }
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

            return $data[$filename];
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

            return $data[$filename];
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

            return $data[$filename];
        }
    }

    /**
     * Installs a Composer package based on the provided metadata.
     *
     * @throws CustomComposerException if the Composer process fails or encounters an issue
     */
    public function installComposerPackage(): void
    {
        if (!is_array($this->meta) || !isset($this->meta['composer.json'])) {
            return;
        }

        $composerJson = $this->meta['composer.json'];
        if (!$composerJson) {
            return;
        }

        // Prepare for install
        $output          = '';
        $installChannel  = implode('.', ['install', $this->company_uuid, $this->extension_uuid]);
        $packageName     = data_get($composerJson, 'name');
        $version         = data_get($composerJson, 'version');
        $composerCommand = [
            'composer',
            'require',
            $packageName . ($version === 'latest' ? '' : ':' . $version),
        ];

        // Create process
        $process = new Process($composerCommand);
        $process->setWorkingDirectory('/fleetbase/api');
        $process->setTimeout(3600 * 2);

        // Run process
        $process->run(function ($type, $buffer) use (&$output, $installChannel) {
            $output .= $buffer;
            $lines = explode("\n", $buffer);
            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $progress = static::composerInstallOutputProgress($line);
                if ($progress > 0) {
                    SocketClusterService::publish($installChannel, [
                        'process'  => 'install',
                        'progress' => $progress,
                    ]);
                }
            }
        });

        if (!$process->isSuccessful()) {
            $friendlyMessage = static::composerOutputFriendly($output);
            throw new InstallFailedException($friendlyMessage);
        }
    }

    public function uninstallComposerPackage(): void
    {
        if (!is_array($this->meta) || !isset($this->meta['composer.json'])) {
            return;
        }

        $composerJson = $this->meta['composer.json'];
        if (!$composerJson) {
            return;
        }

        // Prepare for uninstall
        $output           = '';
        $uninstallChannel = implode('.', ['uninstall', $this->company_uuid, $this->extension_uuid]);
        $packageName      = data_get($composerJson, 'name');
        $composerCommand  = [
            'composer',
            'remove',
            $packageName,
        ];

        // Create process
        $process = new Process($composerCommand);
        $process->setWorkingDirectory('/fleetbase/api');
        $process->setTimeout(3600 * 2);

        // Run process
        $process->run(function ($type, $buffer) use (&$output, $uninstallChannel) {
            $output .= $buffer;
            $lines = explode("\n", $buffer);
            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $progress = static::composerUninstallOutputProgress($line);
                if ($progress > 0) {
                    SocketClusterService::publish($uninstallChannel, [
                        'process'  => 'uninstall',
                        'progress' => $progress,
                    ]);
                }
            }
        });

        if (!$process->isSuccessful()) {
            $friendlyMessage = static::composerOutputFriendly($output);
            throw new InstallFailedException($friendlyMessage);
        }
    }

    /**
     * Parses Composer output to provide a user-friendly message.
     *
     * This method checks the Composer output for specific keywords or phrases
     * and returns a simplified, more understandable message suitable for end-users.
     *
     * @param string $output the raw output from Composer
     *
     * @return string a user-friendly interpretation of the output
     */
    public static function composerOutputFriendly($output): string
    {
        // Check for successful install/update
        if (strpos($output, 'Generating optimized autoload files') !== false) {
            return 'Installation successful. Packages have been updated.';
        }

        // Check for 'Nothing to install, update or remove'
        if (strpos($output, 'Nothing to install, update or remove') !== false) {
            return 'No changes made. Everything is already up-to-date.';
        }

        // Check for 'Package is abandoned'
        if (preg_match_all('/Package (.+) is abandoned/', $output, $matches)) {
            $abandonedPackages = implode(', ', $matches[1]);

            return "Warning: The following packages are abandoned and should be replaced: $abandonedPackages.";
        }

        // Check for dependency issues
        if (preg_match('/Problem (\d+)/', $output, $matches)) {
            return 'Unable to install due to dependency compatibility issues.';
        }

        // If no known patterns are found, return a generic message or the original output
        return 'An error occurred during installation. Please check the log for details.';
    }

    /**
     * Estimates the progress of a Composer installation process.
     *
     * This method interprets the Composer output to estimate the progress of the installation.
     * It assigns a progress percentage based on identified keywords or phrases in the output.
     *
     * @param string $output the raw output from Composer
     *
     * @return int an estimated progress percentage
     */
    public static function composerInstallOutputProgress($output): int
    {
        // Trim the output to remove unnecessary whitespace
        $output = trim($output);

        // Initial phase, updating composer.json and resolving dependencies
        if (strpos($output, 'Running composer update') !== false) {
            return 10;
        }
        // Dependencies are being updated
        elseif (strpos($output, 'Loading composer repositories with package information') !== false) {
            return 20;
        }
        // Dependencies updating step
        elseif (strpos($output, 'Updating dependencies') !== false) {
            return 30;
        }
        // Lock file is being written
        elseif (strpos($output, 'Lock file operations') !== false) {
            return 40;
        }
        // Lock file writing in progress
        elseif (strpos($output, 'Writing lock file') !== false) {
            return 50;
        }
        // Installing dependencies
        elseif (strpos($output, 'Installing dependencies from lock file') !== false) {
            return 60;
        }
        // Package operations, installation begins
        elseif (strpos($output, 'Package operations:') !== false) {
            return 70;
        }
        // Downloading a specific package
        elseif (strpos($output, '- Downloading') !== false) {
            return 75;
        }
        // Extracting archive for a package
        elseif (strpos($output, '- Installing') !== false) {
            return 80;
        }
        // Autoload files are generated, nearing completion
        elseif (strpos($output, 'Generating optimized autoload files') !== false) {
            return 90;
        }
        // Final steps, package discovery and publishing assets
        elseif (strpos($output, 'postAutoloadDump') !== false
                || strpos($output, '@php artisan package:discover --ansi') !== false
                || strpos($output, '@php artisan vendor:publish') !== false) {
            return 95;
        }
        // Completion messages
        elseif (strpos($output, 'No security vulnerability advisories found') !== false) {
            return 100;
        }

        // Default progress if no known phrases are matched
        return 0;
    }

    /**
     * Parses the output of the composer uninstall process to determine progress.
     *
     * This function analyzes the output of the `composer remove` command and returns a progress percentage
     * based on the stage of the process. It helps in providing real-time progress updates during the
     * uninstallation of a Composer package.
     *
     * @param string $output the output from the composer uninstall process
     *
     * @return int the progress percentage
     */
    public static function composerUninstallOutputProgress($output): int
    {
        // Trim the output to remove unnecessary whitespace
        $output = trim($output);

        // Initial phase, updating composer.json and resolving dependencies
        if (strpos($output, 'Running composer update') !== false) {
            return 10;
        }
        // Loading repositories
        elseif (strpos($output, 'Loading composer repositories with package information') !== false) {
            return 20;
        }
        // Dependencies are being updated
        elseif (strpos($output, 'Updating dependencies') !== false) {
            return 30;
        }
        // Lock file operations are defined
        elseif (strpos($output, 'Lock file operations') !== false) {
            return 40;
        }
        // Lock file writing in progress
        elseif (strpos($output, 'Writing lock file') !== false) {
            return 50;
        }
        // Installing dependencies from lock file
        elseif (strpos($output, 'Installing dependencies from lock file') !== false) {
            return 60;
        }
        // Package operations, removal begins
        elseif (strpos($output, 'Package operations:') !== false) {
            return 70;
        }
        // Removing a specific package
        elseif (strpos($output, '- Removing') !== false) {
            return 80;
        }
        // Generating autoload files, nearing completion
        elseif (strpos($output, 'Generating optimized autoload files') !== false) {
            return 90;
        }
        // Final steps, package discovery and publishing assets
        elseif (strpos($output, 'postAutoloadDump') !== false
                || strpos($output, '@php artisan package:discover --ansi') !== false
                || strpos($output, '@php artisan vendor:publish') !== false) {
            return 95;
        }
        // Completion messages
        elseif (strpos($output, 'No security vulnerability advisories found') !== false) {
            return 100;
        }

        // Default progress if no known phrases are matched
        return 0;
    }

    public function installEnginePackage(): void
    {
        if (!is_array($this->meta) || !isset($this->meta['package.json'])) {
            return;
        }

        $packageJson = $this->meta['package.json'];
        if (!$packageJson) {
            return;
        }

        $packageName     = data_get($packageJson, 'name');
        $version         = data_get($packageJson, 'version');
        $installCommand  = [
            'pnpm',
            'add',
            $packageName . ($version === 'latest' ? '' : ':' . $version),
        ];

        $process = new Process($installCommand);
        $process->setWorkingDirectory('/fleetbase/console');
        $process->setTimeout(3600 * 2);
        $process->mustRun();

        $installChannel = implode('.', ['install', $this->company_uuid, $this->extension_uuid]);
        $output         = '';

        foreach ($process as $type => $data) {
            if ($process::OUT === $type) {
                $output .= $data;
                // $progress = static::composerOutputProgress($data);
                SocketClusterService::publish($installChannel, [
                    'process'  => 'install',
                    'progress' => 0,
                ]);
            } else {
                // If there is error output, it could be appended to $output for complete error message
                $output .= $data;
            }
        }

        dd($output);

        if (!$process->isSuccessful()) {
            throw new \Exception('Engine install failed!');
            // $friendlyMessage = static::composerOutputFriendly($output);
            // throw new InstallFailedException($friendlyMessage);
        }
    }
}
