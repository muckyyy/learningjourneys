<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\TokenBundle;
use App\Models\User;
use App\Services\PromptDefaults;
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
     * Show the prompt management page.
     */
    public function prompts()
    {
        $definitions = $this->promptDefinitions();

        // Load any saved overrides from settings table
        $saved = Setting::where('setting', 'like', 'prompt.%')->get()
            ->keyBy('setting');

        // Merge saved values into definitions
        foreach ($definitions as &$def) {
            $key = "prompt.{$def['key']}";
            if ($saved->has($key) && $saved[$key]->value !== null) {
                $def['value'] = $saved[$key]->value;
            }
        }
        unset($def);

        return view('admin.settings.prompts', compact('definitions'));
    }

    /**
     * Update prompt settings.
     */
    public function updatePrompts(Request $request)
    {
        $request->validate([
            'prompts'   => 'required|array',
            'prompts.*' => 'nullable|string',
        ]);

        foreach ($request->input('prompts') as $key => $value) {
            // Only store if different from default (save space), or always store
            Setting::set("prompt.{$key}", $value);
        }

        return redirect()->route('admin.settings.prompts')->with('status', 'Prompts updated successfully.');
    }

    /**
     * Reset a single prompt back to its default.
     */
    public function resetPrompt(Request $request)
    {
        $request->validate(['key' => 'required|string']);
        Setting::remove("prompt.{$request->input('key')}");

        return redirect()->route('admin.settings.prompts')->with('status', 'Prompt reset to default.');
    }

    /**
     * Show the general configuration page.
     */
    public function general()
    {
        $bundles = TokenBundle::where('is_active', true)->orderBy('name')->get();

        return view('admin.settings.general', [
            'signup_enabled'      => Setting::get('site.signup_enabled') !== null
                ? (bool) Setting::get('site.signup_enabled')
                : config('site.signup_enabled'),
            'signup_token_bundle' => Setting::get('site.signup_token_bundle') !== null
                ? (int) Setting::get('site.signup_token_bundle')
                : config('site.signup_token_bundle'),
            'referal_enabled'     => Setting::get('site.referal_enabled') !== null
                ? (bool) (int) Setting::get('site.referal_enabled')
                : config('site.referal_enabled'),
            'referal_frequency'   => Setting::get('site.referal_frequency') !== null
                ? (int) Setting::get('site.referal_frequency')
                : config('site.referal_frequency'),
            'referal_token_bundle' => Setting::get('site.referal_token_bundle') !== null
                ? (int) Setting::get('site.referal_token_bundle')
                : config('site.referal_token_bundle'),
            'bundles'             => $bundles,
        ]);
    }

    /**
     * Update general configuration.
     */
    public function updateGeneral(Request $request)
    {
        $request->validate([
            'signup_enabled'       => 'required|in:0,1',
            'signup_token_bundle'  => 'required|integer|min:0',
            'referal_enabled'      => 'required|in:0,1',
            'referal_frequency'    => 'required|integer|min:1',
            'referal_token_bundle' => 'required|integer|min:0',
        ]);

        Setting::set('site.signup_enabled', $request->input('signup_enabled'));
        Setting::set('site.signup_token_bundle', $request->input('signup_token_bundle'));
        Setting::set('site.referal_enabled', $request->input('referal_enabled'));
        Setting::set('site.referal_frequency', $request->input('referal_frequency'));
        Setting::set('site.referal_token_bundle', $request->input('referal_token_bundle'));

        $warning = null;

        try {
            \Artisan::call('optimize');
        } catch (\Throwable $e) {
            report($e);
            $warning = 'Settings were saved, but cache rebuild failed. Please run php artisan optimize manually.';
        }

        return redirect()
            ->route('admin.settings.general', ['saved' => 1])
            ->with('status', 'General configuration updated.')
            ->with('warning', $warning);
    }

    /**
     * All prompt definitions with keys, labels, descriptions, and defaults.
     */
    private function promptDefinitions(): array
    {
        return [
            [
                'key'         => 'master',
                'label'       => 'Master Prompt',
                'description' => 'The main system prompt sent to the Learning Guide for every journey conversation. Supports variables: {journey_description}, {student_name}, {student_email}, {profile_country}, {profile_city}, {profile_year_of_birth}, {profile_life_stage}, {profile_about_you}, {profile_language}, {journey_history}, {current_step}, {expected_output}.',
                'icon'        => 'bi-cpu',
                'default'     => PromptDefaults::getDefaultMasterPrompt(),
                'value'       => null,
                'rows'        => 18,
            ],
            [
                'key'         => 'rate',
                'label'       => 'Rate Prompt',
                'description' => 'Used to score a student response from 1-5. Variables: {journey_title}, {journey_description}, {current_step}.',
                'icon'        => 'bi-star-half',
                'default'     => PromptDefaults::getDefaultRatePrompt(),
                'value'       => null,
                'rows'        => 10,
            ],
            [
                'key'         => 'report',
                'label'       => 'Report Prompt',
                'description' => 'Generates a full report card for a student after a journey. Variables: {student_name}, {institution_name}, {journey_title}.',
                'icon'        => 'bi-file-earmark-text',
                'default'     => PromptDefaults::getDefaultReportPrompt(),
                'value'       => null,
                'rows'        => 16,
            ],
            [
                'key'         => 'step_config',
                'label'       => 'Step Config (JSON)',
                'description' => 'JSON mapping of step actions to AI node CSS classes used for response formatting.',
                'icon'        => 'bi-braces',
                'default'     => PromptDefaults::getDefaultStepConfig(),
                'value'       => null,
                'rows'        => 8,
            ],
            [
                'key'         => 'step_output',
                'label'       => 'Step Output — Start',
                'description' => 'Response format instructions for the step_start action (Reflection / Teaching / Task).',
                'icon'        => 'bi-play-circle',
                'default'     => PromptDefaults::getDefaultTextStepOutput(),
                'value'       => null,
                'rows'        => 10,
            ],
            [
                'key'         => 'step_output_retry',
                'label'       => 'Step Output — Retry',
                'description' => 'Response format instructions when a student retries a step.',
                'icon'        => 'bi-arrow-repeat',
                'default'     => PromptDefaults::getDefaultTextStepOutputRetry(),
                'value'       => null,
                'rows'        => 8,
            ],
            [
                'key'         => 'step_output_followup',
                'label'       => 'Step Output — Follow-up',
                'description' => 'Response format instructions for follow-up questions.',
                'icon'        => 'bi-chat-right-dots',
                'default'     => PromptDefaults::getDefaultTextStepOutputFollowUp(),
                'value'       => null,
                'rows'        => 6,
            ],
            [
                'key'         => 'step_output_complete',
                'label'       => 'Step Output — Complete',
                'description' => 'Response format instructions when a step is completed.',
                'icon'        => 'bi-check-circle',
                'default'     => PromptDefaults::getDefaultTextStepOutputComplete(),
                'value'       => null,
                'rows'        => 6,
            ],
            [
                'key'         => 'collection_cert',
                'label'       => 'Collection Certificate Prompt',
                'description' => 'Generates the AI report that accompanies a collection certificate. Variables: {student_name}, {student_email}, {institution_name}, {collection_title}, {completion_date}, {collection_description}, {journey_history}.',
                'icon'        => 'bi-patch-check',
                'default'     => PromptDefaults::getDefaultCollectionCertPrompt(),
                'value'       => null,
                'rows'        => 18,
            ],
        ];
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
                'key'         => 'collections',
                'title'       => 'Collections',
                'description' => 'Organise journeys into collections and manage their visibility.',
                'icon'        => 'bi-collection',
                'route'       => route('collections.index'),
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
            [
                'key'         => 'prompts',
                'title'       => 'Prompt Management',
                'description' => 'Configure and manage AI prompts used across the platform.',
                'icon'        => 'bi-chat-square-text',
                'route'       => route('admin.settings.prompts'),
            ],
            [
                'key'         => 'general',
                'title'       => 'General Config',
                'description' => 'Manage sign-up access, token grants and core platform behaviour.',
                'icon'        => 'bi-sliders',
                'route'       => route('admin.settings.general'),
            ],
            [
                'key'         => 'legal',
                'title'       => 'Legal Documents',
                'description' => 'Manage Terms of Service, Privacy Policy, Cookie Policy and consent records.',
                'icon'        => 'bi-file-earmark-text',
                'route'       => route('admin.legal.index'),
            ],
        ];
    }
}
