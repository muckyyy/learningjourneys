<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\LegalConsent;
use App\Models\LegalDocument;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Rules\Recaptcha;
use App\Services\ReferralService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Setting;
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
    public function __construct(private ReferralService $referralService)
    {
        $this->middleware('guest');
        $this->middleware(function ($request, $next) {
            $enabled = (bool) (int) Setting::get('site.signup_enabled', '0');
            if (! $enabled) {
                abort(404);
            }
            return $next($request);
        });
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

        // Require consent for each active required legal document
        foreach (LegalDocument::currentRequired() as $doc) {
            $rules["consent_{$doc->id}"] = ['required', 'accepted'];
        }

        return Validator::make($data, $rules, [
            'consent_*.required' => 'You must accept this document to register.',
            'consent_*.accepted' => 'You must accept this document to register.',
        ]);
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

        $user->assignRole(UserRole::REGULAR);

        return $user;
    }

    /**
     * Handle a registration request for the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function showRegistrationForm()
    {
        // Persist referral code from URL into session so it survives validation redirects
        if (request()->has('ref')) {
            session(['referral_code' => request('ref')]);
        }

        return view('auth.register');
    }

    public function register(Request $request)
    {
        $this->validator($request->all())->validate();

        event(new Registered($user = $this->create($request->all())));

        // Record legal consent
        LegalConsent::recordAllRequired($user, $request->ip(), $request->userAgent());

        // Link referral
        $this->linkReferral($user);

        if ($response = $this->registered($request, $user)) {
            return $response;
        }

        return $request->wantsJson()
                    ? new JsonResponse(['message' => 'Registration successful. Please check your email to verify your account.'], 201)
                    : redirect(route('login'))->with('status', 'Registration successful! We\'ve sent a verification link to your email address. Please check your inbox and click the link to activate your account.');
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

    /**
     * Link the new user to the referrer if a referral code was provided.
     */
    private function linkReferral(User $user): void
    {
        $code = session()->pull('referral_code');

        if (! $code) {
            return;
        }

        $referrer = User::where('referral_id', $code)->first();

        if (! $referrer) {
            return;
        }

        $user->update(['referred_by' => $referrer->id]);
        $this->referralService->recordReferral($referrer, $user);
    }

}
