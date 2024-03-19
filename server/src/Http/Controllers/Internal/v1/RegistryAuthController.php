<?php

namespace Fleetbase\RegistryBridge\Http\Controllers\Internal\v1;

use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Models\Company;
use Fleetbase\Models\User;
use Fleetbase\Support\Auth;
use Illuminate\Http\Request;

class RegistryAuthController extends Controller
{
    public function addUser(Request $request)
    {
        $companyId = $request->input('company');
        $email = $request->input('email');
        $password = $request->input('password');

        // Lookup company
        $company = Company::where('public_id', $companyId)->first();
        
        // Find user
        $user = User::where('email', $email)->whereHas('companies', function ($query) use ($company) {
            $query->where('company_uuid', $company->uuid);
        })->first();

        // Authenticate user with password
        if (Auth::isInvalidPassword($password, $user->password)) {
            return response()->error('Authentication failed using password provided.', 401, ['code' => 'invalid_password']);
        }

        // Create extension developer for company/user
        
    }
}
