<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HealthcareServiceCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServiceRequest;
use App\Http\Requests\Admin\UpdateServiceRequest;
use App\Models\Service;
use App\Services\ServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(protected ServiceService $serviceService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->serviceService->dataTable($request->all());
    }

    /**
     * Return the 5 fixed service categories.
     */
    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => HealthcareServiceCategory::options(),
            'total' => count(HealthcareServiceCategory::cases()),
        ]);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = $this->serviceService->storeService($request);

        return response()->json($service, 201);
    }

    public function show(Service|int|string $service): JsonResponse
    {
        if (! $service instanceof Service) {
            $service = Service::findOrFail($service);
        }

        return response()->json($service);
    }

    public function update(UpdateServiceRequest $request, Service|int|string $service): JsonResponse
    {
        if (! $service instanceof Service) {
            $service = Service::findOrFail($service);
        }

        $service = $this->serviceService->updateService($service, $request);

        return response()->json($service);
    }

    public function destroy(Service|int|string $service): JsonResponse
    {
        if (! $service instanceof Service) {
            $service = Service::findOrFail($service);
        }

        $this->serviceService->deleteService($service);

        return response()->json(null, 204);
    }
}
