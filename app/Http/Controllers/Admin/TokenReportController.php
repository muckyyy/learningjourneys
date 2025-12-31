<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TokenReportService;

class TokenReportController extends Controller
{
    public function __construct(private TokenReportService $reportService)
    {
    }

    public function index()
    {
        return response()->json($this->reportService->summary());
    }
}
