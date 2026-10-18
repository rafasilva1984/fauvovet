<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ClinicalSearchSynonym;
use App\Models\SavedSearch;
use App\Support\SearchText;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** Fecha a Fase 2 da busca: sinônimos da clínica (§3.3 ex. 6) e pesquisas salvas — permissões e isolamento. */
final class SearchSynonymAndSavedTest extends TestCase
{
    private const THOR = '01K7FV0000000000000PTA0001'; // queixa com "prurido" e "coceira"

    private const JABU = '01K7FV0000000000000PTB0001'; // clínica B: "Recusa alimentar há 5 dias"

    private function sys(\Closure $fn): mixed
    {
        return app(TenantContext::class)->runAsSystem($fn);
    }

    public function test_variantes_de_sinonimo(): void
    {
        $this->assertSame(['p:recusa alimentar', 'p:nao se alimenta', 't:anorexia'], SearchText::synonymVariants("Recusa alimentar\nnão se alimenta; anorexia"));
        $this->assertSame(['t:prurido'], SearchText::synonymVariants("prurido\n de "));
    }

    public function test_sem_sinonimo_pronto_e_grupo_da_clinica_expande_a_busca(): void
    {
        $this->loginInto('diana', Seed::TENANT_B);
        // Nada vem pronto: "anorexia" não acha "recusa alimentar".
        $this->get('/busca?q=anorexia')->assertSee('Nada encontrado');
        $this->get('/configuracoes/sinonimos')->assertOk()->assertSee('Nenhum sinônimo cadastrado');
        $this->post('/configuracoes/sinonimos', ['terms' => 'só uma'])->assertSessionHasErrors('terms');
        $this->post('/configuracoes/sinonimos', ['terms' => "recusa alimentar\nnão se alimenta\nanorexia"])->assertSessionHasNoErrors();
        $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', 'search_synonym.created')->exists()));

        $this->get('/busca?q=anorexia')->assertSee('/pacientes/'.self::JABU, false)->assertSee('<mark>Recusa alimentar</mark>', false)
            ->assertSee('Sinônimos da clínica usados');
        // Expressão de várias palavras digitada sem aspas também é reconhecida como o grupo.
        $this->get('/busca?q=nao+se+alimenta')->assertSee('/pacientes/'.self::JABU, false);
        // Exclusão também usa o grupo.
        $this->get('/busca?q=renal')->assertSee('/pacientes/'.self::JABU, false);
        $this->get('/busca?q=renal+N%C3%83O+anorexia')->assertDontSee('/pacientes/'.self::JABU, false);

        $g = $this->sys(fn () => ClinicalSearchSynonym::query()->firstOrFail());
        $this->put("/configuracoes/sinonimos/{$g->id}", ["terms_{$g->id}" => "anorexia\ninapetência"])->assertSessionHasNoErrors();
        $this->get('/busca?q=anorexia')->assertSee('Nada encontrado');
        $this->delete("/configuracoes/sinonimos/{$g->id}")->assertSessionHasNoErrors();
        $this->assertSame(0, $this->sys(fn () => ClinicalSearchSynonym::query()->count()));
    }

    public function test_pesquisas_salvas_privadas_e_compartilhadas(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->post('/busca/salvas?q=dermatite&especie=canine', ['name' => ''])->assertSessionHasErrors('name');
        $this->post('/busca/salvas?q=dermatite&especie=canine', ['name' => 'Dermatites caninas'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/busca/salvas?q=penicilina', ['name' => 'Alergia penicilina (equipe)', 'shared' => '1'])->assertSessionHasNoErrors();
        $mine = $this->sys(fn () => SavedSearch::query()->where('name', 'Dermatites caninas')->firstOrFail());
        $this->assertEquals(['q' => 'dermatite', 'especie' => 'canine'], $mine->params);
        $this->get('/busca')->assertSee('Dermatites caninas')->assertSee('Alergia penicilina (equipe)');
        $this->post('/sair');

        $this->loginInto('eva', Seed::TENANT_A); // outra veterinária: vê só a compartilhada
        $this->get('/busca')->assertDontSee('Dermatites caninas')->assertSee('Alergia penicilina (equipe)');
        $this->delete("/busca/salvas/{$mine->id}")->assertForbidden();
        $this->post('/sair');

        $this->loginInto('ana', Seed::TENANT_A); // titular pode remover qualquer uma
        $this->delete("/busca/salvas/{$mine->id}")->assertRedirect('/busca');
        $this->assertNull($this->sys(fn () => SavedSearch::query()->find($mine->id)));
    }

    public function test_permissoes(): void
    {
        $this->loginInto('carla', Seed::TENANT_A);
        $this->get('/configuracoes/sinonimos')->assertForbidden();
        $this->post('/busca/salvas?q=x', ['name' => 'x'])->assertForbidden();
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_sinonimos_e_pesquisas_salvas(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->post('/configuracoes/sinonimos', ['terms' => "anorexia\nrecusa alimentar"]);
        $this->post('/busca/salvas?q=dermatite', ['name' => 'Salva da A', 'shared' => '1']);
        $g = $this->sys(fn () => ClinicalSearchSynonym::query()->firstOrFail());
        $s = $this->sys(fn () => SavedSearch::query()->firstOrFail());
        $this->post('/sair');

        $this->loginInto('diana', Seed::TENANT_B);
        $this->get('/busca?q=anorexia')->assertSee('Nada encontrado'); // sinônimo da A não vale na B
        $this->get('/busca')->assertDontSee('Salva da A');
        $this->put("/configuracoes/sinonimos/{$g->id}", ["terms_{$g->id}" => "a1\nb2"])->assertNotFound();
        $this->delete("/configuracoes/sinonimos/{$g->id}")->assertNotFound();
        $this->delete("/busca/salvas/{$s->id}")->assertNotFound();
    }
}
