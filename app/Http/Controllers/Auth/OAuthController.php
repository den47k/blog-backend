<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\AuthenticatedUserResource;
use App\Services\Auth\DeviceService;
use App\Services\Auth\OAuthService;
use Illuminate\Http\Request;

class OAuthController extends Controller
{
    public function __construct(
        private readonly OAuthService $oauthService,
        private readonly DeviceService $deviceService,
    ) {}

    public function redirect(Request $request, string $provider)
    {
        return response()->json($this->oauthService->redirectUrl($provider));
    }

    public function callback(Request $request, string $provider)
    {
        $result = $this->oauthService->handleCallback($provider, $request);
        $deviceName = $request->query('device_name', $provider);

        if ($result['kind'] === 'logged_in') {
            $issued = $this->deviceService->issueForUser($result['user'], $deviceName, $request);

            return response()->json([
                'user' => new AuthenticatedUserResource($result['user']),
                'token' => $issued['token'],
                'token_type' => 'Bearer',
                'device_id' => $issued['device']->id,
            ]);
        }

        if ($result['kind'] === 'link_required') {
            return response()->json([
                'status' => 'link_required',
                'link_token' => $result['link_token'],
                'token_type' => 'Bearer',
                'expires_in' => $result['expires_in'],
            ], 409);
        }

        return response()->json([
            'status' => $result['kind'],
            'registration_token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_in' => $result['expires_in'],
        ]);
    }

    public function confirmLink(Request $request, string $provider)
    {
        $request->validate([
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:128'],
        ]);

        $result = $this->oauthService->confirmLink(
            $request->user(),
            $provider,
            $request->input('password'),
            $request,
        );

        if ($result['kind'] === 'logged_in') {
            $issued = $this->deviceService->issueForUser(
                $result['user'],
                $request->input('device_name'),
                $request,
            );

            return response()->json([
                'user' => new AuthenticatedUserResource($result['user']),
                'token' => $issued['token'],
                'token_type' => 'Bearer',
                'device_id' => $issued['device']->id,
            ]);
        }

        return response()->json([
            'status' => $result['kind'],
            'registration_token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_in' => $result['expires_in'],
        ]);
    }

    public function unlink(Request $request, string $id)
    {
        $this->oauthService->unlink($request->user(), $id);

        return response()->noContent();
    }
}
