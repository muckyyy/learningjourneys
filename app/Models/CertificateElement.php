<?php

namespace App\Models;

use App\Enums\CertificateElementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertificateElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_id',
        'name',
        'type',
        'text_content',
        'variable_key',
        'asset_path',
        'sorting',
        'position_x',
        'position_y',
        'width',
        'height',
        'fpdf_settings',
        'meta',
    ];

    protected $casts = [
        'sorting' => 'integer',
        'position_x' => 'float',
        'position_y' => 'float',
        'width' => 'float',
        'height' => 'float',
        'fpdf_settings' => 'array',
        'meta' => 'array',
    ];

    public function certificate()
    {
        return $this->belongsTo(Certificate::class);
    }

    public function isText(): bool
    {
        return $this->type === CertificateElementType::TEXT;
    }

    public function isVariable(): bool
    {
        return $this->type === CertificateElementType::VARIABLE;
    }

    public function isImage(): bool
    {
        return $this->type === CertificateElementType::IMAGE;
    }
}
