<?php

namespace App\Services;

use App\Documents\PdfRenderer;
use App\Documents\TemplateImporter;
use App\Models\AppointmentType;
use App\Models\Attachment;
use App\Models\ExamRequest;
use App\Models\ExamResultValue;
use App\Models\ExamType;
use App\Models\Membership;
use App\Models\Unit;
use App\Models\User;
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
     * 1 = 18/10 (base) · 2 = 19/10 (busca em texto livre) · 3 = 19/10 (anexos com texto + correções da busca) ·
     * 4 = 20/10 (sinônimos, pesquisa salva, modelo importado e exames) · 5 = 21/10 (fundos de documento e exames
     * estruturados com série histórica).
     */
    public const VERSION = 5;

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

    /** Sinônimos da clínica de demonstração (configuração fictícia, como uma clínica faria). */
    private const SYNONYMS = [
        "recusa alimentar\nnão se alimenta\nanorexia",
        "prurido\ncoceira",
        "DRC\ndoença renal crônica",
    ];

    /**
     * Exames: [paciente, exame do catálogo, situação, dias atrás do pedido, laboratório, resultado, interpretação, motivo de cancelamento].
     * Uma situação de cada para o painel "Exames".
     */
    private const EXAMS = [
        [10, 'Bioquímica renal (ureia e creatinina)', 'reviewed', 25, 'Laboratório Fictício Vet', 'Creatinina 3,8 mg/dL; ureia 120 mg/dL; fósforo 6,9 mg/dL (valores fictícios).', 'Azotemia compatível com doença renal crônica já diagnosticada; manter dieta renal e repetir em 60 dias (demonstração).', null],
        [0, 'Citologia', 'received', 6, 'Na própria clínica', 'Citologia de pele: cocos e neutrófilos degenerados; Malassezia rara (fictício).', null, null],
        [11, 'Coproparasitológico', 'received', 9, 'Laboratório Fictício Vet', 'Pesquisa de cistos: Giardia sp. POSITIVO (fictício).', null, null],
        [19, 'Hemograma completo', 'in_analysis', 3, 'Laboratório Fictício Vet', null, null, null],
        [17, 'Hemograma completo', 'collected', 1, null, null, null, null],
        [7, 'Ecocardiograma', 'requested', 0, null, null, null, null],
        [2, 'Urinálise', 'cancelled', 12, null, null, null, 'Tutor optou por não realizar (demonstração).'],
        [10, 'Bioquímica renal (ureia e creatinina)', 'reviewed', 300, 'Laboratório Fictício Vet', 'Valores no quadro estruturado (fictícios).', 'Creatinina discretamente acima da faixa; repetir em 4–6 meses (demonstração).', null],
        [10, 'Bioquímica renal (ureia e creatinina)', 'reviewed', 150, 'Laboratório Fictício Vet', 'Valores no quadro estruturado (fictícios).', 'Progressão da azotemia; dieta renal iniciada (demonstração).', null],
        [0, 'Hemograma completo', 'received', 2, 'Na própria clínica', 'Valores no quadro estruturado (fictícios).', null, null],
    ];

    /**
     * Valores estruturados FICTÍCIOS: [exame (índice em EXAMS), parâmetro, valor, ref. mín., ref. máx.]. As faixas são
     * inventadas para a demonstração — não use como referência clínica.
     */
    private const EXAM_VALUES = [
        [7, 'Creatinina', 1.9, 0.8, 1.8], [7, 'Ureia', 58, 42, 64],
        [8, 'Creatinina', 2.6, 0.8, 1.8], [8, 'Ureia', 85, 42, 64],
        [0, 'Creatinina', 3.8, 0.8, 1.8], [0, 'Ureia', 120, 42, 64],
        [9, 'Hemácias', 6.8, 5.5, 8.5], [9, 'Hemoglobina', 15.2, 12, 18], [9, 'Hematócrito', 45, 37, 55],
        [9, 'Leucócitos', 19800, 6000, 17000], [9, 'Plaquetas', 310000, 200000, 500000],
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
     * @return array{patients: int, encounters: int, vaccinations: int, appointments: int, cases: int, addenda: int, attachments: int, exams: int}
     */
    public function refresh(string $userId): array
    {
        $this->remove();

        return $this->load($userId);
    }

    /** @return array{patients: int, encounters: int, vaccinations: int, appointments: int, cases: int, addenda: int, attachments: int, exams: int} */
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
                $written = [...$written, ...$this->searchAndTemplates($userId, $now)];
                $this->exams($userId, $tz);
                $this->examValues($userId);
                $written = [...$written, ...$this->backgrounds($userId)];
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
            'appointments' => count(self::APPOINTMENTS), 'cases' => 3, 'addenda' => count(self::ADDENDA), 'attachments' => count(self::ATTACHMENTS), 'exams' => count(self::EXAMS),
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

    /**
     * Sinônimos, uma pesquisa salva compartilhada e um modelo importado de DOCX (com o original guardado).
     *
     * @return list<string> arquivos gravados
     */
    private function searchAndTemplates(string $userId, Carbon $now): array
    {
        $tenant = $this->context->requireId();
        foreach (self::SYNONYMS as $i => $terms) {
            DB::table('clinical_search_synonyms')->insert(['id' => $this->id('SY', $i + 1), 'tenant_id' => $tenant, 'terms' => $terms,
                'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('saved_searches')->insert(['id' => $this->id('SS', 1), 'tenant_id' => $tenant, 'user_id' => $userId, 'name' => 'Gatos com doença renal (demonstração)',
            'params' => json_encode(['q' => 'renal', 'especie' => 'feline']), 'shared' => true, 'created_at' => $now, 'updated_at' => $now]);

        $text = "TERMO DE CONSENTIMENTO PARA SEDAÇÃO (modelo fictício de demonstração)\nPaciente: __________\nEspécie: ______  Raça: ______\n"
            ."Tutor responsável: ______________  CPF: ______________\nDeclaro ter sido informado(a) dos riscos da sedação e autorizo o procedimento.\n"
            ."Assinatura: ______________\nData: ____/____/______";
        $bytes = self::docx($text);
        if ($bytes === null || DB::table('document_templates')->where('tenant_id', $tenant)->where('id', $this->id('TP', 1))->exists()) {
            return [];
        }
        [$body] = TemplateImporter::convert(str_replace('. ', ".\n", $text));
        $r = app(TemplateImporter::class)->create($bytes, ['mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'extension' => 'docx'],
            'termo-sedacao-clinica.docx', 'consent', 'Termo de sedação (importado — demonstração)', $body, $userId, $this->id('TP', 1));

        return [(string) $r['template']->source_path];
    }

    /** Pedidos de exame em todas as situações (catálogo padrão da clínica). */
    private function exams(string $userId, string $tz): void
    {
        app(ExamService::class)->ensureDefaults();
        $types = ExamType::query()->pluck('id', 'name');
        $tenant = $this->context->requireId();
        foreach (self::EXAMS as $i => [$p, $name, $status, $daysAgo, $lab, $result, $interpretation, $cancel]) {
            if (! isset($types[$name])) {
                continue; // a clínica renomeou o exame padrão
            }
            $at = Carbon::now($tz)->subDays($daysAgo)->setTime(10, 0)->utc();
            $step = fn (string $s, int $plusHours) => array_search($status, ExamRequest::FLOW, true) >= array_search($s, ExamRequest::FLOW, true) && $status !== 'cancelled' ? $at->copy()->addHours($plusHours) : null;
            DB::table('exam_requests')->insert([
                'id' => $this->id('EX', $i + 1), 'tenant_id' => $tenant, 'patient_id' => $this->id('PT', $p + 1), 'exam_type_id' => $types[$name],
                'status' => $status, 'lab_name' => $lab, 'request_notes' => 'Pedido fictício de demonstração.', 'requested_by' => $userId, 'requested_at' => $at,
                'collected_at' => $step('collected', 1), 'sent_at' => $step('in_analysis', 3), 'received_at' => $step('received', 48),
                'result_text' => $result, 'result_by' => $result ? $userId : null, 'interpretation' => $interpretation,
                'reviewed_at' => $step('reviewed', 50), 'reviewed_by' => $status === 'reviewed' ? $userId : null,
                'cancelled_at' => $cancel ? $at->copy()->addDay() : null, 'cancel_reason' => $cancel, 'created_at' => $at, 'updated_at' => $at,
            ]);
        }
    }

    /**
     * Valores estruturados dos exames demo e faixas FICTÍCIAS no catálogo (só onde a clínica ainda não tem faixa
     * para a espécie — as faixas demo têm ID com o prefixo e saem na remoção).
     */
    private function examValues(string $userId): void
    {
        $tenant = $this->context->requireId();
        $analytes = DB::table('exam_analytes')->where('tenant_id', $tenant)->get(['id', 'exam_type_id', 'name', 'unit']);
        $exams = DB::table('exam_requests')->where('tenant_id', $tenant)->where('id', 'like', self::PREFIX.'%')->get()->keyBy('id');
        $now = now();
        foreach (self::EXAM_VALUES as $i => [$e, $name, $value, $min, $max]) {
            $exam = $exams->get($this->id('EX', $e + 1));
            $analyte = $exam ? $analytes->first(fn ($a) => $a->exam_type_id === $exam->exam_type_id && $a->name === $name) : null;
            if ($exam === null || $analyte === null) {
                continue; // a clínica renomeou o exame/parâmetro padrão
            }
            $species = self::PATIENTS[self::EXAMS[$e][0]][1];
            if (! DB::table('exam_reference_ranges')->where('tenant_id', $tenant)->where('analyte_id', $analyte->id)->where('species', $species)->exists()) {
                DB::table('exam_reference_ranges')->insert(['id' => $this->id('RR', $i + 1), 'tenant_id' => $tenant, 'analyte_id' => $analyte->id,
                    'species' => $species, 'ref_min' => $min, 'ref_max' => $max, 'created_at' => $now, 'updated_at' => $now]);
            }
            DB::table('exam_result_values')->insert(['id' => $this->id('EV', $i + 1), 'tenant_id' => $tenant, 'exam_request_id' => $exam->id,
                'patient_id' => $exam->patient_id, 'analyte_id' => $analyte->id, 'value' => $value, 'unit' => $analyte->unit, 'ref_min' => $min, 'ref_max' => $max,
                'flag' => ExamResultValue::flagFor((float) $value, (float) $min, (float) $max), 'measured_at' => $exam->collected_at ?? $exam->requested_at,
                'recorded_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    /**
     * Papel timbrado (PDF) e receituário (Word com cabeçalho, logotipo e rodapé) FICTÍCIOS — só para os tipos que a
     * clínica ainda não enviou (nunca substitui um fundo real).
     *
     * @return list<string> arquivos gravados
     */
    private function backgrounds(string $userId): array
    {
        $service = app(DocumentBackgroundService::class);
        $current = $service->current();
        $actor = User::query()->findOrFail($userId);
        $written = [];
        if (! isset($current['letterhead'])) {
            $pdf = PdfRenderer::html(self::letterheadHtml());
            $bg = $service->store('letterhead', $pdf, ['mime' => 'application/pdf', 'extension' => 'pdf'], 'papel-timbrado-demonstracao.pdf', $actor, $this->id('BG', 1));
            $written = [$bg->source_path, $bg->render_path];
        }
        $docx = self::letterheadDocx('Clínica Demonstração (fictícia)', 'RECEITUÁRIO', 'Rua Fictícia, 100 — São Paulo/SP · (11) 0000-0000 · demonstração');
        if (! isset($current['prescription']) && $docx !== null) {
            $bg = $service->store('prescription', $docx, ['mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'extension' => 'docx'],
                'receituario-demonstracao.docx', $actor, $this->id('BG', 2));
            $written = [...$written, $bg->source_path, $bg->render_path];
        }

        return $written;
    }

    /** Papel timbrado fictício: faixa superior com nome, filete e rodapé. */
    private static function letterheadHtml(): string
    {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>@page{margin:0}body{margin:0;font-family:"DejaVu Sans",sans-serif}</style></head><body>'
            .'<div style="position:absolute;left:0;top:0;width:210mm;height:30mm;background:#0e5e66"></div>'
            .'<div style="position:absolute;left:18mm;top:9mm;color:#fff;font-size:17pt;font-weight:bold">Clínica Demonstração (fictícia)</div>'
            .'<div style="position:absolute;left:18mm;top:19mm;color:#d6f1ef;font-size:8.5pt">Medicina veterinária · papel timbrado de demonstração</div>'
            .'<div style="position:absolute;left:18mm;right:18mm;top:33mm;border-top:1.2pt solid #e0a43a"></div>'
            .'<div style="position:absolute;left:18mm;right:18mm;bottom:12mm;border-top:0.6pt solid #0e5e66;padding-top:2mm;font-size:7.5pt;color:#0e5e66;text-align:center">'
            .'Rua Fictícia, 100 — Centro — São Paulo/SP · (11) 0000-0000 · contato@demo.exemplo.test</div></body></html>';
    }

    /**
     * DOCX de timbre fictício (Word abre): cabeçalho com logotipo PNG, nome e subtítulo centralizados e rodapé com
     * endereço; margens de 45/25 mm. Também usado nos testes. Sem ZipArchive no servidor → null.
     */
    public static function letterheadDocx(string $name, string $subtitle, string $footer): ?string
    {
        if (! class_exists(\ZipArchive::class)) {
            return null;
        }
        $w = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            .' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            .' xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';
        $t = fn (string $s, int $size, bool $bold = false, string $color = '0E5E66') => '<w:r><w:rPr>'.($bold ? '<w:b/>' : '').'<w:color w:val="'.$color.'"/><w:sz w:val="'.$size.'"/></w:rPr><w:t xml:space="preserve">'.htmlspecialchars($s, ENT_XML1).'</w:t></w:r>';
        $logo = '<w:r><w:drawing><wp:inline><wp:extent cx="540000" cy="540000"/><wp:docPr id="1" name="Logotipo"/><a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic><pic:nvPicPr><pic:cNvPr id="1" name="logo.png"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip r:embed="rIdLogo"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="540000" cy="540000"/></a:xfrm><a:prstGeom prst="rect"/></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
        $center = '<w:pPr><w:jc w:val="center"/></w:pPr>';
        $header = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:hdr '.$w.'><w:p>'.$center.$logo.'</w:p><w:p>'.$center.$t($name, 30, true).'</w:p>'
            .'<w:p>'.$center.$t($subtitle, 20, false, 'B07A1F').'</w:p></w:hdr>';
        $footerXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:ftr '.$w.'><w:p>'.$center.$t($footer, 15).'</w:p></w:ftr>';
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document '.$w.'><w:body><w:p><w:r><w:t>Corpo do documento (não entra no timbre).</w:t></w:r></w:p>'
            .'<w:sectPr><w:headerReference w:type="default" r:id="rIdH"/><w:footerReference w:type="default" r:id="rIdF"/><w:pgSz w:w="11906" w:h="16838"/>'
            .'<w:pgMar w:top="2551" w:right="1134" w:bottom="1417" w:left="1134" w:header="567" w:footer="567" w:gutter="0"/></w:sectPr></w:body></w:document>';
        $rel = fn (string $id, string $type, string $target) => '<Relationship Id="'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/'.$type.'" Target="'.$target.'"/>';
        $rels = fn (string $inner) => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$inner.'</Relationships>';
        $file = (string) tempnam(sys_get_temp_dir(), 'fvlh');
        $zip = new \ZipArchive;
        $zip->open($file, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/></Types>');
        $zip->addFromString('_rels/.rels', $rels($rel('rId1', 'officeDocument', 'word/document.xml')));
        $zip->addFromString('word/document.xml', $document);
        $zip->addFromString('word/_rels/document.xml.rels', $rels($rel('rIdH', 'header', 'header1.xml').$rel('rIdF', 'footer', 'footer1.xml')));
        $zip->addFromString('word/header1.xml', $header);
        $zip->addFromString('word/_rels/header1.xml.rels', $rels($rel('rIdLogo', 'image', 'media/logo.png')));
        $zip->addFromString('word/footer1.xml', $footerXml);
        $zip->addFromString('word/media/logo.png', self::png());
        $zip->close();
        $bytes = (string) file_get_contents($file);
        @unlink($file);

        return $bytes;
    }

    /** DOCX mínimo válido (Word abre) com um parágrafo por frase. */
    private static function docx(string $text): ?string
    {
        if (! class_exists(\ZipArchive::class)) {
            return null;
        }
        $paras = implode('', array_map(fn ($t) => '<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($t, ENT_XML1).'</w:t></w:r></w:p>', preg_split('/(?<=\.)\s+|\n/', $text) ?: [$text]));
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
                foreach (['clinical_search_documents', 'exam_result_values', 'exam_requests', 'vaccinations', 'issued_documents', 'attachments', 'patient_weights', 'patient_alerts', 'encounter_diagnoses'] as $table) {
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
            $demo('clinical_search_synonyms')->delete();
            $demo('exam_reference_ranges')->delete();
            // Fundos demo: apaga (com os arquivos), a não ser que um documento de paciente real o use — aí só aposenta.
            foreach ($demo('document_backgrounds')->get(['id', 'source_path', 'render_path']) as $bg) {
                if ($t('issued_documents')->where('background_id', $bg->id)->exists()) {
                    $t('document_backgrounds')->where('id', $bg->id)->whereNull('retired_at')->update(['retired_at' => now()]);
                } else {
                    $t('document_backgrounds')->where('id', $bg->id)->delete();
                    $files[] = (string) $bg->source_path;
                    $files[] = (string) $bg->render_path;
                }
            }
            $demo('saved_searches')->delete();
            // Modelo importado demo: apaga (com o original), a não ser que um documento de paciente real o use — aí só desativa.
            foreach ($demo('document_templates')->get(['id', 'source_path']) as $tpl) {
                if ($t('issued_documents')->where('template_id', $tpl->id)->exists()) {
                    $t('document_templates')->where('id', $tpl->id)->update(['active' => false]);
                } else {
                    $t('document_templates')->where('id', $tpl->id)->delete();
                    $files[] = (string) $tpl->source_path;
                }
            }
        });
        $this->search->refreshCount();
        foreach ($files as $path) {
            Storage::disk('local')->delete($path); // depois do commit: sem registro, sem arquivo órfão
        }

        return ['patients' => count($patients), 'guardians' => count($guardians), 'cases' => count($cases), 'files' => count($files)];
    }
}
