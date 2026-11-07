<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BeneficiaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BeneficiaryController extends Controller
{
    public function __construct(
        protected BeneficiaryService $beneficiaryService
    ) {}

    public function index(Request $request): mixed
    {
        return $this->beneficiaryService->dataTable($request->all());
    }
}

