<?php

namespace Fleetbase\RegistryBridge\Http\Controllers\Internal\v1;

use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Http\Resources\Category as CategoryResource;
use Fleetbase\Models\Category;

class RegistryController extends Controller
{
    public function categories()
    {
        $categories = Category::whereHas('registryExtensions')->where(['core_category' => 1, 'for' => 'extension_category'])->get();

        CategoryResource::wrap('categories');

        return CategoryResource::collection($categories);
    }
}
