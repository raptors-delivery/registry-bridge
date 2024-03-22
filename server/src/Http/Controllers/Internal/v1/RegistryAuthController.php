<?php

namespace Fleetbase\RegistryBridge\Http\Controllers\Internal\v1;

use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Models\User;
use Fleetbase\RegistryBridge\Http\Requests\AddRegistryUserRequest;
use Fleetbase\RegistryBridge\Http\Requests\AuthenticateRegistryUserRequest;
use Fleetbase\RegistryBridge\Http\Requests\RegistryAuthRequest;
use Fleetbase\RegistryBridge\Http\Resources\RegistryUser as RegistryUserResource;
use Fleetbase\RegistryBridge\Models\RegistryExtension;
use Fleetbase\RegistryBridge\Models\RegistryUser;
use Fleetbase\Support\Auth;

class RegistryAuthController extends Controller
{
    /**
     * Authenticates a registry user based on provided credentials.
     *
     * This method attempts to authenticate a user using an identity (which can be either an email or username)
     * and a password. Upon successful authentication, it either retrieves an existing token associated with
     * the user or generates a new one. The method returns the token and user information in JSON format.
     *
     * If authentication fails or a token cannot be generated or retrieved, an error response is returned.
     *
     * @param AuthenticateRegistryUserRequest $request the request object containing identity and password
     *
     * @return RegistryUserResource Returns a JSON resource representing the registry user along with groups and containing the auth token and additional user data.
     *                              Returns an error response if authentication fails or token generation is unsuccessful.
     */
    public function authenticate(AuthenticateRegistryUserRequest $request)
    {
        $identity = $request->input('identity');
        $password = $request->input('password');

        // Find user by email or username
        $user = User::where('email', $identity)->orWhere('username', $identity)->first();

        if (Auth::isInvalidPassword($password, $user->password)) {
            return response()->error('Invalid credentials.', 401);
        }

        // Get existing token for current user
        $registryUser = RegistryUser::where(['company_uuid' => $user->company_uuid, 'user_uuid' => $user->uuid])->first();
        if (!$registryUser) {
            // Create registry user
            $registryUser = RegistryUser::create([
                'company_uuid' => $user->company_uuid,
                'user_uuid'    => $user->uuid,
                'scope'        => '*',
                'expires_at'   => now()->addYear(),
                'name'         => $user->public_id . ' developer token',
            ]);
        }

        // If no token response with error
        if (!$registryUser) {
            return response()->error('Unable to authenticate.');
        }

        return new RegistryUserResource($registryUser);
    }

    /**
     * Adds a new user to the registry with authentication credentials.
     *
     * This method creates a registry user linked to the currently active company
     * of the user. It requires an identity (email or username) and password for
     * authentication. After successful authentication, it generates a developer
     * key for the user with a scope and expiration date.
     *
     * @param AddRegistryUserRequest $request the request object containing identity and password
     *
     * @return \Illuminate\Http\JsonResponse returns the newly created registry user data in JSON format
     */
    public function addUser(AddRegistryUserRequest $request)
    {
        $identity    = $request->input('identity');
        $password    = $request->input('password');

        // Find user by email or username
        $user = User::where('email', $identity)->orWhere('username', $identity)->first();

        // Authenticate user with password
        if (Auth::isInvalidPassword($password, $user->password)) {
            return response()->error('Invalid credentials.', 401);
        }

        // Create registry user
        $registryUser = RegistryUser::create([
            'company_uuid' => $user->company_uuid,
            'user_uuid'    => $user->uuid,
            'scope'        => '*',
            'expires_at'   => now()->addYear(),
            'name'         => $user->public_id . ' developer token',
        ]);

        return response()->json($registryUser);
    }

    /**
     * Checks if a user has access to the registry based on their identity.
     *
     * This function receives a request containing an 'identity' (which could be an email or username) and
     * attempts to find a corresponding user. If the user is found and they have admin privileges, it grants access
     * to the registry by returning a JSON response indicating that access is allowed. If the user doesn't have
     * admin privileges or the user can't be found based on the provided identity, it returns an error response.
     *
     * @param RegistryAuthRequest $request the request containing the user's identity information
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     *                                                                 Returns a JSON response indicating access is allowed if the user is an admin,
     *                                                                 or an error response if the user is not an admin or can't be found
     */
    public function checkAccess(RegistryAuthRequest $request)
    {
        // Get identity
        $identity    = $request->input('identity');

        // Find user by email or username
        $user = User::where('email', $identity)->orWhere('username', $identity)->first();

        // If user is not admin respond with error
        if (!$user->isAdmin()) {
            return response()->error('User is not allowed access to the registry.', 401);
        }

        // For now only admin users can access registry
        return response()->json(['allowed' => true]);
    }

    /**
     * Validates whether a user is allowed to publish or unpublish a specified package in the registry.
     *
     * This function extracts the user's identity, the package name, and the desired action ('publish' or 'unpublish')
     * from the request. It then performs several checks:
     *   1. Verifies that the specified package (extension) exists in the registry.
     *   2. Confirms the existence of the user associated with the provided identity.
     *   3. Checks if the user has administrative privileges.
     *   4. Ensures that the extension's status allows the desired action (either 'approved' or 'published' for publishing,
     *      'published' for unpublishing).
     * If these conditions are met, the function updates the extension's status based on the action and returns a
     * JSON response indicating the action is allowed. If any of these checks fail, it returns an error response.
     *
     * @param RegistryAuthRequest $request the request containing the user's identity, package information, and action
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     *                                                                 Returns a JSON response indicating the action is allowed if all checks pass, or
     *                                                                 an error response if any check fails
     */
    public function checkPublishAllowed(RegistryAuthRequest $request)
    {
        $identity    = $request->input('identity');
        $package     = $request->input('package');
        $action      = $request->input('action', 'publish');

        // Find package
        $extension = RegistryExtension::findByPackageName($package);
        if (!$extension) {
            return response()->error('Attempting to publish extension which has no record.', 401);
        }

        // Find user by email or username
        $user = User::where('email', $identity)->orWhere('username', $identity)->first();
        if (!$user) {
            return response()->error('Attempting to publish extension with invalid user.', 401);
        }

        // If user is not admin respond with error
        if (!$user->isAdmin()) {
            return response()->error('User is not allowed publish to the registry.', 401);
        }

        // Extension should be approved
        if (!in_array($extension->status, ['approved', 'published'])) {
            return response()->error('Attempting to publish extension which is not approved.', 401);
        }

        // Change status to published
        if ($action === 'publish') {
            $extension->update(['status' => 'published']);
        } elseif ($action === 'unpublish') {
            $extension->update(['status' => 'unpublished']);
        }

        // Passed all checks
        return response()->json(['allowed' => true]);
    }
}
