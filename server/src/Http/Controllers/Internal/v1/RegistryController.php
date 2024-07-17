<?php

namespace Fleetbase\RegistryBridge\Http\Controllers\Internal\v1;

use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Http\Resources\Category as CategoryResource;
use Fleetbase\Models\Category;
use Fleetbase\RegistryBridge\Models\RegistryExtension;

class RegistryController extends Controller
{
    /**
     * Retrieve a collection of categories that have registry extensions.
     *
     * This method fetches categories that have associated registry extensions
     * and meet the specified criteria (core_category equals 1 and 'for' equals
     * 'extension_category'). The retrieved categories are then wrapped using
     * the CategoryResource and returned as a collection of CategoryResource.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     *                                                                     A collection of CategoryResource objects representing the categories
     */
    public function categories()
    {
        $categories = Category::whereHas('registryExtensions')->where(['core_category' => 1, 'for' => 'extension_category'])->get();

        CategoryResource::wrap('categories');

        return CategoryResource::collection($categories);
    }

    /**
     * Retrieve a list of installed engines for the current company session.
     *
     * This method fetches registry extensions that have been installed by the
     * company identified in the current session. It disables caching for the
     * query, filters the results based on the company's UUID stored in the session,
     * and maps the retrieved extensions to extract the 'package.json' metadata.
     * The result is returned as a JSON response.
     *
     * @return \Illuminate\Http\JsonResponse
     *                                       A JSON response containing a list of installed engines with their metadata
     */
    public function getInstalledEngines()
    {
        $installedExtensions = RegistryExtension::disableCache()->whereHas('installs', function ($query) {
            $query->where('company_uuid', session('company'));
        })->get()->map(function ($extension) {
            return $extension->currentBundle->meta['package.json'] ?? [];
        });

        return response()->json($installedExtensions);
    }
}
