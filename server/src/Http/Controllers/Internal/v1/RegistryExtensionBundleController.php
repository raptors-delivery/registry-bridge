<?php

namespace Fleetbase\RegistryBridge\Http\Controllers\Internal\v1;

use Fleetbase\Exceptions\FleetbaseRequestValidationException;
use Fleetbase\Models\File;
use Fleetbase\RegistryBridge\Http\Controllers\RegistryBridgeController;
use Fleetbase\RegistryBridge\Http\Requests\CreateRegistryExtensionBundleRequest;
use Fleetbase\RegistryBridge\Http\Requests\RegistryExtensionActionRequest;
use Fleetbase\RegistryBridge\Models\RegistryExtension;
use Fleetbase\RegistryBridge\Models\RegistryExtensionBundle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class RegistryExtensionBundleController extends RegistryBridgeController
{
    /**
     * The resource to query.
     *
     * @var string
     */
    public $resource = 'registry_extension_bundle';

    /**
     * Creates a record with request payload.
     *
     * @return \Illuminate\Http\Response
     */
    public function createRecord(Request $request)
    {
        // Create validation request
        $createRegistryExtensionBundleRequest  = CreateRegistryExtensionBundleRequest::createFrom($request);
        $rules                                 = $createRegistryExtensionBundleRequest->rules();

        // Manually validate request
        $validator = Validator::make($request->input('registryExtensionBundle'), $rules);
        if ($validator->fails()) {
            return $createRegistryExtensionBundleRequest->responseWithErrors($validator);
        }

        // Extract bundle extension json for file validation
        $bundleFile = File::where('uuid', $request->input('registryExtensionBundle.bundle_uuid'))->first();
        if (!$bundleFile) {
            return response()->error('Unable to find bundle file for validation.');
        }

        // Get extension.json contents
        $extensionJson = RegistryExtensionBundle::extractBundleFile($bundleFile);
        if (!$extensionJson) {
            return response()->error('Unable to find `extension.json` file required in bundle.');
        }

        // Check if bundle number is set
        if (!isset($extensionJson->bundle_number)) {
            return response()->error('No `bundle_number` set in the `extension.json`');
        }

        // Check if version is set
        if (!isset($extensionJson->bundle_number)) {
            return response()->error('No `version` set in the `extension.json`');
        }

        // Check if either api or engine property is set
        if (!isset($extensionJson->engine) || !isset($extensionJson->api)) {
            return response()->error('No `api` or `engine` property set in the `extension.json`');
        }

        // Make sure bundle number and version doesn't exist already
        $isNotUniqueBundle = RegistryExtensionBundle::where(['bundle_number' => $extensionJson->bundle_number, 'version' => $extensionJson->version])->exists();
        if ($isNotUniqueBundle) {
            return response()->error('Bundle with number `' . $extensionJson->bundle_number . '` and version `' . $extensionJson->version . '` already exists.');
        }

        // Make sure the extension ID is set
        $hasExtensionIdSet = isset($extensionJson->id) && RegistryExtension::where('public_id', $extensionJson->id)->exists();
        if (!$hasExtensionIdSet) {
            return response()->json('Invalid extension ID set in `extension.json`, the ID must belong to the submission and be set.');
        }

        try {
            $record = $this->model->createRecordFromRequest($request);

            // Update the record version from extension json
            $record->update(['bundle_number' => $extensionJson->bundle_number, 'version' => $extensionJson->version]);

            // Update the extension with required props
            $record->extension()->update([
                'package_name'  => $extensionJson->engine,
                'composer_name' => $extensionJson->api,
            ]);

            return ['registryExtensionBundle' => new $this->resource($record)];
        } catch (\Throwable $e) {
            return response()->error($e->getMessage());
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->error($e->getMessage());
        } catch (FleetbaseRequestValidationException $e) {
            return response()->error($e->getErrors());
        }
    }

    /**
     * Handles the download request for an extension bundle.
     *
     * This function retrieves a specific `RegistryExtension` by its ID and attempts to download
     * its latest bundle. If the extension exists and has an associated bundle, it returns a download response
     * with the appropriate file. If the extension doesn't exist or doesn't have a bundle, it returns an error response.
     *
     * @param RegistryExtensionActionRequest $request the validated request object
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\Response
     *                                                                                        Returns a download response for the bundle if successful, or an error response if not
     */
    public function download(RegistryExtensionActionRequest $request)
    {
        $id              = $request->input('id');
        $extensionBundle = RegistryExtensionBundle::find($id);
        if ($extensionBundle && $extensionBundle->bundle) {
            return Storage::disk($extensionBundle->bundle->disk)->download($extensionBundle->bundle->path, $extensionBundle->bundle->name);
        }

        return response()->error('Failed to download extension bundle');
    }
}
