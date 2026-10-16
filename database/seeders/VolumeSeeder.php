<?php

namespace Database\Seeders;

use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Volume SINTÉTICO para benchmark (pnpm perf:check com PERF_VOLUME=N): N pacientes e ~N/1,5 responsáveis
 * fictícios na clínica B, mais N/2 agendamentos espalhados em ±30 dias. Só em banco *_test; nunca em produção.
 */
class VolumeSeeder extends Seeder
{
    private const FIRST = ['Ana', 'Bruno', 'Carla', 'Diego', 'Elisa', 'Fábio', 'Gabriela', 'Heitor', 'Íris', 'João', 'Karina', 'Lucas', 'Marta', 'Nelson', 'Olívia', 'Paulo', 'Quésia', 'Renato', 'Sofia', 'Tiago'];

    private const LAST = ['Silva', 'Souza', 'Oliveira', 'Santos', 'Pereira', 'Costa', 'Rodrigues', 'Almeida', 'Nascimento', 'Lima', 'Araújo', 'Fernandes', 'Carvalho', 'Gomes', 'Martins', 'Rocha'];

    private const PETS = ['Thor', 'Luna', 'Mel', 'Bob', 'Nina', 'Fred', 'Lola', 'Simba', 'Bidu', 'Pipoca', 'Amora', 'Zeus', 'Kiara', 'Pingo', 'Jade', 'Toby'];

    private const SPECIES = ['canine', 'canine', 'canine', 'feline', 'feline', 'bird', 'reptile', 'rodent', 'rabbit', 'fish'];

    public function run(TenantContext $context): void
    {
        $db = (string) config('database.connections.mysql.database');
        if (app()->environment('production') || ! str_ends_with($db, '_test')) {
            throw new RuntimeException("Volume sintético recusado no banco \"{$db}\".");
        }
        $total = max(1, (int) (getenv('PERF_VOLUME') ?: 5000)); // variável do processo (CLI), não da config
        $tenant = DatabaseSeeder::TENANT_B;
        mt_srand(2026);
        $nGuardians = (int) ceil($total / 1.5);

        $context->runForTenant($tenant, function () use ($total, $tenant, $nGuardians) {
            $now = now();
            $guardians = [];
            for ($i = 0; $i < $nGuardians; $i++) {
                $guardians[] = [
                    'id' => (string) Str::ulid(), 'tenant_id' => $tenant,
                    'name' => self::FIRST[mt_rand(0, 19)].' '.self::LAST[mt_rand(0, 15)].' '.self::LAST[mt_rand(0, 15)].' (fictício '.$i.')',
                    'person_type' => 'PF', 'phone' => '119'.str_pad((string) mt_rand(0, 99_999_999), 8, '0', STR_PAD_LEFT),
                    'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            foreach (array_chunk($guardians, 1000) as $chunk) {
                DB::table('guardians')->insert($chunk);
            }
            for ($start = 0; $start < $total; $start += 1000) {
                $patients = $links = [];
                for ($i = $start; $i < min($total, $start + 1000); $i++) {
                    $id = (string) Str::ulid();
                    $patients[] = [
                        'id' => $id, 'tenant_id' => $tenant, 'name' => self::PETS[mt_rand(0, 15)].' '.$i,
                        'species' => self::SPECIES[mt_rand(0, 9)], 'sex' => 'unknown', 'neutered' => 'unknown',
                        'status' => 'active', 'birth_date_estimated' => false, 'created_at' => $now, 'updated_at' => $now,
                    ];
                    $links[] = ['tenant_id' => $tenant, 'patient_id' => $id, 'guardian_id' => $guardians[$i % $nGuardians]['id'], 'relationship' => 'primary', 'created_at' => $now, 'updated_at' => $now];
                }
                DB::table('patients')->insert($patients);
                DB::table('guardian_patient')->insert($links);
                $this->appointments($patients, $tenant, $now);
            }
        });
        $this->command->info("Volume sintético: {$total} pacientes, {$nGuardians} responsáveis e ~".intdiv($total, 2).' agendamentos na clínica B.');
    }

    /**
     * Metade dos pacientes do lote ganha um agendamento entre 30 dias atrás e 30 dias à frente (08h–18h, UTC−3).
     *
     * @param  list<array<string, mixed>>  $patients
     */
    private function appointments(array $patients, string $tenant, Carbon $now): void
    {
        $typeId = DB::table('appointment_types')->where('tenant_id', $tenant)->orderBy('name')->value('id');
        $unitId = DB::table('units')->where('tenant_id', $tenant)->value('id');
        $rows = [];
        foreach ($patients as $i => $p) {
            if ($i % 2 === 1) {
                continue;
            }
            $start = $now->copy()->startOfDay()->addDays(mt_rand(-30, 30))->addHours(mt_rand(11, 20))->addMinutes(15 * mt_rand(0, 3));
            $rows[] = [
                'id' => (string) Str::ulid(), 'tenant_id' => $tenant, 'unit_id' => $unitId, 'patient_id' => $p['id'],
                'appointment_type_id' => $typeId, 'starts_at' => $start, 'ends_at' => $start->copy()->addMinutes(30),
                'status' => $start->isPast() ? 'completed' : 'scheduled', 'is_walk_in' => false, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        DB::table('appointments')->insert($rows);
    }
}
