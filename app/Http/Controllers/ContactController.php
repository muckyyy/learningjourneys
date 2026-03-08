<?php

namespace App\Http\Controllers;

use App\Mail\ContactUsMail;
use App\Models\User;
use App\Rules\Recaptcha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    public function send(Request $request)
    {
        $rules = [
            'contact_name' => 'required|string|max:100',
            'contact_email' => 'required|email:rfc,dns|max:255',
            'contact_message' => 'required|string|max:5000',
        ];

        if (config('services.recaptcha.enabled')) {
            $rules['g-recaptcha-response'] = ['required', new Recaptcha];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect(url()->previous() . '#contact')
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $recipient = User::findOrFail(1);

            Mail::to($recipient->email)->send(new ContactUsMail(
                $request->input('contact_name'),
                $request->input('contact_email'),
                $request->input('contact_message')
            ));

            return redirect(url()->previous() . '#contact')->with('contact_success', 'Your message has been sent. We\'ll get back to you soon!');
        } catch (\Exception $e) {
            Log::error('Contact form failed: ' . $e->getMessage());
            return redirect(url()->previous() . '#contact')
                ->withInput()
                ->with('contact_error', 'Sorry, something went wrong. Please try again later.');
        }
    }
}
