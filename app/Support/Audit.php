<?php

namespace App\Support;

use App\Models\AuditEvent;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/** Grava eventos de auditoria (somente inclusão). Sem senhas, tokens ou conteúdo clínico. */
final class Audit
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * Evento da clínica ativa.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(string $action, ?string $actorId, ?string $targetType = null, ?string $targetId = null, array $metadata = []): void
    {
        AuditEvent::create([
            'actor_user_id' => $actorId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => $metadata ?: null,
            'request_id' => RequestId::current(),
            'ip_hash' => self::ipHash(),
        ]);
    }

    /**
     * Evento sem clínica (ex.: falha de login). Nunca é exibido a nenhuma clínica.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordPlatform(string $action, ?string $actorId, array $metadata = []): void
    {
        $this->context->runAsSystem(fn () => DB::table('audit_events')->insert([
            'tenant_id' => null,
            'actor_user_id' => $actorId,
            'action' => $action,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'request_id' => RequestId::current(),
            'ip_hash' => self::ipHash(),
            'created_at' => now(),
        ]));
    }

    /** IP pseudonimizado (HMAC com APP_KEY), suficiente para correlação sem guardar o IP. */
    public static function ipHash(): ?string
    {
        $ip = request()->ip();

        return $ip ? substr(hash_hmac('sha256', $ip, (string) config('app.key')), 0, 16) : null;
    }

    public static function emailFingerprint(string $email): string
    {
        return substr(hash_hmac('sha256', mb_strtolower(trim($email)), (string) config('app.key')), 0, 16);
    }
}
