<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Responsável (tutor) por um ou mais pacientes, dentro de uma clínica.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $person_type
 * @property string|null $document_number
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $phone_alt
 * @property string|null $postal_code
 * @property string|null $address_line
 * @property string|null $city
 * @property string|null $state
 * @property string|null $notes
 * @property string $status
 * @property-read Collection<int, Patient> $patients
 */
class Guardian extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['name', 'person_type', 'document_number', 'email', 'phone', 'phone_alt', 'postal_code', 'address_line', 'city', 'state', 'notes', 'status', 'created_by'];

    /** @return BelongsToMany<Patient, $this> */
    public function patients(): BelongsToMany
    {
        return $this->belongsToMany(Patient::class, 'guardian_patient')
            ->withPivot(['tenant_id', 'relationship'])->withTimestamps();
    }

    public static function formatPhone(?string $digits): string
    {
        if ($digits === null || $digits === '') {
            return '';
        }

        return match (strlen($digits)) {
            11 => sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7)),
            10 => sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6)),
            default => $digits,
        };
    }
}
