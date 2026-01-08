<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    /**
     * Display a listing of the certificates.
     */
    public function index(Request $request)
    {
        $certificates = Certificate::query()
            ->with(['institutions:id,name'])
            ->withCount(['elements', 'issues'])
            ->when($request->boolean('only_enabled'), fn ($query) => $query->where('enabled', true))
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.certificates.index', [
            'certificates' => $certificates,
            'filters' => [
                'only_enabled' => $request->boolean('only_enabled'),
            ],
        ]);
    }
}
