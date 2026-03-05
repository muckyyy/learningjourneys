<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified', 'role:administrator']);
    }

    /**
     * Show the admin settings dashboard.
     */
    public function index()
    {
        return view('admin.settings.index', [
            'sections' => $this->settingsSections(),
        ]);
    }

    /**
     * Define the available settings sections.
     * New sections can be added here as admin features are migrated.
     */
    private function settingsSections(): array
    {
        return [
            [
                'key'         => 'tokens',
                'title'       => 'Token Management',
                'description' => 'Configure token bundles, pricing, grants and view token reports.',
                'icon'        => 'bi-coin',
                'route'       => route('admin.token-management.index'),
            ],
            [
                'key'         => 'certificates',
                'title'       => 'Certificates',
                'description' => 'Design, assign and manage certificates for journey completions.',
                'icon'        => 'bi-award',
                'route'       => route('admin.certificates.index'),
            ],
            [
                'key'         => 'users',
                'title'       => 'User Management',
                'description' => 'Manage users, roles and permissions across the platform.',
                'icon'        => 'bi-person-gear',
                'route'       => route('users.index'),
            ],
            [
                'key'         => 'institutions',
                'title'       => 'Institutions',
                'description' => 'Manage institutions, memberships and organisational settings.',
                'icon'        => 'bi-building',
                'route'       => route('institutions.index'),
            ],
            [
                'key'         => 'collections',
                'title'       => 'Collections',
                'description' => 'Organise journeys into collections and manage their visibility.',
                'icon'        => 'bi-collection',
                'route'       => route('collections.index'),
            ],
            [
                'key'         => 'editors',
                'title'       => 'Editors',
                'description' => 'Manage editor accounts and their content permissions.',
                'icon'        => 'bi-people',
                'route'       => route('editors.index'),
            ],
            [
                'key'         => 'profile-fields',
                'title'       => 'Profile Fields',
                'description' => 'Configure custom profile fields required for users.',
                'icon'        => 'bi-person-lines-fill',
                'route'       => route('profile-fields.index'),
            ],
            [
                'key'         => 'reports',
                'title'       => 'Reports',
                'description' => 'View platform-wide usage analytics and journey reports.',
                'icon'        => 'bi-graph-up',
                'route'       => route('reports.index'),
            ],
        ];
    }
}
