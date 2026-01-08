<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400"></a></p>

<p align="center">
<a href="https://travis-ci.org/laravel/framework"><img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 1500 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Cubet Techno Labs](https://cubettech.com)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[Many](https://www.many.co.uk)**
- **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
- **[DevSquad](https://devsquad.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[OP.GG](https://op.gg)**
- **[WebReinvent](https://webreinvent.com/?utm_source=laravel&utm_medium=github&utm_campaign=patreon-sponsors)**
- **[Lendio](https://lendio.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Certificate Module

### Schema overview
- `certificates`: master definitions with `name`, `enabled`, and optional `validity_days` (in days) used to compute expiry windows.
- `certificate_institution`: pivot that whitelists which institutions can issue a given certificate (rows are automatically enforced via foreign keys and unique constraints).
- `certificate_elements`: designer-friendly configuration for each visual element with ordering, coordinates, dimensions, optional text/variable payloads, and raw `fpdf_settings`.
- `certificate_issues`: immutable history of every issuance including recipient, issuing institution, QR payload, optional QR image path, and serialized snapshot payloads for rendering.

Run `php artisan migrate` after pulling these changes to create the new tables.

### Domain layer
- `App\Models\Certificate`, `CertificateElement`, and `CertificateIssue` expose the relationships and casting required to compose layouts or list issuance history.
- `App\Enums\CertificateElementType` and `App\Enums\CertificateVariable` centralize the available element types and dynamic placeholders (profile data, collection name, journey stats, QR metadata, etc.).
- `App\Services\CertificateIssueService` orchestrates issuance, enforcing that certificates are enabled and that the selected institution is authorized before persisting a `CertificateIssue` record.

#### Issuance example
```php
use App\Models\Certificate;
use App\Models\User;
use App\Services\CertificateIssueService;

$certificate = Certificate::enabled()->firstOrFail();
$recipient = User::findOrFail($userId);

$issue = app(CertificateIssueService::class)->issue(
	certificate: $certificate,
	recipient: $recipient,
	variableOverrides: [
		'variables' => [
			\App\Enums\CertificateVariable::COLLECTION_NAME => 'STEM Explorers',
			\App\Enums\CertificateVariable::JOURNEY_COUNT => 12,
		],
	],
);
```

The returned `CertificateIssue` exposes `issued_at`, `expires_at`, `qr_code`, and the resolved payload ready for downstream PDF/FPDF rendering.
