<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentVerificationStatus;
use App\Enums\HealthcareProfession;
use App\Enums\ProfessionalHistoryAction;
use App\Enums\ProfessionalStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Profession;
use App\Models\ProfessionalDocument;
use App\Models\ProfessionalProfile;
use App\Models\User;
use App\Services\ProfessionalProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfessionalController extends Controller
{
    public function __construct(
        protected ProfessionalProfileService $profileService
    ) {}

    /**
     * Get the authenticated professional's profile or throw 403.
     */
    protected function getAuthenticatedProfile(): ProfessionalProfile
    {
        /** @var User|null $user */
        $user = Auth::guard('api')->user();

        if (! $user || ! $user->isProfessional()) {
            abort(403, 'Accès réservé aux professionnels de santé.');
        }

        /** @var ProfessionalProfile $profile */
        $profile = $user->professionalProfile()->firstOrCreate([
            'user_id' => $user->id,
        ], [
            'first_name' => $user->name,
            'last_name' => '',
        ]);

        return $profile;
    }

    /**
     * Public registration endpoint used by the web frontend.
     */
    public function publicRegister(Request $request): JsonResponse
    {
        $personal = $request->input('personal', []);

        $email = trim((string) ($personal['email'] ?? $request->input('email') ?? ''));
        $firstName = trim((string) ($personal['first_name'] ?? $request->input('first_name') ?? ''));
        $lastName = trim((string) ($personal['last_name'] ?? $request->input('last_name') ?? ''));
        $fullName = trim((string) ($personal['full_name'] ?? $request->input('name') ?? $request->input('full_name') ?? ''));

        if (empty($firstName) && empty($lastName) && !empty($fullName)) {
            $parts = explode(' ', $fullName, 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? '';
        } elseif (empty($fullName)) {
            $fullName = trim("{$firstName} {$lastName}");
        }

        $rawSex = $personal['sex'] ?? $personal['sexe'] ?? $request->input('sexe') ?? $request->input('sex') ?? 'M';
        $sex = match (strtoupper((string) $rawSex)) {
            'F', 'FEMME', 'FEMALE' => 'F',
            default => 'M',
        };

        $cin = strtoupper(trim((string) ($personal['cin'] ?? $request->input('id_number') ?? $request->input('cin') ?? '')));
        $phone = trim((string) ($personal['phone'] ?? $request->input('phone') ?? ''));
        $city = trim((string) ($personal['city'] ?? $request->input('city') ?? ''));
        $birthday = $personal['birthday'] ?? $personal['birth_date'] ?? $personal['date_of_birth'] ?? $request->input('birthday') ?? $request->input('birth_date');

        $rawProfession = $request->input('profession') ?? $request->input('profession_id');
        $professionEnum = HealthcareProfession::fromInput($rawProfession);
        $professionModel = $professionEnum ? Profession::where('slug', $professionEnum->value)->first() : null;
        $professionId = $professionModel?->id ?? (is_numeric($rawProfession) ? (int) $rawProfession : null);
        $professionValue = $professionEnum?->value ?? ($professionModel?->slug ?? null);

        $password = $personal['password'] ?? $request->input('password');
        $passwordConfirmation = $personal['password_confirmation'] ?? $request->input('password_confirmation');

        $errors = [];

        // 1. Profession validation
        if (! $professionEnum && ! $professionId) {
            $errors['profession'] = ['Veuillez sélectionner une des 6 professions de santé autorisées.'];
        }

        // 2. Personal info validation
        if (empty($firstName)) {
            $errors['personal.first_name'] = ['Le prénom est obligatoire.'];
        }
        if (empty($lastName)) {
            $errors['personal.last_name'] = ['Le nom est obligatoire.'];
        }
        if (empty($cin)) {
            $errors['personal.cin'] = ['Le numéro de CIN est obligatoire.'];
        } else {
            // Check CIN uniqueness
            $cinExists = ProfessionalProfile::where('id_number', $cin)->exists();
            if ($cinExists) {
                $errors['personal.cin'] = ['Ce numéro de CIN est déjà associé à un compte professionnel.'];
                $errors['cin'] = ['Ce numéro de CIN est déjà associé à un compte professionnel.'];
            }
        }

        if (empty($phone)) {
            $errors['personal.phone'] = ['Le numéro de téléphone est obligatoire.'];
        }

        if (empty($city)) {
            $errors['personal.city'] = ['La ville est obligatoire.'];
        }

        if (empty($birthday)) {
            $errors['personal.birthday'] = ['La date de naissance est obligatoire.'];
        }

        // 3. Email & Password validation
        if (empty($email)) {
            $errors['personal.email'] = ['L\'adresse email est obligatoire.'];
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['personal.email'] = ['L\'adresse email est invalide.'];
        } else {
            // Check Email uniqueness
            $emailExists = User::where('email', $email)->exists();
            if ($emailExists) {
                $errors['personal.email'] = ['Cette adresse email est déjà associée à un compte.'];
                $errors['email'] = ['Cette adresse email est déjà associée à un compte.'];
            }
        }

        if (empty($password)) {
            $errors['password'] = ['Le mot de passe est obligatoire.'];
        } elseif (strlen((string) $password) < 8) {
            $errors['password'] = ['Le mot de passe doit comporter au moins 8 caractères.'];
        } elseif ($passwordConfirmation !== null && $password !== $passwordConfirmation) {
            $errors['password_confirmation'] = ['La confirmation du mot de passe ne correspond pas.'];
        }

        if (! empty($errors)) {
            return response()->json([
                'message' => 'Les informations fournies sont invalides.',
                'errors' => $errors,
            ], 422);
        }

        /** @var User $user */
        $user = User::create([
            'name' => $fullName ?: "{$firstName} {$lastName}",
            'email' => $email,
            'password' => Hash::make($password),
            'type' => UserType::Professional,
            'profession' => $professionValue,
        ]);

        $serviceIds = $request->input('selected_service_ids') ?? $request->input('services', []);
        $documents = $request->file('documents', []);

        $hasCompletedDossier = ! empty($serviceIds) && ! empty($documents);

        /** @var ProfessionalProfile $profile */
        $profile = $user->professionalProfile()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'sexe' => $sex,
            'id_number' => $cin,
            'phone' => $phone,
            'city' => $city,
            'birthday' => $birthday ? date('Y-m-d', strtotime((string) $birthday)) : null,
            'profession' => $professionValue,
            'profession_id' => $professionId,
            'status' => $hasCompletedDossier ? ProfessionalStatus::Pending : ProfessionalStatus::Draft,
            'submitted_at' => $hasCompletedDossier ? now() : null,
        ]);

        // Sync services if provided
        if (! empty($serviceIds) && is_array($serviceIds)) {
            try {
                $this->profileService->syncProposedServices($profile, $serviceIds);
            } catch (\Exception $e) {
                // Ignore service sync errors during initial registration if not strict
            }
        }

        // Upload any attached documents
        if (is_array($documents)) {
            foreach ($documents as $docId => $file) {
                if ($file instanceof UploadedFile) {
                    try {
                        $this->profileService->uploadDocument($profile, (int) $docId, $file);
                    } catch (\Exception $e) {
                        // Continue uploading other documents
                    }
                }
            }
        }

        $profile->recordHistory(
            ProfessionalHistoryAction::DossierSubmitted,
            'Compte professionnel créé lors de l\'inscription web initiale.'
        );

        $token = Auth::guard('api')->login($user);

        $profile->load(['profession', 'services', 'documents.professionDocument']);

        return response()->json([
            'id' => $profile->id,
            'status' => $profile->status->value,
            'status_label' => $profile->status->label(),
            'first_name' => $profile->first_name,
            'last_name' => $profile->last_name,
            'full_name' => $profile->full_name,
            'cin' => $profile->id_number,
            'phone' => $profile->phone,
            'city' => $profile->city,
            'birthday' => $profile->birthday ? (is_string($profile->birthday) ? $profile->birthday : $profile->birthday->format('Y-m-d')) : null,
            'email' => $user->email,
            'profession' => $profile->profession ? [
                'id' => $profile->profession->id,
                'name' => $profile->profession->name,
                'slug' => $profile->profession->slug,
            ] : ($professionEnum ? [
                'id' => $professionId ?? $professionEnum->value,
                'name' => $professionEnum->label(),
                'slug' => $professionEnum->value,
            ] : null),
            'selected_services' => $profile->services->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'description' => $s->description ?? '',
                'icon' => $s->icon ?? '',
            ])->values()->all(),
            'submitted_documents' => $profile->documents->map(fn ($d) => [
                'id' => $d->id,
                'document_name' => $d->professionDocument?->name ?? 'Document',
                'status' => $d->status === DocumentVerificationStatus::Validated ? 'approved' : $d->status->value,
                'rejection_reason' => $d->rejection_reason,
                'url' => $d->url,
            ])->values()->all(),
            'access_token' => (string) $token,
            'token_type' => 'bearer',
            'message' => 'Inscription enregistrée avec succès. Bienvenue sur Clinigoo !',
        ], 201);
    }

    /**
     * Endpoint to check uniqueness of CIN and Email in real-time.
     */
    public function checkUniqueness(Request $request): JsonResponse
    {
        $email = trim((string) ($request->input('email') ?? ''));
        $cin = strtoupper(trim((string) ($request->input('cin') ?? '')));

        $errors = [];

        if (! empty($email)) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Format d\'email invalide.';
            } elseif (User::where('email', $email)->exists()) {
                $errors['email'] = 'Cette adresse email est déjà associée à un compte.';
            }
        }

        if (! empty($cin)) {
            if (ProfessionalProfile::where('id_number', $cin)->exists()) {
                $errors['cin'] = 'Ce numéro de CIN est déjà associé à un compte professionnel.';
            }
        }

        if (! empty($errors)) {
            return response()->json([
                'available' => false,
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'available' => true,
            'message' => 'Informations valides et disponibles.',
        ]);
    }

    /**
     * Public endpoint to view a professional registration status by ID.
     */
    public function publicShow(int|string $id): JsonResponse
    {
        /** @var ProfessionalProfile $profile */
        $profile = ProfessionalProfile::with([
            'user',
            'profession',
            'services',
            'documents.professionDocument',
        ])->findOrFail($id);

        $frontendStatus = match ($profile->status) {
            ProfessionalStatus::Validated => 'approved',
            ProfessionalStatus::PendingChanges => 'changes_pending',
            default => $profile->status->value,
        };

        return response()->json([
            'id' => $profile->id,
            'status' => $frontendStatus,
            'rejection_reason' => $profile->rejection_reason,
            'full_name' => $profile->full_name,
            'cin' => $profile->id_number,
            'phone' => $profile->phone,
            'email' => $profile->user?->email,
            'created_at' => $profile->created_at?->format('d/m/Y'),
            'updated_at' => $profile->updated_at?->format('d/m/Y'),
            'profession' => $profile->profession ? [
                'id' => $profile->profession->id,
                'name' => $profile->profession->name,
            ] : ($professionEnum ? [
                'id' => $professionId ?? $professionEnum->value,
                'name' => $professionEnum->label(),
            ] : null),
            'selected_services' => $profile->services->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'description' => $s->description ?? '',
                'icon' => $s->icon ?? '',
            ])->values()->all(),
            'submitted_documents' => $profile->documents->map(fn ($d) => [
                'id' => $d->id,
                'document_name' => $d->professionDocument?->name ?? 'Document',
                'status' => $d->status === DocumentVerificationStatus::Validated ? 'approved' : $d->status->value,
                'rejection_reason' => $d->rejection_reason,
                'url' => $d->url,
            ])->values()->all(),
            'pending_changes' => $profile->pending_data,
        ]);
    }

    /**
     * Get full dossier and lifecycle status of the authenticated professional.
     */
    public function dossier(): JsonResponse
    {
        $profile = $this->getAuthenticatedProfile();

        return response()->json($this->profileService->getDossier($profile));
    }

    /**
     * Update non-sensitive profile information (bio, avatar, phone).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $profile = $this->getAuthenticatedProfile();

        $validated = $request->validate([
            'bio' => ['nullable', 'string', 'max:2000'],
            'avatar' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_available' => ['nullable', 'boolean'],
        ]);

        $updated = $this->profileService->updateNonSensitiveProfile($profile, $validated);

        return response()->json([
            'message' => 'Profil mis à jour avec succès.',
            'profile' => $this->profileService->getDossier($updated),
        ]);
    }

    /**
     * Update sensitive information (identity, profession, services).
     */
    public function updateSensitiveInfo(Request $request): JsonResponse
    {
        $profile = $this->getAuthenticatedProfile();

        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'firstName' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'lastName' => ['nullable', 'string', 'max:255'],
            'sexe' => ['nullable', 'string', 'in:M,F,Autre'],
            'id_number' => ['nullable', 'string', 'max:100'],
            'ID' => ['nullable', 'string', 'max:100'],
            'profession_id' => ['nullable', 'integer', 'exists:professions,id'],
            'services' => ['nullable', 'array'],
            'services.*' => ['integer', 'exists:services,id'],
        ]);

        $result = $this->profileService->updateSensitiveProfile($profile, $validated);

        return response()->json($result);
    }

    /**
     * Toggle availability for missions.
     */
    public function updateAvailability(Request $request): JsonResponse
    {
        $profile = $this->getAuthenticatedProfile();

        $validated = $request->validate([
            'is_available' => ['required', 'boolean'],
        ]);

        $this->profileService->updateNonSensitiveProfile($profile, [
            'is_available' => $validated['is_available'],
        ]);

        return response()->json([
            'message' => 'Disponibilité mise à jour.',
            'is_available' => (bool) $profile->is_available,
            'is_validated' => $profile->isValidated(),
            'is_eligible' => $profile->isEligible(),
        ]);
    }

    /**
     * Upload or replace a document.
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $profile = $this->getAuthenticatedProfile();

        $validated = $request->validate([
            'profession_document_id' => ['required', 'integer', 'exists:profession_documents,id'],
            'file' => ['required', 'file', 'max:10240'], // 10MB max
        ]);

        $document = $this->profileService->uploadDocument(
            $profile,
            (int) $validated['profession_document_id'],
            $request->file('file')
        );

        return response()->json([
            'message' => 'Document téléversé avec succès. Il est en attente de vérification administrative.',
            'document' => [
                'id' => $document->id,
                'profession_document_id' => $document->profession_document_id,
                'original_name' => $document->original_name,
                'url' => $document->url,
                'status' => $document->status->value,
                'rejection_reason' => $document->rejection_reason,
            ],
        ], 201);
    }

    /**
     * Submit completed dossier for verification.
     */
    public function submit(): JsonResponse
    {
        $profile = $this->getAuthenticatedProfile();

        $result = $this->profileService->submitDossier($profile);

        return response()->json($result);
    }

    /**
     * Securely download or view uploaded document.
     */
    public function downloadDocument(int $documentId): BinaryFileResponse
    {
        $profile = $this->getAuthenticatedProfile();

        /** @var ProfessionalDocument $document */
        $document = $profile->documents()->findOrFail($documentId);

        if (! Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'Fichier introuvable.');
        }

        $path = Storage::disk('public')->path($document->file_path);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$document->original_name.'"',
        ]);
    }
}
