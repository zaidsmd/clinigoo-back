<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CareRequest;
use App\Services\CareRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CareRequestController extends Controller
{
    public function __construct(
        protected CareRequestService $careRequestService
    ) {}

    /**
     * List all care requests with DataTables support and status counts.
     */
    public function index(Request $request): mixed
    {
        return $this->careRequestService->dataTable($request->all());
    }

    /**
     * Get real-time status counts for all care requests.
     */
    public function statusCounts(): JsonResponse
    {
        return response()->json($this->careRequestService->getStatusCounts());
    }

    /**
     * Show details of a specific care request.
     */
    public function show(int|string $id): JsonResponse
    {
        $careRequest = $this->careRequestService->find($id);

        return response()->json($careRequest);
    }

    /**
     * Update the status and notes of a care request.
     */
    public function updateStatus(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,searching,matched,confirmed,scheduled,in_progress,inprogress,completed,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        $careRequest = $this->careRequestService->find($id);
        $updated = $this->careRequestService->updateStatus(
            $careRequest,
            $validated['status'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Statut de la demande mis à jour avec succès.',
            'care_request' => $updated,
            'status_counts' => $this->careRequestService->getStatusCounts(),
        ]);
    }

    /**
     * Delete a care request.
     */
    public function destroy(int|string $id): JsonResponse
    {
        $careRequest = $this->careRequestService->find($id);
        $this->careRequestService->delete($careRequest);

        return response()->json(null, 204);
    }
}

