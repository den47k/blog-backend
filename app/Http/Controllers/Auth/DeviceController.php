<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\DeviceResource;
use App\Services\Auth\DeviceService;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct(
        private readonly DeviceService $deviceService,
    ) {}

    public function index(Request $request)
    {
        return DeviceResource::collection(
            $this->deviceService->listForUser($request->user())
        );
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'device_name' => ['required', 'string', 'max:128'],
        ]);

        $device = $this->deviceService->rename(
            $request->user(),
            $id,
            $request->input('device_name'),
        );

        return new DeviceResource($device);
    }

    public function destroy(Request $request, string $id)
    {
        $this->deviceService->revoke($request->user(), $id);

        return response()->noContent();
    }
}
