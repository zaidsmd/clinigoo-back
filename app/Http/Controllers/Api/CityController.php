<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityController extends Controller
{
    /**
     * List active cities for selection / autocomplete.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('query') ?? $request->query('q') ?? $request->query('search');

        $query = City::active();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $cities = $query->get(['id', 'name', 'slug', 'region']);

        return response()->json($cities);
    }
}

