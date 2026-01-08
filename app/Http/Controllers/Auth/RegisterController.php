<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Rules\Recaptcha;
use App\Services\MembershipService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(private MembershipService $membershipService)
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        if ($this->recaptchaEnabled()) {
            $rules['g-recaptcha-response'] = ['required', new Recaptcha()];
        }

        return Validator::make($data, $rules);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $this->assignDefaultInstitution($user);

        return $user;
    }

    /**
     * Handle a registration request for the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $this->validator($request->all())->validate();

        event(new Registered($user = $this->create($request->all())));

        if ($response = $this->registered($request, $user)) {
            return $response;
        }

        return $request->wantsJson()
                    ? new JsonResponse(['message' => 'Registration successful. Please check your email to verify your account.'], 201)
                    : redirect($this->redirectPath())->with('status', 'Registration successful! Please check your email to verify your account.');
    }

    /**
     * The user has been registered.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function registered(Request $request, $user)
    {
        // Don't auto-login the user until they verify their email
        return null;
    }

    /**
     * Determine if reCAPTCHA validation should run.
     */
    private function recaptchaEnabled(): bool
    {
        return (bool) (
            config('services.recaptcha.enabled')
            && config('services.recaptcha.site_key')
            && config('services.recaptcha.secret_key')
        );
    }

    protected function assignDefaultInstitution(User $user): void
    {
        $institution = $this->resolveDefaultInstitution();

        if (! $institution) {
            return;
        }

        $this->membershipService->assign(
            $user,
            $institution,
            UserRole::REGULAR,
            true,
            null
        );
    }

    protected function resolveDefaultInstitution(): ?Institution
    {
        $configuredId = config('institutions.default_id') ?? config('institution.default_id');

        if ($configuredId) {
            $institution = Institution::find($configuredId);

            if ($institution && $institution->is_active) {
                return $institution;
            }

            Log::warning('Configured DEFAULT_INSTITUTION_ID not found or inactive during registration.', [
                'institution_id' => $configuredId,
            ]);
        }

        return Institution::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }
}
