<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TokenBundle;
use App\Models\TokenPurchase;
use App\Models\User;
use App\Models\UserTokenGrant;
use App\Services\TokenLedger;
use App\Services\TokenReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TokenManagementController extends Controller
{
    public function __construct(private TokenLedger $ledger, private TokenReportService $reports)
    {
        $this->middleware(['auth', 'verified', 'role:administrator']);
    }

    public function index()
    {
        return view('admin.tokens.manage', [
            'bundles' => TokenBundle::orderBy('token_amount')->get(),
            'summary' => $this->reports->summary(),
            'recentPurchases' => TokenPurchase::with(['user', 'bundle'])->latest()->limit(8)->get(),
        ]);
    }

    public function storeBundle(Request $request)
    {
        $data = $this->validateBundle($request);

        TokenBundle::create($data + [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.token-management.index')->with('status', 'Token bundle created.');
    }

    public function updateBundle(Request $request, TokenBundle $bundle)
    {
        $data = $this->validateBundle($request, $bundle->id);
        $bundle->update($data + ['updated_by' => $request->user()->id]);

        return redirect()->route('admin.token-management.index')->with('status', 'Token bundle updated.');
    }

    public function deleteBundle(TokenBundle $bundle)
    {
        $bundle->delete();

        return redirect()->route('admin.token-management.index')->with('status', 'Token bundle deleted.');
    }

    public function grantTokens(Request $request)
    {
        $data = $request->validateWithBag('grant', [
            'email' => ['required', 'email', 'exists:users,email'],
            'tokens' => ['required', 'integer', 'min:1'],
            'expires_after_days' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::where('email', $data['email'])->firstOrFail();

        $tokens = (int) $data['tokens'];
        $expiresAfterDays = array_key_exists('expires_after_days', $data) && $data['expires_after_days'] !== null
            ? (int) $data['expires_after_days']
            : null;

        $this->ledger->grant($user, $tokens, [
            'source' => UserTokenGrant::SOURCE_MANUAL,
            'granted_by' => $request->user()->id,
            'notes' => $data['notes'] ?? null,
            'expires_after_days' => $expiresAfterDays,
            'description' => sprintf('Manual grant by %s', $request->user()->name),
        ]);

        return redirect()->route('admin.token-management.index')->with('status', 'Tokens granted to ' . $user->name . '.');
    }

    protected function validateBundle(Request $request, ?int $bundleId = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('token_bundles', 'slug')->ignore($bundleId)],
            'description' => ['nullable', 'string'],
            'token_amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'expires_after_days' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($request->has('price')) {
            $rules['price'] = ['required', 'numeric', 'min:0'];
        } else {
            $rules['price_cents'] = ['required', 'integer', 'min:0'];
        }

        $validated = $request->validateWithBag('bundle', $rules);

        if (array_key_exists('price', $validated)) {
            $validated['price_cents'] = $this->convertToCents($validated['price']);
            unset($validated['price']);
        }

        $validated['slug'] = Str::slug($validated['slug'] ?? $validated['name']);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function convertToCents(float|string $price): int
    {
        $sanitized = is_string($price) ? str_replace(',', '', $price) : $price;

        return (int) round((float) $sanitized * 100);
    }
}
