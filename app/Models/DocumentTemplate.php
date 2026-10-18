<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Modelo de documento da clínica (texto com variáveis da whitelist). Editar incrementa a versão;
 * documentos já emitidos guardam o próprio texto e PDF e não mudam.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $category
 * @property string $name
 * @property string $title
 * @property string $body
 * @property int $version
 * @property bool $active
 * @property string|null $source_name
 * @property string|null $source_mime
 * @property string|null $source_path
 * @property string|null $source_sha256
 * @property int|null $source_bytes
 * @property Carbon|null $imported_at
 */
class DocumentTemplate extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    public const CATEGORIES = [
        'prescription' => 'Receita',
        'certificate' => 'Atestado',
        'guidance' => 'Orientações',
        'referral' => 'Encaminhamento',
        'consent' => 'Termo de consentimento',
        'other' => 'Outro',
    ];

    /** Categorias que só o médico-veterinário emite (com CRMV). */
    public const SIGNED_BY_VET = ['prescription', 'certificate', 'referral'];

    protected $fillable = ['category', 'name', 'title', 'body', 'version', 'active', 'updated_by'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'version' => 'integer', 'imported_at' => 'datetime', 'source_bytes' => 'integer'];
    }
}
