<?php

namespace Fleetbase\RegistryBridge\Http\Controllers\Internal\v1;

use Fleetbase\Exceptions\FleetbaseRequestValidationException;
use Fleetbase\RegistryBridge\Http\Controllers\RegistryBridgeController;
use Fleetbase\RegistryBridge\Http\Requests\CreateRegistryExtensionRequest;
use Fleetbase\RegistryBridge\Http\Requests\RegistryExtensionActionRequest;
use Fleetbase\RegistryBridge\Models\RegistryExtension;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class RegistryExtensionController extends RegistryBridgeController
{
    /**
     * The resource to query.
     *
     * @var string
     */
    public $resource = 'registry_extension';

    /**
     * Creates a record with request payload.
     *
     * @return \Illuminate\Http\Response
     */
    public function createRecord(Request $request)
    {
        // Create validation request
        $createRegistryExtensionRequest  = CreateRegistryExtensionRequest::createFrom($request);
        $rules                           = $createRegistryExtensionRequest->rules();

        // Manually validate request
        $validator = Validator::make($request->input('registryExtension'), $rules);
        if ($validator->fails()) {
            return $createRegistryExtensionRequest->responseWithErrors($validator);
        }

        try {
            $record = $this->model->createRecordFromRequest($request);

            return ['registryExtension' => new $this->resource($record)];
        } catch (\Throwable $e) {
            return response()->error($e->getMessage());
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->error($e->getMessage());
        } catch (FleetbaseRequestValidationException $e) {
            return response()->error($e->getErrors());
        }
    }

    /**
     * Approves a specific extension by its ID.
     *
     * This function locates a `RegistryExtension` using the provided ID and sets its status to 'approved'.
     * If the extension is successfully found and updated, it returns the extension resource. If the extension
     * cannot be found, it returns an error response indicating the inability to locate the extension.
     *
     * @param RegistryExtensionActionRequest $request the validated request object
     *
     * @return \Illuminate\Http\Response|array returns an array containing the extension resource if successful,
     *                                         or an error response if the extension cannot be found
     */
    public function approve(RegistryExtensionActionRequest $request)
    {
        $id        = $request->input('id');
        $extension = RegistryExtension::find($id);
        if ($extension) {
            $extension->update(['status' => 'approved']);
        } else {
            return response()->error('Unable to find extension for approval.');
        }

        return ['registryExtension' => new $this->resource($extension)];
    }

    /**
     * Rejects a specific extension by its ID.
     *
     * Locates a `RegistryExtension` using the provided ID and updates its status to 'rejected'. It also
     * intends to send a rejection reason via email to the extension's author (as indicated by commented code).
     * If the extension is found and updated, it returns the extension resource. If not found, it returns an
     * error response indicating the extension could not be located.
     *
     * Note: This method assumes the rejection reason is handled separately (possibly by another request).
     *
     * @param RegistryExtensionActionRequest $request the validated request object
     *
     * @return \Illuminate\Http\Response|array returns an array containing the extension resource if successful,
     *                                         or an error response if the extension cannot be found
     */
    public function reject(RegistryExtensionActionRequest $request)
    {
        $id        = $request->input('id');
        $extension = RegistryExtension::find($id);
        if ($extension) {
            $extension->update(['status' => 'rejected']);
        } else {
            return response()->error('Unable to find extension for rejection.');
        }

        // send rejection reason via email to extension author
        // $reason = $request->input('reason');

        return ['registryExtension' => new $this->resource($extension)];
    }

    /**
     * Submits an extension for review based on its ID.
     *
     * This method attempts to submit a `RegistryExtension` for review. It first checks
     * if the extension is ready for submission by calling the static method
     * `isExtensionReadyForSubmission`. If the extension is not ready, it returns an error response.
     * If the extension is ready, it updates the extension's status to 'awaiting_review' and returns
     * a JSON response indicating success.
     *
     * @param string $id the unique identifier of the extension to be submitted
     *
     * @return \Illuminate\Http\JsonResponse returns a JSON response indicating the outcome of the operation
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException if no extension with the given ID is found
     */
    public function submit(string $id)
    {
        $isReady = RegistryExtension::isExtensionReadyForSubmission($id);
        if (!$isReady) {
            return response()->error('Unable to submit extension for review.');
        }

        $extension = RegistryExtension::find($id);
        if ($extension) {
            $extension->update(['status' => 'awaiting_review']);
        }

        return ['registryExtension' => new $this->resource($extension)];
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
    public function downloadBundle(RegistryExtensionActionRequest $request)
    {
        $id        = $request->input('id');
        $extension = RegistryExtension::find($id);
        if ($extension && $extension->currentBundle) {
            $bundleFile = data_get($extension, 'currentBundle.bundle');
            if ($bundleFile) {
                return Storage::disk($bundleFile->disk)->download($bundleFile->path, $bundleFile->name);
            }
        }

        return response()->error('Failed to download extension bundle');
    }
}
