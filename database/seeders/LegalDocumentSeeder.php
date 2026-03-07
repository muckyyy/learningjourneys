<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            [
                'type'  => LegalDocument::TYPE_TERMS,
                'title' => 'Terms of Service',
                'body'  => $this->termsOfService(),
            ]
        ];

        foreach ($documents as $data) {
            $existing = LegalDocument::ofType($data['type'])->first();

            if (! $existing) {
                $doc = LegalDocument::create([
                    'type'        => $data['type'],
                    'title'       => $data['title'],
                    'body'        => $data['body'],
                    'version'     => 1,
                    'is_required' => true,
                    'is_active'   => true,
                    'published_at' => now(),
                ]);

                $this->command->info("Created: {$doc->title} v{$doc->version}");
            } else {
                $this->command->warn("Skipped: {$data['title']} (already exists)");
            }
        }
    }

    private function termsOfService(): string
    {
        return <<<'HTML'
<h2>1. Acceptance of Terms</h2>
<p>By accessing and using Learning Journeys ("the Platform"), you accept and agree to be bound by the terms and conditions of this agreement. If you do not agree to abide by these terms, please do not use this service.</p>

<h2>2. Description of Service</h2>
<p>Learning Journeys provides an online learning platform that offers curated educational journeys, AI-powered interactions, voice-enabled learning experiences, and related services. The Platform may include features such as token-based access, institutional memberships, and certificate issuance.</p>

<h2>3. User Accounts</h2>
<p>To use certain features, you must register for an account. You agree to:</p>
<ul>
    <li>Provide accurate, current, and complete information during registration.</li>
    <li>Maintain the security of your password and account.</li>
    <li>Accept responsibility for all activities under your account.</li>
    <li>Notify us immediately of any unauthorized use of your account.</li>
</ul>

<h2>4. Acceptable Use</h2>
<p>You agree not to:</p>
<ul>
    <li>Use the Platform for any unlawful purpose or in violation of any applicable laws.</li>
    <li>Attempt to gain unauthorized access to the Platform or its related systems.</li>
    <li>Interfere with or disrupt the integrity or performance of the Platform.</li>
    <li>Share your account credentials with others.</li>
    <li>Use automated tools to access the Platform without prior written consent.</li>
</ul>

<h2>5. Tokens and Payments</h2>
<p>Certain features may require tokens. Tokens are non-refundable and non-transferable unless otherwise stated. We reserve the right to modify token pricing and packages at any time.</p>

<h2>6. Intellectual Property</h2>
<p>All content on the Platform, including but not limited to text, graphics, logos, and software, is the property of Learning Journeys or its content suppliers and is protected by intellectual property laws.</p>

<h2>7. Limitation of Liability</h2>
<p>The Platform is provided "as is" without warranties of any kind. We shall not be liable for any indirect, incidental, special, or consequential damages arising from use of the Platform.</p>

<h2>8. Changes to Terms</h2>
<p>We reserve the right to modify these terms at any time. Users will be notified of significant changes and may be required to accept updated terms to continue using the Platform.</p>

<h2>9. Contact</h2>
<p>If you have questions about these Terms of Service, please contact us through the Platform's support channels.</p>
HTML;
    }

    private function privacyPolicy(): string
    {
        return <<<'HTML'
<h2>1. Information We Collect</h2>
<p>We collect information you provide directly, including:</p>
<ul>
    <li><strong>Account information:</strong> Name, email address, and password when you register.</li>
    <li><strong>Profile information:</strong> Additional details you provide in your profile.</li>
    <li><strong>Usage data:</strong> Journey attempts, responses, audio recordings, and interaction data.</li>
    <li><strong>Technical data:</strong> IP address, browser type, device information, and access times.</li>
</ul>

<h2>2. How We Use Your Information</h2>
<p>We use collected information to:</p>
<ul>
    <li>Provide, maintain, and improve the Platform.</li>
    <li>Process AI-powered interactions and provide personalized learning experiences.</li>
    <li>Transcribe audio recordings for learning purposes.</li>
    <li>Send you service-related communications.</li>
    <li>Analyze usage patterns to improve our services.</li>
    <li>Issue certificates and track learning progress.</li>
</ul>

<h2>3. Data Sharing</h2>
<p>We do not sell your personal information. We may share data with:</p>
<ul>
    <li><strong>Service providers:</strong> Third-party services that help us operate the Platform (e.g., OpenAI for AI features, cloud hosting providers).</li>
    <li><strong>Institutions:</strong> If you are part of an institution, administrators may access your learning progress and reports.</li>
    <li><strong>Legal requirements:</strong> When required by law or to protect our rights.</li>
</ul>

<h2>4. Data Retention</h2>
<p>We retain your data for as long as your account is active or as needed to provide services. You may request deletion of your account and associated data by contacting support.</p>

<h2>5. Data Security</h2>
<p>We implement appropriate technical and organizational measures to protect your personal data. However, no method of transmission over the Internet is 100% secure.</p>

<h2>6. Your Rights</h2>
<p>Depending on your location, you may have the right to:</p>
<ul>
    <li>Access the personal data we hold about you.</li>
    <li>Request correction of inaccurate data.</li>
    <li>Request deletion of your data.</li>
    <li>Object to or restrict certain processing.</li>
    <li>Data portability.</li>
</ul>

<h2>7. Third-Party Services</h2>
<p>The Platform integrates with third-party services including OpenAI for AI-powered features and social login providers (Google, Facebook, LinkedIn, Apple, Microsoft). Each service has its own privacy policy that governs data processing.</p>

<h2>8. Changes to This Policy</h2>
<p>We may update this Privacy Policy from time to time. We will notify you of significant changes and may require you to acknowledge the updated policy.</p>

<h2>9. Contact</h2>
<p>For privacy-related inquiries, please contact us through the Platform's support channels.</p>
HTML;
    }

    private function cookiePolicy(): string
    {
        return <<<'HTML'
<h2>1. What Are Cookies</h2>
<p>Cookies are small text files that are placed on your device when you visit our Platform. They help us provide you with a better experience by remembering your preferences and understanding how you use our services.</p>

<h2>2. Types of Cookies We Use</h2>

<h3>Essential Cookies</h3>
<p>These cookies are necessary for the Platform to function properly. They include:</p>
<ul>
    <li><strong>Session cookies:</strong> Maintain your login state and session data.</li>
    <li><strong>CSRF tokens:</strong> Protect against cross-site request forgery attacks.</li>
    <li><strong>Preferences:</strong> Remember your active institution and settings.</li>
</ul>

<h3>Analytics Cookies</h3>
<p>We use analytics cookies to understand how visitors interact with the Platform. This helps us improve our service. These cookies collect information in an aggregated form.</p>

<h3>Functional Cookies</h3>
<p>These cookies enable enhanced functionality and personalization, such as:</p>
<ul>
    <li>Remembering your language preferences.</li>
    <li>Storing your learning progress locally.</li>
    <li>WebSocket connection management for real-time features.</li>
</ul>

<h2>3. Managing Cookies</h2>
<p>Most web browsers allow you to control cookies through their settings. You can:</p>
<ul>
    <li>Block all cookies.</li>
    <li>Delete existing cookies.</li>
    <li>Allow cookies from specific sites only.</li>
</ul>
<p>Please note that disabling essential cookies may prevent the Platform from functioning correctly.</p>

<h2>4. Third-Party Cookies</h2>
<p>Some third-party services used by the Platform may set their own cookies, including:</p>
<ul>
    <li>Google reCAPTCHA (for spam protection during registration).</li>
    <li>Social login providers (when using OAuth sign-in).</li>
</ul>

<h2>5. Changes to This Policy</h2>
<p>We may update this Cookie Policy to reflect changes in our practices or for regulatory reasons. We will notify you of significant changes.</p>

<h2>6. Contact</h2>
<p>If you have questions about our use of cookies, please contact us through the Platform's support channels.</p>
HTML;
    }
}
