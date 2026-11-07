<?php

namespace App\Services;

use App\Enums\DocumentVerificationStatus;
use App\Enums\HealthcareProfession;
use App\Enums\ProfessionalHistoryAction;
use App\Enums\ProfessionalStatus;
use App\Models\Profession;
use App\Models\ProfessionalDecisionHistory;
use App\Models\ProfessionalDocument;
use App\Models\ProfessionalProfile;
use App\Models\ProfessionDocument;
use App\Models\Service;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfessionalProfileService
{
    /**
     * Get the full dossier for a professional profile.
     *
     * @return array<string, mixed>
     */
    public function getDossier(ProfessionalProfile $profile): array
    {
        $profile->loadMissing([
            'user',
            'profession.services',
            'profession.documents',
            'services',
            'documents.professionDocument',
            'decisionHistories.admin',
        ]);

        $profession = $profile->profession;
        $requiredDocIds = $profession
            ? $profession->documents()->wherePivot('required', true)->pluck('profession_documents.id')->all()
            : [];

        $uploadedDocs = $profile->documents->keyBy('profession_document_id');

        $documentChecklist = [];
        if ($profession) {
            foreach ($profession->documents as $doc) {
                $isRequired = (bool) ($doc->pivot->required ?? false);
                $uploaded = $uploadedDocs->get($doc->id);

                $documentChecklist[] = [
                    'profession_document_id' => $doc->id,
                    'name' => $doc->name,
                    'is_required' => $isRequired,
                    'formats' => $doc->formats ?? ['pdf', 'jpg', 'jpeg', 'png'],
                    'uploaded' => $uploaded !== null,
                    'document' => $uploaded ? [
                        'id' => $uploaded->id,
                        'file_path' => $uploaded->file_path,
                        'original_name' => $uploaded->original_name,
                        'url' => $uploaded->url,
                        'status' => $uploaded->status->value,
                        'rejection_reason' => $uploaded->rejection_reason,
                    ] : null,
                ];
            }
        }

        $profEnum = HealthcareProfession::fromInput($profile->profession ?? $profile->user->profession ?? $profession?->slug);

        return [
            'id' => $profile->id,
            'user' => [
                'id' => $profile->user->id,
                'email' => $profile->user->email,
                'name' => $profile->user->name,
                'profession' => $profile->user->profession?->value ?? ($profile->user->profession ?? $profEnum?->value),
                'profession_label' => $profEnum?->label(),
            ],
            'first_name' => $profile->first_name,
            'last_name' => $profile->last_name,
            'full_name' => $profile->full_name,
            'sexe' => $profile->sexe,
            'id_number' => $profile->id_number,
            'phone' => $profile->phone,
            'city' => $profile->city,
            'birthday' => $profile->birthday ? (is_string($profile->birthday) ? $profile->birthday : $profile->birthday->format('Y-m-d')) : null,
            'bio' => $profile->bio,
            'avatar' => $profile->avatar,
            'status' => $profile->status->value,
            'status_label' => $profile->status->label(),
            'rejection_reason' => $profile->rejection_reason,
            'is_available' => (bool) $profile->is_available,
            'is_validated' => $profile->isValidated(),
            'is_eligible' => $profile->isEligible(),
            'submitted_at' => $profile->submitted_at?->toIso8601String(),
            'validated_at' => $profile->validated_at?->toIso8601String(),
            'profession' => $profession ? [
                'id' => $profession->id,
                'key' => $profession->slug,
                'name' => $profession->name,
                'slug' => $profession->slug,
                'description' => $profession->description,
                'category' => $profEnum?->category()->label(),
            ] : ($profEnum ? [
                'id' => $profEnum->value,
                'key' => $profEnum->value,
                'name' => $profEnum->label(),
                'slug' => $profEnum->value,
                'description' => $profEnum->category()->label(),
                'category' => $profEnum->category()->label(),
            ] : null),
            'services' => $profile->services->map(fn (Service $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'base_price' => $s->base_price,
                'duration_minutes' => $s->duration_minutes,
            ])->values()->all(),
            'documents' => $profile->documents->map(fn (ProfessionalDocument $d) => [
                'id' => $d->id,
                'profession_document_id' => $d->profession_document_id,
                'document_name' => $d->professionDocument?->name,
                'original_name' => $d->original_name,
                'url' => $d->url,
                'mime_type' => $d->mime_type,
                'file_size' => $d->file_size,
                'status' => $d->status->value,
                'rejection_reason' => $d->rejection_reason,
                'verified_at' => $d->verified_at?->toIso8601String(),
            ])->values()->all(),
            'document_checklist' => $documentChecklist,
            'pending_changes' => $profile->pending_data,
            'history' => $profile->decisionHistories->map(fn (ProfessionalDecisionHistory $h) => [
                'id' => $h->id,
                'action' => $h->action->value,
                'action_label' => $h->action->label(),
                'status_before' => $h->status_before,
                'status_after' => $h->status_after,
                'reason' => $h->reason,
                'metadata' => $h->metadata,
                'admin_name' => $h->admin?->name,
                'created_at' => $h->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * Update non-sensitive profile information.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateNonSensitiveProfile(ProfessionalProfile $profile, array $data): ProfessionalProfile
    {
        $fillable = array_intersect_key($data, array_flip(['bio', 'avatar', 'phone']));
        $profile->fill($fillable);

        if (isset($data['is_available'])) {
            $prevAvailable = (bool) $profile->is_available;
            $newAvailable = (bool) $data['is_available'];
            if ($prevAvailable !== $newAvailable) {
                $profile->is_available = $newAvailable;
                $profile->recordHistory(
                    ProfessionalHistoryAction::AvailabilityChanged,
                    null,
                    ['is_available' => $newAvailable]
                );
            }
        }

        $profile->save();

        return $profile;
    }

    /**
     * Update sensitive information (identity, profession, services).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateSensitiveProfile(ProfessionalProfile $profile, array $data): array
    {
        // If already validated, sensitive changes must be queued in pending_data
        if ($profile->isValidated()) {
            return $this->requestSensitiveChanges($profile, $data);
        }

        // If draft, rejected, or pending, apply updates directly
        DB::transaction(function () use ($profile, $data) {
            if (isset($data['profession_id'])) {
                $newProfessionId = (int) $data['profession_id'];
                $profession = Profession::where('is_active', true)->findOrFail($newProfessionId);
                $oldProfessionId = $profile->profession_id;

                $profile->profession_id = $profession->id;

                // If profession changed, prune incompatible services
                if ($oldProfessionId && $oldProfessionId !== $newProfessionId) {
                    $allowedServiceIds = $profession->services()->pluck('services.id')->all();
                    $profile->services()->whereNotIn('services.id', $allowedServiceIds)->detach();
                }
            }

            if (isset($data['first_name'])) {
                $profile->first_name = $data['first_name'];
            }
            if (isset($data['firstName'])) {
                $profile->first_name = $data['firstName'];
            }
            if (isset($data['last_name'])) {
                $profile->last_name = $data['last_name'];
            }
            if (isset($data['lastName'])) {
                $profile->last_name = $data['lastName'];
            }
            if (isset($data['sexe'])) {
                $profile->sexe = $data['sexe'];
            }
            if (isset($data['id_number'])) {
                $profile->id_number = $data['id_number'];
            }
            if (isset($data['ID'])) {
                $profile->id_number = $data['ID'];
            }

            $profile->save();

            // Sync services if provided
            if (isset($data['services']) && is_array($data['services'])) {
                $this->syncProposedServices($profile, $data['services']);
            }
        });

        return [
            'status' => 'updated_directly',
            'profile' => $profile->fresh(['profession', 'services', 'documents']),
        ];
    }

    /**
     * Submit sensitive modifications for administrative review while keeping active info intact.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function requestSensitiveChanges(ProfessionalProfile $profile, array $data): array
    {
        $pending = [];

        if (isset($data['first_name']) || isset($data['firstName'])) {
            $pending['first_name'] = $data['first_name'] ?? $data['firstName'];
        }
        if (isset($data['last_name']) || isset($data['lastName'])) {
            $pending['last_name'] = $data['last_name'] ?? $data['lastName'];
        }
        if (isset($data['sexe'])) {
            $pending['sexe'] = $data['sexe'];
        }
        if (isset($data['id_number']) || isset($data['ID'])) {
            $pending['id_number'] = $data['id_number'] ?? $data['ID'];
        }

        if (isset($data['profession_id'])) {
            $profession = Profession::where('is_active', true)->findOrFail((int) $data['profession_id']);
            $pending['profession_id'] = $profession->id;
            $pending['profession_name'] = $profession->name;
        }

        if (isset($data['services']) && is_array($data['services'])) {
            $targetProfessionId = $pending['profession_id'] ?? $profile->profession_id;
            if (! $targetProfessionId) {
                throw ValidationException::withMessages([
                    'services' => ['Une profession doit être associée avant de choisir des prestations.'],
                ]);
            }

            $profession = Profession::with('services')->findOrFail($targetProfessionId);
            $allowedServiceIds = $profession->services->pluck('id')->all();

            foreach ($data['services'] as $serviceId) {
                if (! in_array((int) $serviceId, $allowedServiceIds, true)) {
                    throw ValidationException::withMessages([
                        'services' => ["La prestation #{$serviceId} n'est pas autorisée pour cette profession."],
                    ]);
                }
            }

            $pending['services'] = array_values(array_map('intval', $data['services']));
        }

        $profile->pending_data = $pending;
        $profile->status = ProfessionalStatus::PendingChanges;
        $profile->save();

        $profile->recordHistory(
            ProfessionalHistoryAction::SensitiveChangesSubmitted,
            'Demande de modification d\'informations sensibles soumise pour vérification.',
            ['pending_changes' => $pending],
            null,
            ProfessionalStatus::Validated->value,
            ProfessionalStatus::PendingChanges->value
        );

        return [
            'status' => 'pending_verification',
            'message' => 'Vos modifications sensibles ont été soumises à la validation administrative. Vos informations validées actuelles restent actives.',
            'pending_changes' => $pending,
            'profile' => $profile->fresh(['profession', 'services', 'documents']),
        ];
    }

    /**
     * Sync proposed services for a profile, checking that all belong to the profession.
     *
     * @param  array<int|string>  $serviceIds
     */
    public function syncProposedServices(ProfessionalProfile $profile, array $serviceIds): void
    {
        if (! $profile->profession_id) {
            throw ValidationException::withMessages([
                'services' => ['Une profession valide doit être sélectionnée avant d\'ajouter des prestations.'],
            ]);
        }

        $profession = Profession::with('services')->findOrFail($profile->profession_id);
        $allowedIds = $profession->services->pluck('id')->all();

        $intServiceIds = array_values(array_map('intval', $serviceIds));

        foreach ($intServiceIds as $id) {
            if (! in_array($id, $allowedIds, true)) {
                throw ValidationException::withMessages([
                    'services' => ["La prestation ID {$id} n'est pas autorisée pour la profession '{$profession->name}'."],
                ]);
            }
        }

        $profile->services()->sync($intServiceIds);
    }

    /**
     * Upload or replace a document.
     */
    public function uploadDocument(
        ProfessionalProfile $profile,
        int $professionDocumentId,
        UploadedFile $file,
    ): ProfessionalDocument {
        $professionDocument = ProfessionDocument::findOrFail($professionDocumentId);

        // Check file extension against allowed formats
        $extension = strtolower($file->getClientOriginalExtension());
        if (! $professionDocument->isFormatAllowed($extension)) {
            $allowed = implode(', ', $professionDocument->formats ?? ['pdf', 'jpg', 'jpeg', 'png']);
            throw ValidationException::withMessages([
                'file' => ["Le format de fichier '.{$extension}' n'est pas autorisé pour ce document. Formats acceptés : {$allowed}."],
            ]);
        }

        $path = $file->store("professional_documents/{$profile->id}", 'public');

        /** @var ProfessionalDocument|null $existing */
        $existing = $profile->documents()->where('profession_document_id', $professionDocumentId)->first();

        if ($existing) {
            // Delete old file if present
            if ($existing->file_path && Storage::disk('public')->exists($existing->file_path)) {
                Storage::disk('public')->delete($existing->file_path);
            }

            $existing->update([
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'status' => DocumentVerificationStatus::Pending,
                'rejection_reason' => null,
                'verified_at' => null,
                'verified_by' => null,
            ]);

            $profile->recordHistory(
                ProfessionalHistoryAction::DocumentReplaced,
                "Remplacement du document '{$professionDocument->name}'.",
                ['document_id' => $existing->id, 'profession_document_id' => $professionDocumentId]
            );

            return $existing->fresh();
        }

        /** @var ProfessionalDocument $document */
        $document = $profile->documents()->create([
            'profession_document_id' => $professionDocumentId,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'status' => DocumentVerificationStatus::Pending,
        ]);

        return $document;
    }

    /**
     * Submit dossier for verification.
     *
     * @return array<string, mixed>
     */
    public function submitDossier(ProfessionalProfile $profile): array
    {
        $profile->loadMissing(['profession.documents', 'services', 'documents']);

        if (! $profile->profession_id && $profile->profession) {
            $slug = $profile->profession instanceof HealthcareProfession
                ? $profile->profession->value
                : (string) $profile->profession;
            $profModel = Profession::where('slug', $slug)->first();
            if ($profModel) {
                $profile->profession_id = $profModel->id;
                $profile->save();
                $profile->load('profession.documents');
            }
        }

        $errors = [];

        if (! $profile->profession_id && ! $profile->profession) {
            $errors['profession'] = ['Veuillez choisir une profession valide avant de soumettre votre dossier.'];
        }

        if ($profile->services->isEmpty()) {
            $errors['services'] = ['Vous devez sélectionner au moins une prestation autorisée pour votre profession.'];
        }

        // Validate mandatory documents
        if ($profile->profession) {
            $mandatoryDocs = $profile->profession->documents()->wherePivot('required', true)->get();
            $uploadedDocs = $profile->documents->keyBy('profession_document_id');

            $missingMandatory = [];
            foreach ($mandatoryDocs as $mandatoryDoc) {
                $uploaded = $uploadedDocs->get($mandatoryDoc->id);
                if (! $uploaded) {
                    $missingMandatory[] = $mandatoryDoc->name.' (manquant)';
                } elseif ($uploaded->isRejected()) {
                    $missingMandatory[] = $mandatoryDoc->name.' (rejeté : '.$uploaded->rejection_reason.')';
                }
            }

            if (! empty($missingMandatory)) {
                $errors['documents'] = [
                    'Les documents obligatoires suivants doivent être fournis ou corrigés : '.implode(', ', $missingMandatory),
                ];
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $prevStatus = $profile->status->value;

        // Set status to pending
        $profile->status = ProfessionalStatus::Pending;
        $profile->submitted_at = now();
        $profile->rejection_reason = null;
        $profile->save();

        $profile->recordHistory(
            ProfessionalHistoryAction::DossierSubmitted,
            'Dossier professionnel soumis pour vérification administrative.',
            null,
            null,
            $prevStatus,
            ProfessionalStatus::Pending->value
        );

        return [
            'success' => true,
            'message' => 'Votre dossier a été soumis avec succès à l\'administration de Clinigoo.',
            'profile' => $this->getDossier($profile),
        ];
    }

    /**
     * Admin: Verify an individual document (validate or reject).
     */
    public function adminVerifyDocument(
        ProfessionalDocument $document,
        string $decision,
        ?string $reason,
        User $admin,
    ): ProfessionalDocument {
        if (! $document->status->isPending()) {
            throw ValidationException::withMessages([
                'document' => ['Ce document a déjà été traité et ne peut plus être vérifié.'],
            ]);
        }

        $profile = $document->professionalProfile;
        $docName = $document->professionDocument?->name ?? 'Document';

        if ($decision === 'validate') {
            $documentStatusBefore = $document->status->value;
            $document->update([
                'status' => DocumentVerificationStatus::Validated,
                'rejection_reason' => null,
                'verified_at' => now(),
                'verified_by' => $admin->id,
            ]);

            $profile->recordHistory(
                ProfessionalHistoryAction::DocumentValidated,
                "Document '{$docName}' validé par l'administration.",
                [
                    'document_id' => $document->id,
                    'document_name' => $docName,
                    'document_status_before' => $documentStatusBefore,
                    'document_status_after' => DocumentVerificationStatus::Validated->value,
                ],
                $admin
            );
        } elseif ($decision === 'reject') {
            if (empty($reason)) {
                throw ValidationException::withMessages([
                    'reason' => ['Le motif de rejet est obligatoire pour rejeter un document.'],
                ]);
            }

            $documentStatusBefore = $document->status->value;
            $document->update([
                'status' => DocumentVerificationStatus::Rejected,
                'rejection_reason' => $reason,
                'verified_at' => now(),
                'verified_by' => $admin->id,
            ]);

            $profile->recordHistory(
                ProfessionalHistoryAction::DocumentRejected,
                $reason,
                [
                    'document_id' => $document->id,
                    'document_name' => $docName,
                    'document_status_before' => $documentStatusBefore,
                    'document_status_after' => DocumentVerificationStatus::Rejected->value,
                ],
                $admin
            );
        } else {
            throw ValidationException::withMessages([
                'decision' => ["La décision doit être 'validate' ou 'reject'."],
            ]);
        }

        return $document->fresh();
    }

    /**
     * Admin: Verify the complete dossier (validate or reject).
     *
     * @return array<string, mixed>
     */
    public function adminVerifyDossier(
        ProfessionalProfile $profile,
        string $decision,
        ?string $reason,
        User $admin,
    ): array {
        $profile->loadMissing(['profession.documents', 'services', 'documents']);

        if ($decision === 'validate') {
            // Cannot validate if any mandatory document is missing, pending, or rejected!
            if (! $profile->profession) {
                throw ValidationException::withMessages([
                    'dossier' => ['Impossible de valider un dossier sans profession associée.'],
                ]);
            }

            $mandatoryDocs = $profile->profession->documents()->wherePivot('required', true)->get();
            $uploadedDocs = $profile->documents->keyBy('profession_document_id');

            $unvalidatedMandatory = [];
            foreach ($mandatoryDocs as $mDoc) {
                $uploaded = $uploadedDocs->get($mDoc->id);
                if (! $uploaded || ! $uploaded->isValidated()) {
                    $unvalidatedMandatory[] = $mDoc->name;
                }
            }

            if (! empty($unvalidatedMandatory)) {
                throw ValidationException::withMessages([
                    'dossier' => [
                        'Le dossier ne peut pas être approuvé tant que des documents obligatoires sont manquants, rejetés ou en attente de vérification : '.implode(', ', $unvalidatedMandatory),
                    ],
                ]);
            }

            if ($profile->services->isEmpty()) {
                throw ValidationException::withMessages([
                    'dossier' => ['Le professionnel doit avoir au moins une prestation attribuée.'],
                ]);
            }

            $prevStatus = $profile->status->value;
            $profile->status = ProfessionalStatus::Validated;
            $profile->validated_at = now();
            $profile->validated_by = $admin->id;
            $profile->rejection_reason = null;
            $profile->save();

            $profile->recordHistory(
                ProfessionalHistoryAction::DossierValidated,
                'Dossier professionnel approuvé par l\'administration.',
                null,
                $admin,
                $prevStatus,
                ProfessionalStatus::Validated->value
            );

            return [
                'success' => true,
                'message' => 'Le dossier professionnel a été approuvé avec succès.',
                'profile' => $this->getDossier($profile),
            ];
        }

        if ($decision === 'reject') {
            if (empty($reason)) {
                throw ValidationException::withMessages([
                    'reason' => ['Une décision de rejet doit obligatoirement être motivée.'],
                ]);
            }

            $prevStatus = $profile->status->value;
            $profile->status = ProfessionalStatus::Rejected;
            $profile->rejection_reason = $reason;
            $profile->save();

            $profile->recordHistory(
                ProfessionalHistoryAction::DossierRejected,
                $reason,
                null,
                $admin,
                $prevStatus,
                ProfessionalStatus::Rejected->value
            );

            return [
                'success' => true,
                'message' => 'Le dossier professionnel a été rejeté avec motif.',
                'profile' => $this->getDossier($profile),
            ];
        }

        throw ValidationException::withMessages([
            'decision' => ["La décision doit être 'validate' ou 'reject'."],
        ]);
    }

    /**
     * Admin: Review and approve/reject sensitive changes request.
     *
     * @return array<string, mixed>
     */
    public function adminReviewSensitiveChanges(
        ProfessionalProfile $profile,
        string $decision,
        ?string $reason,
        User $admin,
    ): array {
        if (! $profile->hasPendingChanges() || empty($profile->pending_data)) {
            throw ValidationException::withMessages([
                'changes' => ['Aucune modification sensible en attente pour ce professionnel.'],
            ]);
        }

        $pending = $profile->pending_data;

        if ($decision === 'approve') {
            DB::transaction(function () use ($profile, $pending, $admin) {
                if (isset($pending['first_name'])) {
                    $profile->first_name = $pending['first_name'];
                }
                if (isset($pending['last_name'])) {
                    $profile->last_name = $pending['last_name'];
                }
                if (isset($pending['sexe'])) {
                    $profile->sexe = $pending['sexe'];
                }
                if (isset($pending['id_number'])) {
                    $profile->id_number = $pending['id_number'];
                }
                if (isset($pending['profession_id'])) {
                    $profile->profession_id = $pending['profession_id'];
                }

                if (isset($pending['services']) && is_array($pending['services'])) {
                    $profile->services()->sync($pending['services']);
                }

                $profile->pending_data = null;
                $profile->status = ProfessionalStatus::Validated;
                $profile->save();

                $profile->recordHistory(
                    ProfessionalHistoryAction::SensitiveChangesApproved,
                    'Modifications sensibles approuvées par l\'administration.',
                    ['applied_changes' => $pending],
                    $admin,
                    ProfessionalStatus::PendingChanges->value,
                    ProfessionalStatus::Validated->value
                );
            });

            return [
                'success' => true,
                'message' => 'Les modifications sensibles ont été appliquées avec succès.',
                'profile' => $this->getDossier($profile),
            ];
        }

        if ($decision === 'reject') {
            if (empty($reason)) {
                throw ValidationException::withMessages([
                    'reason' => ['Le motif de rejet des modifications est obligatoire.'],
                ]);
            }

            $profile->pending_data = null;
            $profile->status = ProfessionalStatus::Validated; // previously validated data remains intact!
            $profile->save();

            $profile->recordHistory(
                ProfessionalHistoryAction::SensitiveChangesRejected,
                $reason,
                ['rejected_changes' => $pending],
                $admin,
                ProfessionalStatus::PendingChanges->value,
                ProfessionalStatus::Validated->value
            );

            return [
                'success' => true,
                'message' => 'Les modifications sensibles ont été rejetées. Les informations validées existantes ont été conservées.',
                'profile' => $this->getDossier($profile),
            ];
        }

        throw ValidationException::withMessages([
            'decision' => ["La décision doit être 'approve' ou 'reject'."],
        ]);
    }

    /**
     * Admin: Directly update services for a professional.
     *
     * @param  array<int|string>  $serviceIds
     * @return array<string, mixed>
     */
    public function adminUpdateServices(
        ProfessionalProfile $profile,
        array $serviceIds,
        User $admin,
    ): array {
        if (! $profile->profession_id) {
            throw ValidationException::withMessages([
                'services' => ['Le professionnel doit avoir une profession valide pour lui attribuer des prestations.'],
            ]);
        }

        $profession = Profession::with('services')->findOrFail($profile->profession_id);
        $allowedIds = $profession->services->pluck('id')->all();

        $intServiceIds = array_values(array_map('intval', $serviceIds));

        foreach ($intServiceIds as $id) {
            if (! in_array($id, $allowedIds, true)) {
                throw ValidationException::withMessages([
                    'services' => ["La prestation #{$id} n'est pas autorisée pour la profession '{$profession->name}'."],
                ]);
            }
        }

        $oldServices = $profile->services->pluck('id')->all();
        $profile->services()->sync($intServiceIds);

        $profile->recordHistory(
            ProfessionalHistoryAction::ServicesUpdatedByAdmin,
            'Prestations modifiées directement par l\'administration.',
            [
                'previous_services' => $oldServices,
                'new_services' => $intServiceIds,
            ],
            $admin
        );

        return [
            'success' => true,
            'message' => 'Prestations mises à jour avec succès par l\'administration.',
            'services' => $profile->services()->get(),
        ];
    }

    /**
     * Get paginated professionals list for administrative inspection.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getProfessionalsList(array $filters = []): LengthAwarePaginator
    {
        $query = ProfessionalProfile::with([
            'user',
            'profession',
            'services',
            'documents',
        ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['profession_id'])) {
            $query->where('profession_id', (int) $filters['profession_id']);
        }

        if (isset($filters['is_available'])) {
            $query->where('is_available', (bool) $filters['is_available']);
        }

        if (isset($filters['is_validated'])) {
            $isValidated = (bool) $filters['is_validated'];
            if ($isValidated) {
                $query->where('status', ProfessionalStatus::Validated->value);
            } else {
                $query->where('status', '!=', ProfessionalStatus::Validated->value);
            }
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                        $userQuery->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->latest()->paginate($perPage);
    }
}
