<?php

namespace Fleetbase\RegistryBridge\Http\Controllers\Internal\v1;

use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Models\User;
use Fleetbase\RegistryBridge\Http\Requests\AddRegistryUserRequest;
use Fleetbase\RegistryBridge\Http\Requests\AuthenticateRegistryUserRequest;
use Fleetbase\RegistryBridge\Http\Resources\RegistryUser as RegistryUserResource;
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
}
