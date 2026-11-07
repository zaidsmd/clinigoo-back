<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\Facades\DataTables;

class CollaboratorController extends Controller
{
    /**
     * List all administrator collaborators.
     */
    public function index(Request $request): mixed
    {
        $query = User::where('type', UserType::Admin->value);

        return DataTables::of($query)
            ->filter(function ($q) use ($request) {
                if ($search = $request->input('search')) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                }
            })
            ->make(true);
    }

    /**
     * Store a new administrator collaborator.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ], [
            'name.required' => 'Le nom du collaborateur est obligatoire.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit comporter au moins 8 caractères.',
        ]);

        $collaborator = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'type' => UserType::Admin->value,
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'Collaborateur créé avec succès.',
            'data' => $collaborator,
        ], 201);
    }

    /**
     * Display a specific collaborator.
     */
    public function show(int|string $id): JsonResponse
    {
        $collaborator = User::where('type', UserType::Admin->value)->findOrFail($id);

        return response()->json($collaborator);
    }

    /**
     * Update an existing administrator collaborator.
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $collaborator = User::where('type', UserType::Admin->value)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $collaborator->id,
            'password' => 'nullable|string|min:8',
        ], [
            'name.required' => 'Le nom du collaborateur est obligatoire.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'password.min' => 'Le mot de passe doit comporter au moins 8 caractères.',
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $collaborator->update($data);

        return response()->json([
            'message' => 'Collaborateur mis à jour avec succès.',
            'data' => $collaborator,
        ]);
    }

    /**
     * Delete a collaborator.
     */
    public function destroy(int|string $id): JsonResponse
    {
        $collaborator = User::where('type', UserType::Admin->value)->findOrFail($id);

        if (auth()->id() === $collaborator->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte administrateur.',
            ], 422);
        }

        $collaborator->delete();

        return response()->json([
            'message' => 'Collaborateur supprimé avec succès.',
        ]);
    }
}
