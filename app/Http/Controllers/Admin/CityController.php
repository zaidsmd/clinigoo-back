<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityController extends Controller
{
    /**
     * List all cities (active & inactive) for admin management.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('query') ?? $request->query('q') ?? $request->query('search');

        $query = City::query()->orderBy('display_order')->orderBy('name');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return response()->json($query->get());
    }

    /**
     * Store a new city.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:cities,name'],
            'region' => ['nullable', 'string', 'max:150'],
            'is_active' => ['boolean'],
            'display_order' => ['nullable', 'integer'],
        ]);

        $city = City::create([
            'name' => trim($validated['name']),
            'slug' => \Illuminate\Support\Str::slug($validated['name']),
            'region' => $validated['region'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'display_order' => $validated['display_order'] ?? 0,
        ]);

        return response()->json($city, 201);
    }

    /**
     * Update a city.
     */
    public function update(Request $request, City $city): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', 'unique:cities,name,'.$city->id],
            'region' => ['nullable', 'string', 'max:150'],
            'is_active' => ['boolean'],
            'display_order' => ['nullable', 'integer'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);
        }

        $city->update($validated);

        return response()->json($city);
    }

    /**
     * Toggle active state or delete.
     */
    public function destroy(City $city): JsonResponse
    {
        $city->delete();

        return response()->json(['message' => 'Ville supprimée avec succès.']);
    }
}

