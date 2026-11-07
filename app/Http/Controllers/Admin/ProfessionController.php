<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProfessionRequest;
use App\Http\Requests\Admin\UpdateProfessionRequest;
use App\Http\Resources\ProfessionResource;
use App\Models\Profession;
use App\Services\ProfessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfessionController extends Controller
{
    public function __construct(protected ProfessionService $professionService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->professionService->dataTable($request->all());
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Les 6 professions de santé sont désormais figées. La création via CRUD a été éliminée.',
        ], 403);
    }

    public function show(Profession|int|string $profession): JsonResponse
    {
        if (! $profession instanceof Profession) {
            $profession = Profession::where('slug', $profession)
                ->orWhere('id', $profession)
                ->firstOrFail();
        }

        $profession->load(['services', 'documents']);

        return response()->json(new ProfessionResource($profession));
    }

    public function update(Request $request, Profession|int|string $profession): JsonResponse
    {
        if (! $profession instanceof Profession) {
            $profession = Profession::where('slug', $profession)
                ->orWhere('id', $profession)
                ->firstOrFail();
        }

        if ($request->has('documents')) {
            $this->professionService->syncDocuments($profession, (array) $request->input('documents'));
            $profession->load(['services', 'documents']);

            return response()->json(new ProfessionResource($profession));
        }

        return response()->json([
            'message' => 'Les 6 professions de santé sont désormais figées. Seules les exigences documentaires peuvent être configurées.',
        ], 403);
    }

    public function updateDocuments(Request $request, Profession|int|string $profession): JsonResponse
    {
        if (! $profession instanceof Profession) {
            $profession = Profession::where('slug', $profession)
                ->orWhere('id', $profession)
                ->firstOrFail();
        }

        $documents = $request->input('documents', []);
        $this->professionService->syncDocuments($profession, (array) $documents);
        $profession->load(['services', 'documents']);

        return response()->json(new ProfessionResource($profession));
    }

    public function destroy(Profession|int|string $profession): JsonResponse
    {
        return response()->json([
            'message' => 'Les 6 professions de santé sont désormais figées. La suppression via CRUD a été éliminée.',
        ], 403);
    }
}
