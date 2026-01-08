<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class ActiveInstitutionController extends Controller
{
    public function __construct(private PermissionRegistrar $registrar)
    {
        $this->middleware('auth');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'institution_id' => 'required|exists:institutions,id',
        ]);

        $user = $request->user();

        if (!$user->hasMembership((int) $data['institution_id'])) {
            abort(403, 'You are not an active member of this institution.');
        }

        $user->update(['active_institution_id' => $data['institution_id']]);

        $this->registrar->setPermissionsTeamId($data['institution_id']);

        return back()->with('success', 'Active institution updated.');
    }
}
