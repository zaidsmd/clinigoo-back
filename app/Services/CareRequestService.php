<?php

namespace App\Services;

use App\Models\CareRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class CareRequestService
{
    /**
     * Get DataTables response for care requests.
     */
    public function dataTable(array $filters = []): mixed
    {
        $query = CareRequest::with(['service', 'beneficiary', 'user.patientProfile', 'address', 'documents'])
            ->latest('id');

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $status = $filters['status'];
            if ($status === 'in_progress') {
                $query->whereIn('status', ['in_progress', 'inprogress']);
            } elseif ($status === 'matched') {
                $query->whereIn('status', ['matched', 'confirmed']);
            } elseif ($status === 'pending') {
                $query->whereIn('status', ['pending', 'searching']);
            } else {
                $query->where('status', $status);
            }
        }

        if (isset($filters['urgent']) && $filters['urgent'] !== '' && $filters['urgent'] !== 'all') {
            $isUrgent = filter_var($filters['urgent'], FILTER_VALIDATE_BOOLEAN);
            $query->where('urgent', $isUrgent);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return DataTables::of($query)
            ->filter(function ($query) use ($filters) {
                if (! empty($filters['search'])) {
                    $search = $filters['search'];
                    $query->where(function ($q) use ($search) {
                        $q->where('reference', 'like', "%{$search}%")
                            ->orWhere('status', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%")
                            ->orWhere('address_line', 'like', "%{$search}%")
                            ->orWhere('notes', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($uq) use ($search) {
                                $uq->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            })
                            ->orWhereHas('beneficiary', function ($bq) use ($search) {
                                $bq->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%");
                            })
                            ->orWhereHas('service', function ($sq) use ($search) {
                                $sq->where('name', 'like', "%{$search}%");
                            });
                    });
                }
            })
            ->with([
                'status_counts' => $this->getStatusCounts(),
            ])
            ->make(true);
    }

    /**
     * Get counts of care requests grouped by status.
     */
    public function getStatusCounts(): array
    {
        $rawCounts = CareRequest::query()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $total = CareRequest::count();

        $pending = ($rawCounts['pending'] ?? 0) + ($rawCounts['searching'] ?? 0);
        $matched = ($rawCounts['matched'] ?? 0) + ($rawCounts['confirmed'] ?? 0);
        $scheduled = $rawCounts['scheduled'] ?? 0;
        $inProgress = ($rawCounts['in_progress'] ?? 0) + ($rawCounts['inprogress'] ?? 0);
        $completed = $rawCounts['completed'] ?? 0;
        $cancelled = $rawCounts['cancelled'] ?? 0;

        return [
            'all' => $total,
            'pending' => $pending,
            'matched' => $matched,
            'scheduled' => $scheduled,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'cancelled' => $cancelled,
        ];
    }

    /**
     * Find a care request by id.
     */
    public function find(int|string $id): CareRequest
    {
        return CareRequest::with(['service', 'beneficiary', 'user.patientProfile', 'address', 'documents'])
            ->findOrFail($id);
    }

    /**
     * Update the status of a care request.
     */
    public function updateStatus(CareRequest $careRequest, string $status, ?string $notes = null): CareRequest
    {
        $updates = ['status' => $status];
        if ($notes !== null) {
            $updates['notes'] = $notes;
        }
        if ($status === 'cancelled') {
            $updates['cancelled_at'] = now();
        }

        $careRequest->update($updates);

        return $careRequest->fresh(['service', 'beneficiary', 'user.patientProfile', 'address', 'documents']);
    }

    /**
     * Delete a care request.
     */
    public function delete(CareRequest $careRequest): void
    {
        $careRequest->delete();
    }

    /**
     * Get all care requests for a user.
     */
    public function getForUser(int $userId): Collection
    {
        return CareRequest::with(['documents', 'service', 'beneficiary', 'address'])
            ->where('user_id', $userId)
            ->get();
    }

    /**
     * Store a new care request.
     *
     * @param  UploadedFile[]  $documents
     */
    public function store(int $userId, array $data, array $documents = []): CareRequest
    {
        return DB::transaction(function () use ($userId, $data, $documents) {
            $data['user_id'] = $userId;

            if (empty($data['reference'])) {
                $data['reference'] = 'CLG-'.rand(1000, 9999);
            }

            if (empty($data['uuid'])) {
                $data['uuid'] = (string) Str::uuid();
            }

            if (isset($data['address']) && is_array($data['address'])) {
                if (isset($data['address']['latitude'])) {
                    $data['latitude'] = $data['address']['latitude'];
                }
                if (isset($data['address']['longitude'])) {
                    $data['longitude'] = $data['address']['longitude'];
                }
            }

            if (! empty($data['urgent'])) {
                $data['date'] = null;
                $data['time'] = null;
                $data['scheduled_at'] = now();
            } elseif (! empty($data['date'])) {
                $timeStr = ! empty($data['time']) ? $data['time'] : '00:00';
                $data['scheduled_at'] = Carbon::parse($data['date'].' '.$timeStr);
            }

            // Remove non-model attributes
            $modelData = collect($data)->except(['address', 'documents'])->toArray();

            $careRequest = CareRequest::create($modelData);

            $this->uploadDocuments($careRequest, $documents);

            return $careRequest->load(['documents', 'service', 'beneficiary', 'address']);
        });
    }

    /**
     * Update an existing care request.
     */
    public function update(CareRequest $careRequest, array $data): CareRequest
    {
        if (isset($data['description'])) {
            $data['notes'] = $data['description'];
        }

        $careRequest->update($data);

        return $careRequest->load(['documents', 'service', 'beneficiary', 'address']);
    }

    /**
     * Upload and attach documents to a care request.
     *
     * @param  UploadedFile[]  $documents
     */
    public function uploadDocuments(CareRequest $careRequest, array $documents): void
    {
        foreach ($documents as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $path = $file->store('care_requests/documents', 'public');
                $careRequest->documents()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }
    }
}
