<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TokenBundle;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TokenBundleController extends Controller
{
    public function index()
    {
        $bundles = TokenBundle::orderBy('token_amount')->get();
        return response()->json($bundles);
    }

    public function store(Request $request)
    {
        $data = $this->validateBundle($request);
        $bundle = TokenBundle::create($data + [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json($bundle, 201);
    }

    public function update(Request $request, TokenBundle $bundle)
    {
        $data = $this->validateBundle($request, $bundle->id);
        $bundle->update($data + ['updated_by' => $request->user()->id]);

        return response()->json($bundle);
    }

    public function destroy(TokenBundle $bundle)
    {
        $bundle->delete();

        return response()->json(['status' => 'deleted']);
    }

    protected function validateBundle(Request $request, ?int $bundleId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('token_bundles', 'slug')->ignore($bundleId)],
            'description' => ['nullable', 'string'],
            'token_amount' => ['required', 'integer', 'min:1'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'expires_after_days' => ['required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        $data['slug'] = Str::slug($data['slug'] ?? $data['name']);
        $data['metadata'] = $data['metadata'] ?? null;

        return $data;
    }
}
