-- FauvoVet 0001 — fundação multi-tenant: tenants, unidades, usuários, vínculos, sessões, tema e auditoria.
-- Executada pelo papel de migração (dono das tabelas). O papel da aplicação ({{APP_ROLE}})
-- NÃO é dono, NÃO tem BYPASSRLS e, portanto, sempre está sujeito às políticas RLS abaixo.
-- O contexto vem de set_config('app.tenant_id'/'app.user_id', ..., true) — local à transação.

CREATE SCHEMA IF NOT EXISTS app;
REVOKE ALL ON SCHEMA app FROM PUBLIC;

CREATE FUNCTION app.current_tenant_id() RETURNS uuid
  LANGUAGE sql STABLE AS $$ SELECT nullif(current_setting('app.tenant_id', true), '')::uuid $$;

CREATE FUNCTION app.current_user_id() RETURNS uuid
  LANGUAGE sql STABLE AS $$ SELECT nullif(current_setting('app.user_id', true), '')::uuid $$;

CREATE FUNCTION app.touch_updated_at() RETURNS trigger
  LANGUAGE plpgsql AS $$ BEGIN NEW.updated_at := now(); RETURN NEW; END $$;

-- ---------------------------------------------------------------- tenants
CREATE TABLE tenants (
  id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  trade_name      text NOT NULL CHECK (char_length(trade_name) BETWEEN 2 AND 120),
  legal_name      text CHECK (char_length(legal_name) <= 160),
  person_type     text NOT NULL CHECK (person_type IN ('PF', 'PJ')),
  document_number text CHECK (document_number ~ '^[0-9]{11}$|^[0-9]{14}$'),
  status          text NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'suspended', 'closed')),
  created_at      timestamptz NOT NULL DEFAULT now(),
  updated_at      timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER tenants_touch BEFORE UPDATE ON tenants FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

-- ---------------------------------------------------------------- units
CREATE TABLE units (
  id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id    uuid NOT NULL REFERENCES tenants (id),
  name         text NOT NULL CHECK (char_length(name) BETWEEN 2 AND 120),
  timezone     text NOT NULL DEFAULT 'America/Sao_Paulo',
  address_line text,
  city         text,
  state        char(2),
  created_at   timestamptz NOT NULL DEFAULT now(),
  updated_at   timestamptz NOT NULL DEFAULT now(),
  UNIQUE (tenant_id, id)
);
CREATE UNIQUE INDEX units_tenant_name_uq ON units (tenant_id, lower(name));
CREATE TRIGGER units_touch BEFORE UPDATE ON units FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

-- ---------------------------------------------------------------- users (identidade global)
CREATE TABLE users (
  id                 uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  email              text NOT NULL CHECK (email = lower(email) AND char_length(email) <= 254),
  name               text NOT NULL CHECK (char_length(name) BETWEEN 2 AND 120),
  crmv               text CHECK (char_length(crmv) <= 30),
  password_hash      text NOT NULL,
  status             text NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'disabled')),
  failed_login_count integer NOT NULL DEFAULT 0,
  locked_until       timestamptz,
  created_at         timestamptz NOT NULL DEFAULT now(),
  updated_at         timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX users_email_uq ON users (email);
CREATE TRIGGER users_touch BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

-- ---------------------------------------------------------------- memberships (vínculo usuário × tenant)
CREATE TABLE memberships (
  id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id  uuid NOT NULL REFERENCES tenants (id),
  user_id    uuid NOT NULL REFERENCES users (id),
  roles      text[] NOT NULL CHECK (
    cardinality(roles) > 0 AND roles <@ ARRAY['tenant_owner', 'tenant_manager', 'veterinarian',
      'clinical_assistant', 'receptionist', 'finance', 'stock', 'pet_services']::text[]
  ),
  status     text NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'invited', 'disabled')),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (tenant_id, user_id),
  UNIQUE (tenant_id, id)
);
CREATE INDEX memberships_user_idx ON memberships (user_id);
CREATE TRIGGER memberships_touch BEFORE UPDATE ON memberships FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

-- Restrição opcional de unidades por vínculo (vazio = todas as unidades do tenant).
-- FKs compostas impedem ligar vínculo de um tenant a unidade de outro.
CREATE TABLE membership_units (
  tenant_id     uuid NOT NULL,
  membership_id uuid NOT NULL,
  unit_id       uuid NOT NULL,
  PRIMARY KEY (membership_id, unit_id),
  FOREIGN KEY (tenant_id, membership_id) REFERENCES memberships (tenant_id, id) ON DELETE CASCADE,
  FOREIGN KEY (tenant_id, unit_id) REFERENCES units (tenant_id, id)
);

-- ---------------------------------------------------------------- sessões
CREATE TABLE user_sessions (
  id                   uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id              uuid NOT NULL REFERENCES users (id),
  token_hash           bytea NOT NULL UNIQUE,
  csrf_token           text NOT NULL,
  active_tenant_id     uuid,
  active_membership_id uuid,
  active_unit_id       uuid,
  user_agent           text CHECK (char_length(user_agent) <= 300),
  created_at           timestamptz NOT NULL DEFAULT now(),
  last_seen_at         timestamptz NOT NULL DEFAULT now(),
  expires_at           timestamptz NOT NULL,
  revoked_at           timestamptz,
  FOREIGN KEY (active_tenant_id, active_membership_id) REFERENCES memberships (tenant_id, id),
  FOREIGN KEY (active_tenant_id, active_unit_id) REFERENCES units (tenant_id, id)
);
CREATE INDEX user_sessions_user_idx ON user_sessions (user_id);

-- ---------------------------------------------------------------- tema por tenant (tokens, sem CSS por cliente)
CREATE TABLE tenant_themes (
  tenant_id       uuid PRIMARY KEY REFERENCES tenants (id),
  primary_color   text NOT NULL CHECK (primary_color ~ '^#[0-9a-fA-F]{6}$'),
  secondary_color text NOT NULL CHECK (secondary_color ~ '^#[0-9a-fA-F]{6}$'),
  mode            text NOT NULL DEFAULT 'system' CHECK (mode IN ('light', 'dark', 'system')),
  updated_by      uuid REFERENCES users (id),
  updated_at      timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER tenant_themes_touch BEFORE UPDATE ON tenant_themes FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

-- ---------------------------------------------------------------- auditoria (append-only)
CREATE TABLE audit_events (
  id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  tenant_id     uuid REFERENCES tenants (id),
  actor_user_id uuid REFERENCES users (id),
  action        text NOT NULL CHECK (action ~ '^[a-z_]+\.[a-z_.]+$'),
  target_type   text,
  target_id     text,
  metadata      jsonb NOT NULL DEFAULT '{}'::jsonb,
  request_id    text,
  created_at    timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX audit_events_tenant_created_idx ON audit_events (tenant_id, created_at DESC);

CREATE FUNCTION app.audit_immutable() RETURNS trigger
  LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'audit_events é somente inclusão (append-only)'; END $$;
CREATE TRIGGER audit_events_no_update BEFORE UPDATE OR DELETE ON audit_events
  FOR EACH ROW EXECUTE FUNCTION app.audit_immutable();

-- ================================================================ RLS
ALTER TABLE tenants          ENABLE ROW LEVEL SECURITY;
ALTER TABLE units            ENABLE ROW LEVEL SECURITY;
ALTER TABLE users            ENABLE ROW LEVEL SECURITY;
ALTER TABLE memberships      ENABLE ROW LEVEL SECURITY;
ALTER TABLE membership_units ENABLE ROW LEVEL SECURITY;
ALTER TABLE user_sessions    ENABLE ROW LEVEL SECURITY;
ALTER TABLE tenant_themes    ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_events     ENABLE ROW LEVEL SECURITY;

CREATE POLICY tenants_isolation ON tenants
  USING (id = app.current_tenant_id()) WITH CHECK (id = app.current_tenant_id());
CREATE POLICY units_isolation ON units
  USING (tenant_id = app.current_tenant_id()) WITH CHECK (tenant_id = app.current_tenant_id());
CREATE POLICY memberships_isolation ON memberships
  USING (tenant_id = app.current_tenant_id()) WITH CHECK (tenant_id = app.current_tenant_id());
CREATE POLICY membership_units_isolation ON membership_units
  USING (tenant_id = app.current_tenant_id()) WITH CHECK (tenant_id = app.current_tenant_id());
CREATE POLICY tenant_themes_isolation ON tenant_themes
  USING (tenant_id = app.current_tenant_id()) WITH CHECK (tenant_id = app.current_tenant_id());

-- Usuário enxerga a si mesmo e os membros do tenant ativo; só pode alterar/criar a si mesmo.
CREATE POLICY users_select ON users FOR SELECT USING (
  id = app.current_user_id()
  OR EXISTS (SELECT 1 FROM memberships m WHERE m.user_id = users.id AND m.tenant_id = app.current_tenant_id())
);
CREATE POLICY users_insert ON users FOR INSERT WITH CHECK (id = app.current_user_id());
CREATE POLICY users_update ON users FOR UPDATE
  USING (id = app.current_user_id()) WITH CHECK (id = app.current_user_id());

CREATE POLICY user_sessions_own ON user_sessions
  USING (user_id = app.current_user_id()) WITH CHECK (user_id = app.current_user_id());

-- Eventos sem tenant (ex.: falha de login) podem ser inseridos, mas nunca lidos pela aplicação.
CREATE POLICY audit_select ON audit_events FOR SELECT USING (tenant_id = app.current_tenant_id());
CREATE POLICY audit_insert ON audit_events FOR INSERT
  WITH CHECK (tenant_id IS NULL OR tenant_id = app.current_tenant_id());

-- ================================================================ funções de autenticação (SECURITY DEFINER)
-- Escopo mínimo e explícito para operações anteriores à seleção de contexto.

CREATE FUNCTION app.auth_find_user(p_email text)
  RETURNS TABLE (id uuid, name text, password_hash text, status text, locked_until timestamptz)
  LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public, pg_temp AS $$
    SELECT u.id, u.name, u.password_hash, u.status, u.locked_until FROM users u WHERE u.email = lower(p_email)
  $$;

CREATE FUNCTION app.auth_register_login_result(p_user_id uuid, p_success boolean, p_max_failures integer, p_lock_minutes integer)
  RETURNS void
  LANGUAGE plpgsql SECURITY DEFINER SET search_path = public, pg_temp AS $$
  BEGIN
    IF p_success THEN
      UPDATE users SET failed_login_count = 0, locked_until = NULL WHERE id = p_user_id;
    ELSE
      UPDATE users
         SET failed_login_count = failed_login_count + 1,
             locked_until = CASE WHEN failed_login_count + 1 >= p_max_failures
                                 THEN now() + make_interval(mins => p_lock_minutes) ELSE locked_until END
       WHERE id = p_user_id;
    END IF;
  END $$;

-- Resolve sessão válida + vínculo ativo (se houver), conferindo status do usuário, vínculo e tenant.
CREATE FUNCTION app.auth_resolve_session(p_token_hash bytea)
  RETURNS TABLE (session_id uuid, user_id uuid, user_name text, user_email text, csrf_token text,
                 tenant_id uuid, tenant_name text, membership_id uuid, unit_id uuid, roles text[], expires_at timestamptz)
  LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public, pg_temp AS $$
    SELECT s.id, u.id, u.name, u.email, s.csrf_token,
           t.id, t.trade_name, m.id, s.active_unit_id,
           CASE WHEN m.id IS NULL THEN ARRAY[]::text[] ELSE m.roles END,
           s.expires_at
      FROM user_sessions s
      JOIN users u ON u.id = s.user_id AND u.status = 'active'
      LEFT JOIN memberships m ON m.id = s.active_membership_id AND m.user_id = u.id AND m.status = 'active'
      LEFT JOIN tenants t ON t.id = m.tenant_id AND t.status = 'active'
     WHERE s.token_hash = p_token_hash AND s.revoked_at IS NULL AND s.expires_at > now()
  $$;

-- Vínculos ativos do usuário corrente (app.user_id) para a tela de seleção de contexto.
CREATE FUNCTION app.my_memberships()
  RETURNS TABLE (membership_id uuid, tenant_id uuid, tenant_name text, roles text[], units jsonb)
  LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public, pg_temp AS $$
    SELECT m.id, t.id, t.trade_name, m.roles,
           coalesce((
             SELECT jsonb_agg(jsonb_build_object('id', un.id, 'name', un.name) ORDER BY un.name)
               FROM units un
              WHERE un.tenant_id = t.id
                AND (NOT EXISTS (SELECT 1 FROM membership_units mu WHERE mu.membership_id = m.id)
                     OR EXISTS (SELECT 1 FROM membership_units mu WHERE mu.membership_id = m.id AND mu.unit_id = un.id))
           ), '[]'::jsonb)
      FROM memberships m
      JOIN tenants t ON t.id = m.tenant_id AND t.status = 'active'
     WHERE m.user_id = app.current_user_id() AND m.status = 'active'
     ORDER BY t.trade_name
  $$;

REVOKE ALL ON ALL FUNCTIONS IN SCHEMA app FROM PUBLIC;

-- ================================================================ privilégios do papel da aplicação
GRANT USAGE ON SCHEMA public, app TO {{APP_ROLE}};
GRANT EXECUTE ON FUNCTION app.current_tenant_id(), app.current_user_id(),
  app.auth_find_user(text), app.auth_register_login_result(uuid, boolean, integer, integer),
  app.auth_resolve_session(bytea), app.my_memberships() TO {{APP_ROLE}};
GRANT SELECT, INSERT, UPDATE ON tenants, units, users, memberships, tenant_themes, user_sessions TO {{APP_ROLE}};
GRANT SELECT, INSERT, DELETE ON membership_units TO {{APP_ROLE}};
GRANT SELECT, INSERT ON audit_events TO {{APP_ROLE}};
