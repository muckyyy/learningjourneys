<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CertificateController extends Controller
{
    /**
     * Display a listing of the certificates.
     */
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'only_enabled' => $request->boolean('only_enabled'),
        ];

        $certificates = Certificate::query()
            ->with(['institutions:id,name'])
            ->withCount(['elements', 'issues'])
            ->when($filters['q'], function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($filters['only_enabled'], fn ($query) => $query->where('enabled', true))
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        $metrics = [
            'total' => Certificate::count(),
            'enabled' => Certificate::where('enabled', true)->count(),
            'institutions' => DB::table('certificate_institution')->distinct('institution_id')->count('institution_id'),
            'issues' => CertificateIssue::count(),
        ];

        return view('admin.certificates.index', [
            'certificates' => $certificates,
            'filters' => $filters,
            'metrics' => $metrics,
        ]);
    }

    /**
     * Show the form for creating a new certificate.
     */
    public function create()
    {
        return view('admin.certificates.create');
    }

    public function edit(Certificate $certificate)
    {
        return view('admin.certificates.edit', [
            'certificate' => $certificate,
        ]);
    }

    /**
     * Store a newly created certificate definition.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'page_size' => ['required', 'string', 'max:32'],
            'orientation' => ['required', Rule::in(['portrait', 'landscape'])],
            'page_width_mm' => ['nullable', 'integer', 'min:100', 'max:2000'],
            'page_height_mm' => ['nullable', 'integer', 'min:100', 'max:2000'],
        ]);

        [$width, $height] = $this->resolveDimensions(
            $data['page_size'],
            $data['orientation'],
            $data['page_width_mm'] ?? null,
            $data['page_height_mm'] ?? null
        );

        $certificate = Certificate::create([
            'name' => $data['name'],
            'enabled' => $data['enabled'] ?? false,
            'validity_days' => $data['validity_days'] ?? null,
            'page_size' => $data['page_size'],
            'orientation' => $data['orientation'],
            'page_width_mm' => $width,
            'page_height_mm' => $height,
        ]);

        return redirect()
            ->route('admin.certificates.index')
            ->with('status', "Certificate '{$certificate->name}' created successfully.");
    }

    public function update(Request $request, Certificate $certificate)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'page_size' => ['required', 'string', 'max:32'],
            'orientation' => ['required', Rule::in(['portrait', 'landscape'])],
            'page_width_mm' => ['nullable', 'integer', 'min:100', 'max:2000'],
            'page_height_mm' => ['nullable', 'integer', 'min:100', 'max:2000'],
        ]);

        [$width, $height] = $this->resolveDimensions(
            $data['page_size'],
            $data['orientation'],
            $data['page_width_mm'] ?? null,
            $data['page_height_mm'] ?? null
        );

        $certificate->update([
            'name' => $data['name'],
            'enabled' => $request->boolean('enabled'),
            'validity_days' => $data['validity_days'] ?? null,
            'page_size' => $data['page_size'],
            'orientation' => $data['orientation'],
            'page_width_mm' => $width,
            'page_height_mm' => $height,
        ]);

        return redirect()
            ->route('admin.certificates.index')
            ->with('status', "Certificate '{$certificate->name}' updated.");
    }

    public function editInstitutions(Certificate $certificate)
    {
        $certificate->load('institutions:id,name');

        $institutions = Institution::orderBy('name')
            ->get(['id', 'name', 'is_active', 'contact_email']);

        return view('admin.certificates.institutions', [
            'certificate' => $certificate,
            'institutions' => $institutions,
            'assigned' => $certificate->institutions->pluck('id')->all(),
        ]);
    }

    public function updateInstitutions(Request $request, Certificate $certificate)
    {
        $data = $request->validate([
            'institutions' => ['array'],
            'institutions.*' => ['integer', 'exists:institutions,id'],
        ]);

        $selectedIds = collect($data['institutions'] ?? [])
            ->filter()
            ->unique()
            ->values();

        $certificate->load('institutions:id');
        $existing = $certificate->institutions
            ->mapWithKeys(fn ($inst) => [$inst->id => $inst->pivot->granted_at]);

        $now = now();
        $syncPayload = [];
        foreach ($selectedIds as $id) {
            $syncPayload[$id] = ['granted_at' => $existing[$id] ?? $now];
        }

        $certificate->institutions()->sync($syncPayload);

        return redirect()
            ->route('admin.certificates.institutions.edit', $certificate)
            ->with('status', 'Institution assignments updated.');
    }

    protected function resolveDimensions(string $size, string $orientation, ?int $width, ?int $height): array
    {
        if ($width && $height) {
            return [$width, $height];
        }

        $presets = [
            'A4' => ['portrait' => [210, 297], 'landscape' => [297, 210]],
            'LETTER' => ['portrait' => [216, 279], 'landscape' => [279, 216]],
        ];

        $key = strtoupper($size);
        if (!isset($presets[$key])) {
            return [$width ?? 210, $height ?? 297];
        }

        return $presets[$key][$orientation] ?? [$width ?? 210, $height ?? 297];
    }
}
