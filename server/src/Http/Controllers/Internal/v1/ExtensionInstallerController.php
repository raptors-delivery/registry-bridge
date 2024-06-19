<?php

namespace Fleetbase\RegistryBridge\Http\Controllers\Internal\v1;

use Fleetbase\Http\Controllers\Controller;
use Fleetbase\RegistryBridge\Http\Requests\InstallExtensionRequest;
use Fleetbase\RegistryBridge\Models\RegistryExtension;
use Fleetbase\RegistryBridge\Models\RegistryExtensionInstall;

class ExtensionInstallerController extends Controller
{
    /**
     * Installs a specified extension for a company.
     *
     * This method handles the installation process of an extension. It first looks up
     * the extension in the `RegistryExtension` model using its public identifier provided
     * in the request. Then, it ensures that the installation is recorded in the
     * `RegistryExtensionInstall` model, linking the extension to the current company.
     * If the installation record already exists, it does not create a duplicate.
     *
     * @param InstallExtensionRequest $request The request object containing the extension
     *                                         identifier. Expected to have an 'extension'
     *                                         field with the public ID of the extension.
     *
     * @return \Illuminate\Http\JsonResponse Returns a JSON response indicating the status.
     *                                       The response contains a 'status' key with the
     *                                       value 'ok' if the process is successful.
     *
     * @throws \Exception throws an exception if the specified extension is not found in the
     *                    `RegistryExtension` model, leading to a potential error when
     *                    trying to access properties of a non-object (`$extension->uuid`)
     */
    public function install(InstallExtensionRequest $request)
    {
        set_time_limit(120);
        $extension = RegistryExtension::where('public_id', $request->input('extension'))->first();

        // Check if already installed
        $installed = RegistryExtensionInstall::disableCache()->where(['company_uuid' => session('company'), 'extension_uuid' => $extension->uuid])->exists();
        if ($installed) {
            return response()->error('This extension is already installed.');
        }

        // Install for Composer
        try {
            $extension->currentBundle->installComposerPackage();
        } catch (\Throwable $e) {
            return response()->error($e->getMessage());
        }

        // // Install for Console
        // // If OSS version it should send signal to console api
        // // Which will install using pnpm, then rebuild the application
        // // If build fails it will uninstall and report error
        // try {
        //     $extension->currentBundle->installEnginePackage();
        // } catch (\Throwable $e) {
        //     return response()->error($e->getMessage());
        // }

        // Create install record
        $install = RegistryExtensionInstall::create([
            'company_uuid'   => session('company'),
            'extension_uuid' => $extension->uuid,
        ]);

        return response()->json(['status' => 'ok', 'install' => $install]);
    }

    /**
     * Uninstall a specific extension for the current company.
     *
     * This function handles the uninstallation of an extension specified by the public ID
     * provided in the request. It first checks if the extension is already uninstalled for
     * the company identified by the `company_uuid` stored in the session. If the extension
     * is not installed, an error response is returned.
     *
     * If the extension is installed, it proceeds to uninstall the extension using Composer
     * and removes the corresponding installation records from the database. If any errors
     * occur during the Composer uninstallation process, an error response is returned.
     *
     * @param InstallExtensionRequest $request the request containing the public ID of the extension to be uninstalled
     *
     * @return \Illuminate\Http\JsonResponse a JSON response indicating the status of the uninstallation process
     */
    public function uninstall(InstallExtensionRequest $request)
    {
        $extension   = RegistryExtension::where('public_id', $request->input('extension'))->first();
        $uninstalled = false;

        // Check if already uninstalled
        $uninstalled = RegistryExtensionInstall::disableCache()->where(['company_uuid' => session('company'), 'extension_uuid' => $extension->uuid])->doesntExist();
        if ($uninstalled) {
            return response()->error('This extension is not installed.');
        }

        // Uninstall for Composer
        try {
            $extension->currentBundle->uninstallComposerPackage();
        } catch (\Throwable $e) {
            return response()->error($e->getMessage());
        }

        // Remove install records
        $uninstalled = RegistryExtensionInstall::where([
            'company_uuid'   => session('company'),
            'extension_uuid' => $extension->uuid,
        ])->delete();

        return response()->json(['status' => 'ok', 'uninstalled' => $uninstalled]);
    }
}
