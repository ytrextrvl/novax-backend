<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FALaravel\Support\Google2FA;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:12'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole('user');
        $token = JWTAuth::fromUser($user);

        return response()->json($this->tokenResponse($token, $user));
    }

    public function login(Request $request)
    {
        [$token, $user] = $this->authenticate($request);

        if ($user->mfa_enabled) {
            return response()->json([
                'mfa_required' => true,
                'temp_token' => $token,
                'user' => $this->userPayload($user),
            ]);
        }

        return response()->json($this->tokenResponse($token, $user));
    }

    public function adminLogin(Request $request)
    {
        [$token, $user] = $this->authenticate($request);

        if (! $user->hasRole('admin')) {
            auth('api')->logout(true);
            abort(403, 'Admin access required.');
        }

        if ($user->mfa_enabled) {
            return response()->json([
                'mfa_required' => true,
                'temp_token' => $token,
                'user' => $this->userPayload($user),
            ]);
        }

        return response()->json($this->tokenResponse($token, $user));
    }

    public function logout()
    {
        auth('api')->logout(true);
        return response()->json(['message' => 'Logged out.']);
    }

    public function refresh()
    {
        return response()->json($this->tokenResponse(auth('api')->refresh(true, true), auth('api')->user()));
    }

    public function me()
    {
        $user = auth('api')->user();
        return response()->json([
            'user' => array_merge($this->userPayload($user), [
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ])
        ]);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required','string'],
            'password' => ['required','string','min:12','confirmed'],
        ]);

        $user = auth('api')->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        activity()->causedBy($user)->performedOn($user)->log('auth.password_change');

        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function mfaEnable(Request $request)
    {
        $user = auth('api')->user();

        /** @var Google2FA $google2fa */
        $google2fa = app('pragmarx.google2fa');
        $secret = $google2fa->generateSecretKey();
        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_enabled' => false,
        ])->save();

        $inlineUrl = $google2fa->getQRCodeInline(config('app.name'), $user->email, $secret);

        return response()->json(['secret' => $secret, 'qr' => $inlineUrl]);
    }

    public function mfaVerify(Request $request)
    {
        $data = $request->validate(['code' => ['required','string']]);
        $user = auth('api')->user();

        /** @var Google2FA $google2fa */
        $google2fa = app('pragmarx.google2fa');
        $valid = $user->mfa_secret ? $google2fa->verifyKey($user->mfa_secret, $data['code']) : false;

        if (! $valid) {
            throw ValidationException::withMessages(['code' => ['Invalid MFA code.']]);
        }

        $user->forceFill(['mfa_enabled' => true])->save();
        $token = JWTAuth::getToken();
        return response()->json($this->tokenResponse((string)$token, $user));
    }

    private function authenticate(Request $request): array
    {
        $credentials = $request->validate([
            'email' => ['required','email'],
            'password' => ['required','string'],
        ]);

        if (! $token = auth('api')->attempt($credentials)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        /** @var User $user */
        $user = auth('api')->user();
        $user->forceFill(['last_login_at' => now()])->save();

        return [$token, $user];
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
        ];
    }

    private function tokenResponse(string $token, User $user): array
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $this->userPayload($user),
        ];
    }
}
