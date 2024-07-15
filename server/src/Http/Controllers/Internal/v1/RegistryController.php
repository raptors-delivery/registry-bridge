<?php

namespace Fleetbase\RegistryBridge\Http\Controllers\Internal\v1;

use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Http\Resources\Category as CategoryResource;
use Fleetbase\Models\Category;
use Fleetbase\RegistryBridge\Models\RegistryExtension;

class RegistryController extends Controller
{
    public function categories()
    {
        $categories = Category::whereHas('registryExtensions')->where(['core_category' => 1, 'for' => 'extension_category'])->get();

        CategoryResource::wrap('categories');

        return CategoryResource::collection($categories);
    }

    public function loadInstalledEngines()
    {
        $installedExtensions = RegistryExtension::disableCache()->whereHas('installs', function ($query) {
            $query->where('company_uuid', session('company'));
        })->get()->mapWithKeys(function ($extension) {
            return [$extension->public_id => $extension->currentBundle->meta['package.json'] ?? []];
        });

        return response()->json($installedExtensions);
    }

    public function loadEngineManifest(string $extensionId)
    {
        $extension = RegistryExtension::where('public_id', $extensionId)->first();
        if (!$extension) {
            return response()->json([]);
        }

        if (isset($extension->currentBundle)) {
            $composerJson = $extension->currentBundle->meta['composer.json'];
            $packageJson  = $extension->currentBundle->meta['package.json'] ?? null;
            if ($composerJson) {
                $packageDistPath   = base_path('vendor/' . $composerJson['name'] . '/dist');
                $assetManifestPath = $packageDistPath . '/asset-manifest.json';
                $assetManifest     = json_decode(file_get_contents($assetManifestPath));

                // Get bundles
                $bundles = $assetManifest->bundles;

                // Create consumable assets map
                $output = [];
                foreach ($bundles as $manifest) {
                    if (isset($manifest->assets) && is_array($manifest->assets)) {
                        foreach ($manifest->assets as $asset) {
                            $name    = null;
                            $type    = $asset->type;
                            $content = file_get_contents($packageDistPath . $asset->uri);
                            $syntax  = $type === 'js' ? 'application/javascript' : 'text/css';
                            if ($type === 'js' && static::isES6Module($content)) {
                                $syntax = 'module';
                            }

                            // Check for config environments which should be injected as meta tags
                            if (static::containsConfigEnvironment($content)) {
                                // might need to make as additional $output[] element
                                $type    = 'meta';
                                $name    = static::extractModuleName($content);
                                $content = urlencode(static::extractConfigEnvironment($content));
                                $syntax  = null;
                            }

                            $output[] = [
                                'name'    => $name,
                                'type'    => $type,
                                'content' => $content,
                                'syntax'  => $syntax,
                            ];
                        }
                    }
                }

                return response()->json($output);
            }
        }

        return response()->json([]);
    }

    private static function isES6Module(string $contents)
    {
        // Check for module-specific keywords like import or export
        if (preg_match('/\bimport\s|\bexport\s/', $contents)) {
            return true;  // It should be a module
        }

        return false;  // It should not be a module
    }

    private static function containsConfigEnvironment(string $contents): bool
    {
        // This regex checks if the string starts with define and includes a module name ending with "/config/environment"
        return preg_match('/^define\("@[^"]+\/config\/environment",/', $contents);
    }

    private static function extractConfigEnvironment(string $contents)
    {
        // This regex extracts the JSON-like object from the define function's return statement
        if (preg_match('/define\("@[^"]+\/config\/environment",.*?\(function\(\)\{return\{default:(\{.*?\})\}\}\)\)/s', $contents, $matches)) {
            // Capture the object after "default:"
            $configString = $matches[1];
            // Correctly balance the curly braces to form a valid JSON object
            $configString = self::balanceCurlyBraces($configString);
            $configString = self::fixJsonFormat($configString);

            // Convert the balanced string to an array
            return $configString;
        }

        return null;
    }

    private static function fixJsonFormat($jsObject)
    {
        // Add quotes around keys where they may be missing
        $pattern        = '/(?<=[{,])(\s*)([a-zA-Z0-9_]+)(\s*):/m';
        $replacement    = '$1"$2"$3:';
        $jsonLikeString = preg_replace($pattern, $replacement, $jsObject);

        // Replace single quotes with double quotes around string values
        $pattern        = "/:(\s*)'([^']*)'/";
        $replacement    = ':$1"$2"';
        $jsonLikeString = preg_replace($pattern, $replacement, $jsonLikeString);

        // Ensure booleans and null are properly formatted
        $jsonLikeString = preg_replace('/:(\s*)(true|false|null)/i', ':$1$2', $jsonLikeString);

        // Convert the inner single quotes encapsulated by double quotes back to single quotes
        $pattern  = '/"(.*?)"/s';
        $callback = function ($matches) {
            return '"' . str_replace('"', "'", $matches[1]) . '"';
        };
        $jsonLikeString = preg_replace_callback($pattern, $callback, $jsonLikeString);

        return $jsonLikeString;
    }

    private static function balanceCurlyBraces($string)
    {
        // This function aims to balance curly braces if they are not properly closed in the extracted string
        $count  = 0;
        $length = strlen($string);
        for ($i = 0; $i < $length; $i++) {
            if ($string[$i] === '{') {
                $count++;
            } elseif ($string[$i] === '}') {
                $count--;
                if ($count === 0) {
                    // Cut the string up to the last matching closing brace
                    return substr($string, 0, $i + 1);
                }
            }
        }

        return $string; // Return as is if braces are balanced
    }

    private static function extractModuleName(string $contents)
    {
        // This regex matches the module name part of the define function call
        if (preg_match('/define\("([^"]+)"/', $contents, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
