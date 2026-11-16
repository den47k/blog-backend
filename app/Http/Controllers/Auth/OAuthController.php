<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\AuthResponse;
use App\Services\Auth\OAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OAuthController extends Controller
{
    public function __construct(
        private readonly OAuthService $oauthService,
    ) {}

    public function redirect(Request $request, string $provider): JsonResponse
    {
        return response()->json($this->oauthService->redirectUrl($provider));
    }

    public function callback(Request $request, string $provider): JsonResponse
    {
        return AuthResponse::from($this->oauthService->handleCallback(
            $provider,
            $request->query('device_name', $provider),
            $request,
        ));
    }

    public function confirmLink(Request $request, string $provider): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:128'],
        ]);

        return AuthResponse::from($this->oauthService->confirmLink(
            $request->user(),
            $provider,
            $request->input('password'),
            $request->input('device_name'),
            $request,
        ));
    }

    public function unlink(Request $request, string $id): Response
    {
        $this->oauthService->unlink($request->user(), $id);

        return response()->noContent();
    }
}
