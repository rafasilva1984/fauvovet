<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Encounter;
use App\Support\SearchText;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** Busca clínica em texto livre (CLAUDE.md §3.3): normalização, operadores, evidência, índice em dia, permissões e isolamento. */
final class ClinicalSearchTest extends TestCase
{
    private const THOR = '01K7FV0000000000000PTA0001'; // alerta "Alergia a penicilina"; dermatite suspeita → confirmada; "prurido"

    private const MIA = '01K7FV0000000000000PTA0002';

    private const JABU = '01K7FV0000000000000PTB0001'; // clínica B: "Recusa alimentar há 5 dias"

    private function sys(\Closure $fn): mixed
    {
        return app(TenantContext::class)->runAsSystem($fn);
    }

    public function test_normalizacao_plural_acento_e_operadores(): void
    {
        $this->assertSame(['lesao', 'renal', 'cao', 'dermatite'], SearchText::terms('Lesões renais em cães com dermatites'));
        $q = SearchText::parse('"recusa alimentar" OU "não se alimenta" -felino NÃO gato de');
        $this->assertSame([['terms' => [], 'phrases' => ['recusa alimentar']], ['terms' => [], 'phrases' => ['nao se alimenta']]], $q['groups']);
        $this->assertSame(['felino', 'gato'], $q['exclude']);
        $this->assertSame(['de'], $q['ignored']);
        $this->assertSame('alergia a <mark>Penicilina</mark> &lt;b&gt;', SearchText::snippet('alergia a Penicilina <b>', ['penicilina'], []));
    }

    public function test_termo_com_evidencia_sem_acento_e_plural(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $res = $this->get('/busca?q=dermatites+atopicas')->assertOk()
            ->assertSee('/pacientes/'.self::THOR, false)->assertDontSee('/pacientes/'.self::MIA, false)
            ->assertSee('<mark>Dermatite</mark> <mark>atópica</mark>', false)->assertSee('Diagnóstico')
            ->assertSee('/atendimentos/01K7FV0000000000000ENA0002', false);
        $this->assertStringContainsString('1 paciente', (string) $res->getContent());
        // Situação do diagnóstico separa suspeitas de confirmados (exemplo 3).
        $this->get('/busca?q=dermatite&situacao=suspected')->assertSee('/atendimentos/01K7FV0000000000000ENA0001', false)->assertDontSee('/atendimentos/01K7FV0000000000000ENA0002', false);
        $this->get('/busca?q=dermatite&especie=feline')->assertSee('Nada encontrado no prontuário');
        $this->get('/busca?q=dermatite&periodo=30')->assertDontSee('/atendimentos/01K7FV0000000000000ENA0001', false); // 120 dias atrás
    }

    public function test_cruza_campos_ou_e_nao(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        // Exemplo 5: alergia (alerta) + termo da queixa em outro registro do mesmo paciente.
        $this->get('/busca?q=penicilina+prurido')->assertSee('/pacientes/'.self::THOR, false)->assertSee('<mark>penicilina</mark>', false)->assertSee('<mark>prurido</mark>', false);
        $this->get('/busca?q=penicilina+inexistentepalavra')->assertSee('Nada encontrado no prontuário');
        $this->get('/busca?q=inexistentepalavra+OU+penicilina')->assertSee('/pacientes/'.self::THOR, false);
        $this->get('/busca?q=dermatite+N%C3%83O+prurido')->assertSee('Nada encontrado no prontuário');
        $this->get('/busca?q=dermatite+-felino')->assertSee('/pacientes/'.self::THOR, false)->assertSee('sem “felino”');
        $this->get('/busca?q=%22reduziu+com+o+tratamento%22')->assertSee('/pacientes/'.self::THOR, false);
        $this->get('/busca?q=%22tratamento+com+reduziu%22')->assertSee('Nada encontrado no prontuário');
        $this->get('/busca?q=de')->assertSee('Digite ao menos uma palavra');
    }

    public function test_indice_atualizado_ao_finalizar_e_rascunho_fora(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->get('/busca?q=giardia')->assertSee('Nada encontrado');
        $this->post('/pacientes/'.self::MIA.'/atendimentos');
        $id = $this->sys(fn () => Encounter::query()->where('patient_id', self::MIA)->where('status', 'draft')->value('id'));
        $this->put("/atendimentos/{$id}", ['chief_complaint' => 'Diarreia com suspeita de giárdia (fictício).', 'diagnoses' => []])->assertSessionHasNoErrors();
        $this->get('/busca?q=giardia')->assertSee('Nada encontrado'); // rascunho não entra
        $this->put("/atendimentos/{$id}", ['chief_complaint' => 'Diarreia com suspeita de giárdia (fictício).', 'diagnoses' => [], 'finalize' => '1', 'confirm' => '1'])->assertSessionHasNoErrors();
        $this->get('/busca?q=giardia')->assertSee('/pacientes/'.self::MIA, false)->assertSee('Queixa principal')->assertSee('<mark>giárdia</mark>', false);
        $this->post("/atendimentos/{$id}/adendos", ['body' => 'Coproparasitológico positivo para Giardia (fictício).'])->assertSessionHasNoErrors();
        $this->get('/busca?q=coproparasitologico')->assertSee('Adendo');

        $event = $this->sys(fn () => AuditEvent::query()->where('action', 'clinical.search')->latest('id')->first());
        $this->assertSame(1, $event?->metadata['patients'] ?? null);
        $this->assertStringNotContainsString('giard', (string) json_encode($this->sys(fn () => AuditEvent::query()->where('action', 'clinical.search')->pluck('metadata'))));
    }

    public function test_permissoes_e_reconstrucao(): void
    {
        foreach (['carla', 'davi'] as $who) {
            $this->loginInto($who, Seed::TENANT_A);
            $this->get('/busca?q=dermatite')->assertForbidden();
            $this->post('/sair');
        }
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->get('/pacientes?q=dermatite')->assertSee('no prontuário'); // atalho a partir da busca de cadastro
        $this->post('/busca/reindexar')->assertForbidden();
        $this->post('/sair');
        $this->loginInto('ana', Seed::TENANT_A);
        $this->post('/busca/reindexar')->assertRedirect('/busca')->assertSessionHasNoErrors();
        $this->get('/busca')->assertSee('Reconstruir índice')->assertSee('trechos');
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_busca_texto_livre(): void
    {
        $this->loginInto('diana', Seed::TENANT_B);
        $this->get('/busca?q=%22recusa+alimentar%22')->assertSee('/pacientes/'.self::JABU, false);
        $this->get('/busca?q=dermatite')->assertSee('Nada encontrado')->assertDontSee(self::THOR, false);
        $this->get('/busca?q=penicilina')->assertSee('Nada encontrado');
        $this->post('/sair');
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->get('/busca?q=recusa')->assertSee('Nada encontrado')->assertDontSee(self::JABU, false);
        // Índice e termos gravados só com o tenant de cada clínica.
        $this->assertSame(0, $this->sys(fn () => DB::table('clinical_search_documents')->where('tenant_id', Seed::TENANT_A)->where('patient_id', self::JABU)->count()));
        $this->assertSame(0, $this->sys(fn () => DB::table('clinical_search_terms as t')->join('clinical_search_documents as d', 'd.id', '=', 't.document_id')->whereColumn('t.tenant_id', '!=', 'd.tenant_id')->count()));
    }
}
