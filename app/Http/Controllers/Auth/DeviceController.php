<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateDeviceRequest;
use App\Http\Resources\Auth\DeviceResource;
use App\Services\Auth\DeviceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DeviceController extends Controller
{
    public function __construct(
        private readonly DeviceService $deviceService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return DeviceResource::collection(
            $this->deviceService->listForUser($request->user())
        );
    }

    public function update(UpdateDeviceRequest $request, string $id): DeviceResource
    {
        $device = $this->deviceService->rename(
            $request->user(),
            $id,
            $request->validated('device_name'),
        );

        return new DeviceResource($device);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->deviceService->revoke($request->user(), $id);

        return response()->noContent();
    }
}
