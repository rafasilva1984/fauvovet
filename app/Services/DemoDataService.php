<?php

namespace App\Services;

use App\Models\AppointmentType;
use App\Models\Attachment;
use App\Models\Membership;
use App\Models\Unit;
use App\Models\Vaccine;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Dados de DEMONSTRAÇÃO 100% fictícios para a clínica ativa: responsáveis, pacientes de várias espécies, atendimentos
 * finalizados em 3 anos (com diagnósticos suspeitos/confirmados/descartados), pesos, alertas, vacinas (atrasadas, a
 * vencer e em dia), agenda da semana e casos científicos — para visualizar busca avançada, vacinação e casos.
 *
 * Todo registro criado aqui tem ID com o prefixo {@see self::PREFIX} (ULID válido com data "1970", que nunca é gerado
 * pelo sistema). A remoção apaga só pacientes/responsáveis/casos com esse prefixo e o que estiver ligado aos pacientes
 * demo (inclusive o que a equipe registrou neles depois). Ligado apenas com FAUVOVET_DEMO_DATA=true (ADR-016).
 */
final class DemoDataService
{
    public const PREFIX = '00DEM0';

    /**
     * Versão do conjunto de demonstração; SUBIR a cada entrega que amplia os dados. Fica gravada no 1º responsável demo
     * para a tela avisar "demonstração desatualizada" e oferecer "Atualizar" (remover + carregar).
     * 1 = 18/10 (base) · 2 = 19/10 (busca em texto livre) · 3 = 19/10 (anexos com texto + correções da busca).
     */
    public const VERSION = 3;

    /** Responsáveis: [nome, celular, cidade/UF]. */
    private const GUARDIANS = [
        ['Beatriz Andrade (demo)', '11900000101', 'São Paulo', 'SP'],
        ['Carlos Menezes (demo)', '11900000102', 'São Paulo', 'SP'],
        ['Daniela Freitas (demo)', '11900000103', 'Guarulhos', 'SP'],
        ['Eduardo Ramos (demo)', '11900000104', 'Osasco', 'SP'],
        ['Fernanda Queiroz (demo)', '11900000105', 'São Paulo', 'SP'],
        ['Gustavo Tavares (demo)', '11900000106', 'Santo André', 'SP'],
        ['Helena Vasconcelos (demo)', '11900000107', 'São Paulo', 'SP'],
        ['Igor Batista (demo)', '11900000108', 'Barueri', 'SP'],
        ['Juliana Moreira (demo)', '11900000109', 'São Paulo', 'SP'],
        ['Kátia Nogueira (demo)', '11900000110', 'Campinas', 'SP'],
    ];

    /**
     * Pacientes: [nome, espécie, detalhe, raça, sexo, castrado, nascimento (Y-m-d ou null), responsável (índice)].
     * Nomes sem "(demo)" para a tela ficar natural; o responsável identifica a demonstração.
     */
    private const PATIENTS = [
        ['Bento', 'canine', null, 'Golden Retriever', 'male', 'yes', '2018-04-12', 0],
        ['Luna', 'canine', null, 'Shih Tzu', 'female', 'yes', '2020-09-03', 0],
        ['Pipoca', 'canine', null, 'SRD', 'female', 'no', '2023-01-20', 1],
        ['Zeus', 'canine', null, 'Bulldog Francês', 'male', 'no', '2021-06-15', 1],
        ['Amora', 'canine', null, 'Labrador', 'female', 'yes', '2016-11-02', 2],
        ['Fred', 'canine', null, 'Lhasa Apso', 'male', 'yes', '2017-02-28', 3],
        ['Mel', 'canine', null, 'SRD', 'female', 'yes', '2015-07-07', 4],
        ['Toby', 'canine', null, 'Poodle', 'male', 'yes', '2012-03-18', 5],
        ['Kiara', 'canine', null, 'Pastor Alemão', 'female', 'no', '2019-12-01', 6],
        ['Bidu', 'canine', null, 'Beagle', 'male', 'unknown', '2024-02-10', 7],
        ['Simba', 'feline', null, 'SRD', 'male', 'yes', '2011-05-05', 2],
        ['Nina', 'feline', null, 'Persa', 'female', 'yes', '2013-10-10', 4],
        ['Frajola', 'feline', null, 'SRD', 'male', 'yes', '2010-08-21', 5],
        ['Mimi', 'feline', null, 'Siamês', 'female', 'yes', '2014-01-15', 8],
        ['Tom', 'feline', null, 'Maine Coon', 'male', 'yes', '2019-03-30', 8],
        ['Jade', 'feline', null, 'SRD', 'female', 'no', '2025-03-01', 9],
        ['Pingo', 'bird', 'Calopsita', null, 'male', 'unknown', '2021-01-01', 3],
        ['Sol', 'bird', 'Papagaio-verdadeiro', null, 'unknown', 'unknown', null, 6],
        ['Tuga', 'reptile', 'Jabuti-piranga', null, 'female', 'no', '2008-01-01', 7],
        ['Floco', 'rabbit', null, 'Mini Lop', 'male', 'yes', '2022-05-10', 9],
        ['Paçoca', 'rodent', 'Hamster sírio', null, 'female', 'no', '2025-06-01', 1],
        ['Zara', 'ferret', null, null, 'female', 'yes', '2022-09-09', 0],
    ];

    /**
     * Atendimentos finalizados: [paciente, dias atrás, peso, queixa, exame físico, conduta, [[diagnóstico, código, situação]]].
     * Datas espalhadas em ~3 anos para o filtro "ano de atendimento".
     */
    private const ENCOUNTERS = [
        // Dermatologia canina: suspeita → confirmação, e um caso descartado (série científica de exemplo).
        [0, 840, '31.200', 'Coceira nas patas e lambedura constante há 1 mês.', 'Eritema interdigital, otite bilateral leve.', 'Citologia de pele; banho terapêutico; retorno em 30 dias.', [['Dermatite atópica', 'L20', 'suspected']]],
        [0, 800, '31.000', 'Retorno: prurido persiste mesmo com controle de pulgas.', 'Lesões em face e axilas.', 'Iniciado imunomodulador; dieta hipoalergênica de eliminação.', [['Dermatite atópica', 'L20', 'confirmed']]],
        [0, 420, '30.400', 'Crise de coceira na primavera.', 'Eritema em abdômen; pústulas.', 'Antibiótico (clindamicina) por 21 dias para piodermite secundária; ajuste do imunomodulador.', [['Dermatite atópica', 'L20', 'confirmed'], ['Otite externa', 'H60', 'confirmed']]],
        [1, 600, '6.800', 'Coça orelhas e balança a cabeça.', 'Secreção ceruminosa em conduto direito.', 'Limpeza otológica e medicação tópica por 10 dias.', [['Otite externa', 'H60', 'confirmed'], ['Dermatite atópica', 'L20', 'suspected']]],
        [1, 150, '6.950', 'Lambedura das patas e vermelhidão.', 'Pododermatite leve.', 'Teste terapêutico com dieta; retorno.', [['Dermatite atópica', 'L20', 'confirmed']]],
        [2, 210, '12.300', 'Feridas no dorso e muita coceira.', 'Pulgas visíveis; alopecia lombossacral.', 'Antipulgas e controle ambiental.', [['Dermatite alérgica à picada de pulga', 'L23', 'confirmed'], ['Dermatite atópica', 'L20', 'ruled_out']]],
        [3, 330, '13.100', 'Coceira e vermelhidão nas dobras da face.', 'Piodermite de dobra facial.', 'Limpeza diária e antibiótico tópico.', [['Piodermite superficial', 'L08', 'confirmed'], ['Dermatite atópica', 'L20', 'suspected']]],
        [3, 60, '13.400', 'Retorno: coceira voltou após o tratamento.', 'Eritema axilar e inguinal.', 'Exclusão de alergia alimentar; imunomodulador.', [['Dermatite atópica', 'L20', 'confirmed']]],
        [8, 95, '29.800', 'Coceira intensa em orelhas e patas.', 'Otite bilateral, eritema interdigital.', 'Tratamento otológico e citologia.', [['Dermatite atópica', 'L20', 'suspected'], ['Otite externa', 'H60', 'confirmed']]],
        // Endócrino / obesidade / geriatria canina.
        [4, 700, '38.500', 'Ganho de peso e cansaço nos passeios.', 'ECC 8/9.', 'Plano de emagrecimento; ração light.', [['Obesidade', 'E66', 'confirmed']]],
        [4, 380, '34.900', 'Retorno do plano de peso.', 'ECC 7/9.', 'Manter plano; aumentar atividade.', [['Obesidade', 'E66', 'confirmed']]],
        [5, 510, '7.900', 'Bebe muita água e urina mais.', 'Abdômen penduloso, pelame ralo.', 'Exames de sangue e teste de supressão.', [['Hiperadrenocorticismo', 'E24', 'suspected']]],
        [5, 470, '7.700', 'Resultado dos exames.', 'Sem alterações novas.', 'Iniciado tratamento; monitorar.', [['Hiperadrenocorticismo', 'E24', 'confirmed']]],
        [6, 260, '18.600', 'Mau hálito e dificuldade para mastigar.', 'Cálculo dentário grau 3; gengivite.', 'Profilaxia dentária sob anestesia.', [['Doença periodontal', 'K05', 'confirmed']]],
        [7, 45, '6.100', 'Tosse seca à noite e cansaço.', 'Sopro sistólico grau IV/VI em foco mitral.', 'Ecocardiograma; iniciar cardioprotetor.', [['Doença valvar mitral degenerativa', 'I34', 'confirmed']]],
        [9, 20, '9.800', 'Vômito e diarreia há 2 dias.', 'Desidratação leve.', 'Fluidoterapia subcutânea e dieta leve.', [['Gastroenterite aguda', 'K52', 'confirmed'], ['Parvovirose', 'A08', 'ruled_out']]],
        // Felinos: doença renal crônica (série científica encerrada) e outros.
        [10, 760, '4.900', 'Emagrecimento, bebe muita água e recusa alimentar há 4 dias.', 'Rins pequenos à palpação; desidratação 5%.', 'Exames de sangue e urina.', [['Doença renal crônica', 'N18', 'suspected']]],
        [10, 730, '4.700', 'Resultado dos exames: creatinina elevada.', 'Mucosas levemente pálidas.', 'Dieta renal; fluido subcutâneo em casa.', [['Doença renal crônica', 'N18', 'confirmed']]],
        [10, 300, '4.500', 'Acompanhamento renal.', 'Estável.', 'Manter dieta; exames a cada 3 meses.', [['Doença renal crônica', 'N18', 'confirmed']]],
        [11, 560, '3.600', 'Perda de apetite e vômitos ocasionais.', 'Halitose urêmica.', 'Exames; dieta renal.', [['Doença renal crônica', 'N18', 'confirmed']]],
        [12, 640, '5.200', 'Bebe muita água e não se alimenta direito há uma semana.', 'Hipertensão aferida.', 'Anti-hipertensivo e dieta renal.', [['Doença renal crônica', 'N18', 'confirmed'], ['Hipertensão sistêmica', 'I10', 'confirmed']]],
        [13, 180, '3.900', 'Emagrecimento apesar de comer bem.', 'Nódulo tireoidiano palpável; taquicardia.', 'Dosagem de T4.', [['Hipertireoidismo', 'E05', 'suspected'], ['Doença renal crônica', 'N18', 'suspected']]],
        [14, 340, '7.400', 'Respiração rápida em repouso.', 'Galope à ausculta.', 'Ecocardiograma.', [['Cardiomiopatia hipertrófica', 'I42', 'suspected']]],
        [14, 12, '7.300', 'Retorno com ecocardiograma.', 'Espessamento do septo confirmado.', 'Iniciar medicação; restrição de esforço.', [['Cardiomiopatia hipertrófica', 'I42', 'confirmed']]],
        [15, 30, '2.900', 'Primeira consulta; espirros e secreção ocular.', 'Conjuntivite bilateral.', 'Colírio e vacinação após melhora.', [['Complexo respiratório felino', 'J06', 'confirmed']]],
        // Exóticos.
        [16, 400, '0.092', 'Arranca as próprias penas.', 'Falhas de penas no peito; ave ativa.', 'Enriquecimento ambiental; avaliar dieta.', [['Arrancamento de penas (comportamental)', null, 'suspected']]],
        [17, 40, '0.420', 'Olho direito fechado e lacrimejando.', 'Opacidade da câmara anterior e hiperemia no olho direito.', 'Colírio anti-inflamatório; reavaliar em 7 dias.', [['Uveíte anterior', 'H20', 'suspected']]],
        [17, 250, '0.410', 'Bico crescido e dificuldade para comer.', 'Sobrecrescimento de bico; plumagem opaca.', 'Desgaste do bico; correção da dieta.', [['Deficiência nutricional', 'E63', 'confirmed']]],
        [18, 900, '2.150', 'Olhos inchados e não come há 1 semana.', 'Blefaroedema bilateral; secreção nasal.', 'Vitamina A injetável; ajuste de dieta e UVB.', [['Hipovitaminose A', 'E50', 'confirmed']]],
        [18, 870, '2.200', 'Retorno: olhos abriram, voltou a comer.', 'Melhora clínica evidente.', 'Manter dieta e iluminação UVB.', [['Hipovitaminose A', 'E50', 'confirmed']]],
        [19, 140, '1.650', 'Baba e não come ração.', 'Pontas dentárias em pré-molares.', 'Desgaste dentário; feno à vontade.', [['Má oclusão dentária', 'K07', 'confirmed']]],
        [20, 25, '0.140', 'Diarreia e prostração.', 'Região perianal úmida.', 'Hidratação e probiótico.', [['Enterite', 'K52', 'suspected']]],
        [21, 75, '0.950', 'Perda de pelo na cauda e coceira.', 'Alopecia simétrica.', 'Exames hormonais.', [['Doença adrenal do furão', 'E27', 'suspected']]],
    ];

    /** Vacinas: [paciente, vacina, dose, aplicada há N dias, próxima dose em N dias (negativo = atrasada)]. */
    private const VACCINES = [
        [0, 'Polivalente canina (V8/V10)', 4, 380, -15], [0, 'Antirrábica', 3, 200, 165],
        [1, 'Polivalente canina (V8/V10)', 3, 360, 5], [1, 'Antirrábica', 2, 355, 10],
        [2, 'Polivalente canina (V8/V10)', 2, 30, -9], [3, 'Polivalente canina (V8/V10)', 3, 340, 25],
        [4, 'Antirrábica', 5, 390, -25], [6, 'Antirrábica', 6, 100, 265], [8, 'Leishmaniose visceral canina', 3, 350, 15],
        [9, 'Polivalente canina (V8/V10)', 1, 20, 1], [10, 'Antirrábica', 8, 370, -5], [11, 'Polivalente felina (V4/V5)', 6, 300, 65],
        [13, 'Polivalente felina (V4/V5)', 4, 358, 7], [14, 'Antirrábica', 4, 50, 315], [15, 'Polivalente felina (V4/V5)', 1, 25, -4],
    ];

    /** Agenda: [paciente, dias a partir de hoje, hora, situação]. */
    private const APPOINTMENTS = [
        [3, 0, '08:30', 'confirmed'], [14, 0, '09:30', 'waiting'], [19, 0, '11:00', 'scheduled'], [7, 0, '15:00', 'scheduled'],
        [10, 1, '09:00', 'confirmed'], [2, 1, '10:30', 'scheduled'], [15, 2, '14:00', 'scheduled'], [4, 3, '16:30', 'scheduled'],
        [18, 4, '10:00', 'scheduled'], [1, 6, '08:00', 'scheduled'],
    ];

    /** Adendos (texto livre pós-finalização, aparecem na busca clínica): [atendimento (índice), dias depois, texto]. */
    private const ADDENDA = [
        [2, 10, 'Tutora informou que o prurido voltou após banho com xampu comum; orientado retomar o xampu terapêutico.'],
        [16, 20, 'Contato por telefone: segue com recusa alimentar parcial; reforçado oferecer ração úmida aquecida.'],
        [26, 3, 'Retorno antecipado: uveíte com melhora parcial; manter colírio por mais 7 dias.'],
    ];

    /**
     * Anexos fictícios: [paciente, tipo (pdf|docx|png), título, categoria, texto do arquivo].
     * O texto só existe DENTRO do arquivo (não está em atendimento), para provar a busca no conteúdo do anexo.
     */
    private const ATTACHMENTS = [
        [17, 'pdf', 'Laudo oftalmológico', 'exam', 'Laudo oftalmológico (fictício) — Papagaio-verdadeiro. Tonometria de rebote: pressão intraocular de 9 mmHg no olho direito e 14 mmHg no olho esquerdo. Biomicroscopia: flare em câmara anterior compatível com uveíte anterior. Sugere-se pesquisa de clamidiose.'],
        [19, 'docx', 'Sorologia — resultado do laboratório', 'exam', 'Resultado de exame (fictício). Sorologia para Encephalitozoon cuniculi: reagente, título 1:640. Interpretação: exposição ao agente; correlacionar com sinais neurológicos.'],
        [18, 'png', 'Foto do casco', 'image', ''],
    ];

    /** Alertas: [paciente, tipo, gravidade, descrição]. */
    private const ALERTS = [
        [0, 'allergy', 'high', 'Alergia a penicilina — demonstração'],
        [7, 'chronic', 'medium', 'Cardiopata: evitar estresse e sedação sem avaliação — demonstração'],
        [10, 'chronic', 'medium', 'Doente renal crônico — demonstração'],
        [12, 'medication', 'medium', 'Uso contínuo de anti-hipertensivo — demonstração'],
        [3, 'behavior', 'low', 'Fica agitado na contenção — demonstração'],
    ];

    public function __construct(private readonly TenantContext $context, private readonly ClinicalSearchIndex $search, private readonly AttachmentService $attachments) {}

    /** ID demo da clínica: prefixo + 8 hex do tenant (evita colisão entre clínicas) + tipo + número. Hex é Crockford válido. */
    public static function idFor(string $tenantId, string $kind, int $n): string
    {
        $head = self::PREFIX.strtoupper(hash('crc32b', $tenantId)).$kind;

        return $head.str_pad((string) $n, 26 - strlen($head), '0', STR_PAD_LEFT);
    }

    private function id(string $kind, int $n): string
    {
        return self::idFor($this->context->requireId(), $kind, $n);
    }

    /** @return array{patients: int, guardians: int, encounters: int, cases: int} */
    public function counts(): array
    {
        $tenant = $this->context->requireId();
        $count = fn (string $table) => DB::table($table)->where('tenant_id', $tenant)->where('id', 'like', self::PREFIX.'%')->count();

        return ['patients' => $count('patients'), 'guardians' => $count('guardians'), 'encounters' => $count('encounters'), 'cases' => $count('clinical_cases')];
    }

    public function isLoaded(): bool
    {
        return $this->counts()['patients'] > 0;
    }

    /** Versão carregada (1 quando é anterior ao controle de versão) ou null se não há demonstração. */
    public function loadedVersion(): ?int
    {
        $notes = DB::table('guardians')->where('tenant_id', $this->context->requireId())->where('id', $this->id('GR', 1))->value('notes');
        if ($notes === null) {
            return null;
        }

        return preg_match('/versão (\d+)/u', (string) $notes, $m) ? (int) $m[1] : 1;
    }

    /** Versão carregada quando é ANTERIOR à atual (para avisar e oferecer atualização); senão null. */
    public function outdated(): ?int
    {
        $v = $this->loadedVersion();

        return $v !== null && $v < self::VERSION ? $v : null;
    }

    /**
     * Atualiza para a versão atual: remove a demonstração antiga e carrega a nova.
     *
     * @return array{patients: int, encounters: int, vaccinations: int, appointments: int, cases: int, addenda: int, attachments: int}
     */
    public function refresh(string $userId): array
    {
        $this->remove();

        return $this->load($userId);
    }

    /** @return array{patients: int, encounters: int, vaccinations: int, appointments: int, cases: int, addenda: int, attachments: int} */
    public function load(string $userId): array
    {
        $tenant = $this->context->requireId();
        if ($this->isLoaded()) {
            throw new RuntimeException('Os dados de demonstração já estão carregados nesta clínica.');
        }
        $membership = Membership::query()->where('user_id', $userId)->value('id');
        $unit = Unit::query()->orderBy('created_at')->orderBy('id')->first();
        if ($membership === null || $unit === null) {
            throw new RuntimeException('Cadastre ao menos uma unidade antes de carregar a demonstração.');
        }
        app(ScheduleService::class)->ensureDefaultTypes();
        app(VaccinationService::class)->ensureDefaults();
        $tz = $unit->timezone ?: 'America/Sao_Paulo';
        $now = now();
        $stamp = ['created_at' => $now, 'updated_at' => $now];
        $base = ['tenant_id' => $tenant];

        $written = [];
        try {
            DB::transaction(function () use ($userId, $membership, $unit, $tz, $now, $stamp, $base, &$written) {
                foreach (self::GUARDIANS as $i => [$name, $phone, $city, $uf]) {
                    DB::table('guardians')->insert($base + $stamp + [
                        'id' => $this->id('GR', $i + 1), 'name' => $name, 'person_type' => 'PF', 'phone' => $phone,
                        'email' => 'tutor'.($i + 1).'@demo.exemplo.test', 'city' => $city, 'state' => $uf, 'status' => 'active',
                        'notes' => 'Cadastro fictício de demonstração (versão '.self::VERSION.').', 'created_by' => $userId,
                    ]);
                }
                foreach (self::PATIENTS as $i => [$name, $species, $detail, $breed, $sex, $neutered, $birth, $g]) {
                    $pid = $this->id('PT', $i + 1);
                    DB::table('patients')->insert($base + $stamp + [
                        'id' => $pid, 'name' => $name, 'species' => $species, 'species_detail' => $detail, 'breed' => $breed,
                        'sex' => $sex, 'neutered' => $neutered, 'birth_date' => $birth, 'birth_date_estimated' => $birth !== null && str_ends_with($birth, '-01-01'),
                        'status' => 'active', 'marks' => 'Paciente fictício de demonstração.', 'created_by' => $userId,
                    ]);
                    DB::table('guardian_patient')->insert($base + $stamp + ['guardian_id' => $this->id('GR', $g + 1), 'patient_id' => $pid, 'relationship' => 'primary']);
                }

                $encounterOf = []; // paciente => [[id, quando, diagnósticos]]
                foreach (self::ENCOUNTERS as $i => [$p, $daysAgo, $weight, $complaint, $exam, $plan, $dxs]) {
                    $eid = $this->id('EN', $i + 1);
                    $pid = $this->id('PT', $p + 1);
                    $when = Carbon::now($tz)->subDays($daysAgo)->setTime(9 + $i % 8, ($i * 17) % 60)->utc();
                    DB::table('encounters')->insert($base + [
                        'id' => $eid, 'unit_id' => $unit->id, 'patient_id' => $pid, 'status' => 'finalized', 'started_at' => $when,
                        'chief_complaint' => $complaint, 'history' => 'Histórico fictício de demonstração.', 'physical_exam' => $exam,
                        'plan' => $plan, 'guidance' => 'Orientações fictícias de demonstração.', 'weight_kg' => $weight,
                        'temperature_c' => '38.'.($i % 9), 'finalized_at' => $when->copy()->addMinutes(35), 'finalized_by' => $userId,
                        'signer_membership_id' => $membership, 'created_by' => $userId, 'created_at' => $when, 'updated_at' => $when->copy()->addMinutes(35),
                    ]);
                    foreach ($dxs as $pos => [$desc, $code, $status]) {
                        DB::table('encounter_diagnoses')->insert($base + [
                            'id' => $this->id('DX', ($i + 1) * 10 + $pos), 'encounter_id' => $eid, 'patient_id' => $pid, 'description' => $desc,
                            'code' => $code, 'status' => $status, 'position' => $pos, 'created_by' => $userId, 'created_at' => $when, 'updated_at' => $when,
                        ]);
                    }
                    DB::table('patient_weights')->insert($base + [
                        'id' => $this->id('PW', $i + 1), 'patient_id' => $pid, 'encounter_id' => $eid, 'weight_kg' => $weight,
                        'measured_at' => $when, 'created_by' => $userId, 'created_at' => $when, 'updated_at' => $when,
                    ]);
                    $encounterOf[$p][] = ['id' => $eid, 'dx' => array_column($dxs, 2, 0)];
                }

                foreach (self::ADDENDA as $i => [$e, $daysAfter, $body]) {
                    [$p, $daysAgo] = self::ENCOUNTERS[$e];
                    DB::table('encounter_addenda')->insert($base + [
                        'id' => $this->id('AD', $i + 1), 'encounter_id' => $this->id('EN', $e + 1), 'body' => $body, 'author_id' => $userId,
                        'author_membership_id' => $membership, 'created_at' => Carbon::now($tz)->subDays(max(0, $daysAgo - $daysAfter))->utc(),
                    ]);
                }

                foreach (self::ALERTS as $i => [$p, $kind, $severity, $desc]) {
                    DB::table('patient_alerts')->insert($base + $stamp + [
                        'id' => $this->id('PA', $i + 1), 'patient_id' => $this->id('PT', $p + 1), 'kind' => $kind, 'severity' => $severity,
                        'description' => $desc, 'created_by' => $userId,
                    ]);
                }

                $vaccines = Vaccine::query()->pluck('id', 'name');
                foreach (self::VACCINES as $i => [$p, $name, $dose, $appliedAgo, $nextIn]) {
                    if (! isset($vaccines[$name])) {
                        continue; // vacina padrão renomeada/removida pela clínica
                    }
                    DB::table('vaccinations')->insert($base + $stamp + [
                        'id' => $this->id('VX', $i + 1), 'patient_id' => $this->id('PT', $p + 1), 'vaccine_id' => $vaccines[$name],
                        'dose_number' => $dose, 'applied_on' => Carbon::now($tz)->subDays($appliedAgo)->toDateString(),
                        'lot' => 'DEMO-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT), 'manufacturer' => 'Fabricante fictício',
                        'lot_expires_on' => Carbon::now($tz)->addYear()->toDateString(), 'site' => 'Subcutânea',
                        'next_due_on' => Carbon::now($tz)->addDays($nextIn)->toDateString(), 'applied_by_membership_id' => $membership, 'created_by' => $userId,
                    ]);
                }

                $type = AppointmentType::query()->where('active', true)->orderBy('name')->first();
                foreach ($type ? self::APPOINTMENTS : [] as $i => [$p, $inDays, $time, $status]) {
                    $start = ScheduleService::toUtc(Carbon::now($tz)->addDays($inDays)->format('Y-m-d'), $time, $tz);
                    DB::table('appointments')->insert($base + $stamp + [
                        'id' => $this->id('AP', $i + 1), 'unit_id' => $unit->id, 'patient_id' => $this->id('PT', $p + 1),
                        'appointment_type_id' => $type->id, 'professional_membership_id' => $membership, 'starts_at' => $start,
                        'ends_at' => $start->copy()->addMinutes($type->duration_minutes), 'status' => $status, 'is_walk_in' => false,
                        'notes' => 'Agendamento fictício de demonstração.', 'confirmed_at' => in_array($status, ['confirmed', 'waiting'], true) ? $now : null,
                        'checked_in_at' => $status === 'waiting' ? $now : null, 'created_by' => $userId,
                    ]);
                }

                $this->cases($base, $userId, $encounterOf, $now);
                $written = $this->attachmentFiles($base, $userId, $now);
                // Busca clínica: os pacientes demo já entram no índice.
                foreach (array_keys(self::PATIENTS) as $i) {
                    $this->search->reindexPatient($this->id('PT', $i + 1));
                }
            });
        } catch (\Throwable $e) {
            foreach ($written as $path) {
                Storage::disk('local')->delete($path); // sem registro, sem arquivo órfão
            }

            throw $e;
        }

        return [
            'patients' => count(self::PATIENTS), 'encounters' => count(self::ENCOUNTERS), 'vaccinations' => count(self::VACCINES),
            'appointments' => count(self::APPOINTMENTS), 'cases' => 3, 'addenda' => count(self::ADDENDA), 'attachments' => count(self::ATTACHMENTS),
        ];
    }

    /**
     * Grava os anexos fictícios no disco privado (como um envio real) e lê o texto pelo mesmo extrator do sistema.
     *
     * @param  array<string, string>  $base
     * @return list<string> caminhos gravados
     */
    private function attachmentFiles(array $base, string $userId, Carbon $now): array
    {
        $paths = [];
        foreach (self::ATTACHMENTS as $i => [$p, $type, $title, $category, $text]) {
            $bytes = match ($type) {
                'pdf' => app(DocumentService::class)->renderHtml('<html><body style="font-family: DejaVu Sans"><h2>'.e($title).'</h2><p>'.e($text).'</p></body></html>'),
                'docx' => self::docx($text),
                default => self::png(),
            };
            if ($bytes === null) {
                continue; // servidor sem ZipArchive: DOCX demo fica de fora
            }
            [$mime, $ext] = ['pdf' => ['application/pdf', 'pdf'], 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx'], 'png' => ['image/png', 'png']][$type];
            $id = $this->id('AT', $i + 1);
            $path = sprintf('attachments/%s/%s/%s.%s', $this->context->requireId(), $now->format('Y'), $id, $ext);
            Storage::disk('local')->put($path, $bytes);
            $paths[] = $path;
            DB::table('attachments')->insert($base + [
                'id' => $id, 'patient_id' => $this->id('PT', $p + 1), 'encounter_id' => null, 'category' => $category, 'title' => $title,
                'description' => 'Arquivo fictício de demonstração.', 'original_name' => Str::slug($title).'.'.$ext, 'mime' => $mime,
                'extension' => $ext, 'size_bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'storage_path' => $path,
                'uploaded_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->attachments->extractText(Attachment::query()->findOrFail($id), $bytes);
        }

        return $paths;
    }

    /** DOCX mínimo válido (Word abre) com um parágrafo por frase. */
    private static function docx(string $text): ?string
    {
        if (! class_exists(\ZipArchive::class)) {
            return null;
        }
        $paras = implode('', array_map(fn ($t) => '<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($t, ENT_XML1).'</w:t></w:r></w:p>', preg_split('/(?<=\.)\s+/', $text) ?: [$text]));
        $file = (string) tempnam(sys_get_temp_dir(), 'fvdocx');
        $zip = new \ZipArchive;
        $zip->open($file, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$paras.'</w:body></w:document>');
        $zip->close();
        $bytes = (string) file_get_contents($file);
        @unlink($file);

        return $bytes;
    }

    /** PNG 32×32 (gradiente) gerado sem GD: imagem sem texto → "sem texto pesquisável". */
    private static function png(): string
    {
        $raw = '';
        for ($y = 0; $y < 32; $y++) {
            $raw .= "\0";
            for ($x = 0; $x < 32; $x++) {
                $raw .= chr(120 + $x * 3).chr(90 + $y * 3).chr(60);
            }
        }
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 32, 32, 8, 2, 0, 0, 0)).$chunk('IDAT', (string) gzcompress($raw)).$chunk('IEND', '');
    }

    /**
     * Três estudos: série aberta (dermatite atópica em cães), série encerrada (DRC em gatos) e relato (hipovitaminose A
     * em jabuti), com desfechos e consentimentos variados para mostrar a exportação anonimizada.
     *
     * @param  array<string, string>  $base
     * @param  array<int, list<array{id: string, dx: array<string, string>}>>  $encounterOf
     */
    private function cases(array $base, string $userId, array $encounterOf, Carbon $now): void
    {
        $studies = [
            ['CC', 1, 'series', 'Dermatite atópica em cães — resposta ao tratamento', 'Qual a proporção de cães com dermatite atópica confirmada que melhoram em até 90 dias?',
                null, 'Dermatologia', ['especie' => 'canine', 'diagnostico' => 'dermatite atópica', 'situacao' => 'confirmed'], 'open', 'Dermatite atópica', [
                    [0, 'improved', 'granted', 'Bento respondeu bem ao imunomodulador; tutora Beatriz ligou do (11) 90000-0101 relatando melhora.'],
                    [1, 'resolved', 'granted', 'Sem recidiva após troca de dieta.'],
                    [3, 'stable', 'pending', 'Aguardando assinatura do termo pela família.'],
                ]],
            ['CC', 2, 'series', 'Doença renal crônica em gatos idosos', 'Como evoluíram o peso e o apetite após a dieta renal?',
                'Série encerrada de demonstração: 3 gatos idosos com DRC confirmada; dois mantiveram o peso e um perdeu peso no período.', 'Nefrologia',
                ['especie' => 'feline', 'diagnostico' => 'renal', 'situacao' => 'confirmed'], 'closed', 'Doença renal crônica', [
                    [10, 'stable', 'granted', 'Manteve peso com fluido subcutâneo em casa.'],
                    [11, 'worsened', 'granted', 'Perdeu peso; tutor Eduardo optou por cuidados paliativos.'],
                    [12, 'stable', 'refused', null],
                ]],
            ['CC', 3, 'report', 'Relato: hipovitaminose A em jabuti-piranga', 'Descrever a recuperação com suplementação e correção do manejo.',
                null, 'Animais silvestres e exóticos', null, 'open', 'Hipovitaminose A', [
                    [18, 'resolved', 'granted', 'Voltou a se alimentar em 30 dias com luz UVB e dieta variada.'],
                ]],
        ];
        foreach ($studies as [$kind, $n, $type, $title, $question, $summary, $specialty, $criteria, $status, $dxName, $entries]) {
            $cid = $this->id($kind, $n);
            DB::table('clinical_cases')->insert($base + [
                'id' => $cid, 'kind' => $type, 'title' => $title, 'question' => $question, 'summary' => $summary, 'specialty' => $specialty,
                'criteria' => $criteria ? json_encode($criteria) : null, 'status' => $status, 'next_label' => count($entries) + 1,
                'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($entries as $k => [$p, $outcome, $consent, $notes]) {
                $recorded = $consent !== 'pending';
                $ref = collect($encounterOf[$p] ?? [])->last(fn ($e) => ($e['dx'][$dxName] ?? null) === 'confirmed');
                DB::table('clinical_case_patients')->insert($base + [
                    'id' => $this->id('CP', $n * 100 + $k + 1), 'case_id' => $cid, 'patient_id' => $this->id('PT', $p + 1),
                    'encounter_id' => $ref['id'] ?? null, 'label' => sprintf('Caso %02d', $k + 1), 'outcome' => $outcome, 'notes' => $notes,
                    'consent' => $consent, 'consent_on' => $recorded ? $now->toDateString() : null,
                    'consent_recorded_by' => $recorded ? $userId : null, 'added_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Remove os dados de demonstração e tudo o que foi registrado nos pacientes demo (atendimentos, vacinas,
     * documentos, anexos e arquivos). Nada fora do prefixo demo é tocado.
     *
     * @return array{patients: int, guardians: int, cases: int, files: int}
     */
    public function remove(): array
    {
        $tenant = $this->context->requireId();
        $demo = fn (string $table) => DB::table($table)->where('tenant_id', $tenant)->where('id', 'like', self::PREFIX.'%');
        $patients = $demo('patients')->pluck('id')->all();
        $cases = $demo('clinical_cases')->pluck('id')->all();
        $guardians = $demo('guardians')->pluck('id')->all();
        $files = [];

        DB::transaction(function () use ($tenant, $patients, $cases, $guardians, $demo, &$files) {
            $t = fn (string $table) => DB::table($table)->where('tenant_id', $tenant);
            $t('clinical_case_patients')->where(fn ($q) => $q->whereIn('case_id', $cases ?: [''])->orWhereIn('patient_id', $patients ?: ['']))->delete();
            $demo('clinical_cases')->delete();
            if ($patients !== []) {
                $encounters = $t('encounters')->whereIn('patient_id', $patients)->pluck('id')->all();
                $files = [
                    ...$t('issued_documents')->whereIn('patient_id', $patients)->pluck('pdf_path')->all(),
                    ...$t('attachments')->whereIn('patient_id', $patients)->pluck('storage_path')->all(),
                ];
                foreach (['clinical_search_documents', 'vaccinations', 'issued_documents', 'attachments', 'patient_weights', 'patient_alerts', 'encounter_diagnoses'] as $table) {
                    $t($table)->whereIn('patient_id', $patients)->delete();
                }
                if ($encounters !== []) {
                    $t('encounter_addenda')->whereIn('encounter_id', $encounters)->delete();
                }
                $t('encounters')->whereIn('patient_id', $patients)->delete();
                $t('appointments')->whereIn('patient_id', $patients)->delete();
                $t('guardian_patient')->whereIn('patient_id', $patients)->delete();
            }
            if ($guardians !== []) {
                $t('guardian_patient')->whereIn('guardian_id', $guardians)->delete(); // vínculo de paciente real a tutor demo
            }
            $demo('patients')->delete();
            $demo('guardians')->delete();
        });
        $this->search->refreshCount();
        foreach ($files as $path) {
            Storage::disk('local')->delete($path); // depois do commit: sem registro, sem arquivo órfão
        }

        return ['patients' => count($patients), 'guardians' => count($guardians), 'cases' => count($cases), 'files' => count($files)];
    }
}
