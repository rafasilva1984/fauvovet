'use client';
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import type { MeResponse, Permission, TenantDto } from '@fauvovet/contracts';
import { ApiRequestError, api, setCsrfToken } from './api';
import { applyTheme, resetTheme } from './theme';

export type TenantInfo = TenantDto & { counts: { units: number; members: number } };

interface SessionValue {
  me: MeResponse | null;
  tenant: TenantInfo | null;
  status: 'loading' | 'ready' | 'unauthenticated' | 'error';
  error: string | null;
  refresh: () => Promise<void>;
  setMe: (me: MeResponse) => void;
  setTenant: (t: TenantInfo) => void;
  logout: () => Promise<void>;
  can: (p: Permission) => boolean;
}

const SessionContext = createContext<SessionValue | null>(null);

export function SessionProvider({ children }: { children: ReactNode }) {
  const [me, setMeState] = useState<MeResponse | null>(null);
  const [tenant, setTenantState] = useState<TenantInfo | null>(null);
  const [status, setStatus] = useState<SessionValue['status']>('loading');
  const [error, setError] = useState<string | null>(null);

  const setMe = useCallback((value: MeResponse) => {
    setCsrfToken(value.csrfToken);
    setMeState(value);
  }, []);

  const setTenant = useCallback((t: TenantInfo) => {
    setTenantState(t);
    applyTheme(t.theme);
  }, []);

  const refresh = useCallback(async () => {
    try {
      const m = await api<MeResponse>('/auth/me');
      setMe(m);
      if (m.context) setTenant(await api<TenantInfo>('/tenant'));
      setStatus('ready');
      setError(null);
    } catch (e) {
      if (e instanceof ApiRequestError && e.status === 401) {
        setStatus('unauthenticated');
        return;
      }
      setError(e instanceof Error ? e.message : 'Falha ao carregar a sessão.');
      setStatus('error');
    }
  }, [setMe, setTenant]);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  const logout = useCallback(async () => {
    try {
      await api('/auth/logout', { method: 'POST' });
    } finally {
      setCsrfToken(null);
      resetTheme();
      setMeState(null);
      setTenantState(null);
      setStatus('unauthenticated');
    }
  }, []);

  const can = useCallback((p: Permission) => !!me?.context?.permissions.includes(p), [me]);

  const value = useMemo(
    () => ({ me, tenant, status, error, refresh, setMe, setTenant, logout, can }),
    [me, tenant, status, error, refresh, setMe, setTenant, logout, can],
  );
  return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}

export function useSession(): SessionValue {
  const v = useContext(SessionContext);
  if (!v) throw new Error('useSession fora de SessionProvider');
  return v;
}
