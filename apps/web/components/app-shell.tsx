'use client';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useState, type ComponentType, type ReactNode, type SVGProps } from 'react';
import type { Permission } from '@fauvovet/contracts';
import { useSession } from '@/lib/session';
import {
  BuildingIcon,
  CloseIcon,
  HomeIcon,
  LogoMark,
  LogoutIcon,
  MenuIcon,
  PaletteIcon,
  SwapIcon,
  UsersIcon,
} from './icons';
import { Alert, Button, Skeleton } from './ui';

interface NavItem {
  href: string;
  label: string;
  icon: ComponentType<SVGProps<SVGSVGElement>>;
  permission?: Permission;
}

const NAV: NavItem[] = [
  { href: '/painel', label: 'Painel', icon: HomeIcon },
  { href: '/unidades', label: 'Unidades', icon: BuildingIcon, permission: 'units:read' },
  { href: '/equipe', label: 'Equipe', icon: UsersIcon, permission: 'members:read' },
  {
    href: '/configuracoes/aparencia',
    label: 'Aparência',
    icon: PaletteIcon,
    permission: 'theme:update',
  },
];

export function AppShell({ children }: { children: ReactNode }) {
  const { me, tenant, status, error, can, logout, refresh } = useSession();
  const router = useRouter();
  const pathname = usePathname();
  const [menuOpen, setMenuOpen] = useState(false);

  useEffect(() => {
    if (status === 'unauthenticated') router.replace('/login');
    else if (status === 'ready' && !me?.context) router.replace('/contexto');
  }, [status, me, router]);

  useEffect(() => setMenuOpen(false), [pathname]);

  if (status === 'error') {
    return (
      <main
        id="conteudo"
        className="mx-auto flex min-h-dvh max-w-md flex-col justify-center gap-4 px-6"
      >
        <Alert>{error}</Alert>
        <Button onClick={() => void refresh()}>Tentar novamente</Button>
      </main>
    );
  }
  if (status !== 'ready' || !me?.context) {
    return (
      <div className="flex min-h-dvh" aria-busy="true" aria-label="Carregando">
        <div className="hidden w-64 border-r border-line bg-surface p-4 lg:block">
          <Skeleton className="h-9 w-36" />
        </div>
        <div className="flex-1 p-6">
          <Skeleton className="h-8 w-56" />
          <Skeleton className="mt-6 h-32" />
        </div>
      </div>
    );
  }

  const items = NAV.filter((n) => !n.permission || can(n.permission));
  const unitName = me.memberships
    .find((m) => m.membershipId === me.context!.membershipId)
    ?.units.find((u) => u.id === me.context!.unitId)?.name;
  const isActive = (href: string) => pathname === href || pathname.startsWith(`${href}/`);
  const doLogout = () => void logout().then(() => router.replace('/login'));
  const tenantName = tenant?.tradeName ?? me.context.tenantName;

  const contextBlock = (
    <div className="rounded-xl bg-surface-2 p-3">
      <p className="truncate text-sm font-semibold text-ink" title={tenantName}>
        {tenantName}
      </p>
      <p className="truncate text-xs text-muted">{unitName ?? 'Todas as unidades'}</p>
      {(me.memberships.length > 1 || (me.memberships[0]?.units.length ?? 0) > 1) && (
        <Link
          href="/contexto"
          className="mt-2 inline-flex min-h-8 items-center gap-1.5 text-xs font-semibold text-link"
        >
          <SwapIcon width={16} height={16} /> Trocar clínica/unidade
        </Link>
      )}
    </div>
  );

  return (
    <div className="min-h-dvh lg:grid lg:grid-cols-[16rem_minmax(0,1fr)]">
      {/* Navegação lateral (desktop) */}
      <aside className="sticky top-0 hidden h-dvh flex-col gap-6 border-r border-line bg-surface px-4 py-5 lg:flex">
        <Link href="/painel" className="flex items-center gap-2.5 px-2">
          <LogoMark size={34} />
          <span className="text-lg font-semibold tracking-tight">FauvoVet</span>
        </Link>
        {contextBlock}
        <nav aria-label="Principal" className="flex flex-1 flex-col gap-1">
          {items.map((n) => (
            <Link
              key={n.href}
              href={n.href}
              aria-current={isActive(n.href) ? 'page' : undefined}
              className={`flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition ${
                isActive(n.href) ? 'bg-brand text-on-brand' : 'text-ink hover:bg-surface-2'
              }`}
            >
              <n.icon /> {n.label}
            </Link>
          ))}
        </nav>
        <div className="border-t border-line pt-4">
          <p className="truncate px-2 text-sm font-medium">{me.user.name}</p>
          <p className="truncate px-2 text-xs text-muted">{me.user.email}</p>
          <Button variant="ghost" className="mt-2 w-full justify-start" onClick={doLogout}>
            <LogoutIcon /> Sair
          </Button>
        </div>
      </aside>

      {/* Barra superior (mobile/tablet) */}
      <header className="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-line bg-surface/95 px-4 py-2.5 backdrop-blur lg:hidden">
        <Link href="/painel" className="flex min-w-0 items-center gap-2.5">
          <LogoMark size={30} />
          <span className="min-w-0">
            <span className="block truncate text-sm font-semibold leading-tight">{tenantName}</span>
            <span className="block truncate text-xs text-muted">
              {unitName ?? 'Todas as unidades'}
            </span>
          </span>
        </Link>
        <button
          type="button"
          onClick={() => setMenuOpen(true)}
          aria-label="Abrir menu da conta"
          aria-expanded={menuOpen}
          aria-controls="menu-conta"
          className="flex size-11 shrink-0 items-center justify-center rounded-xl hover:bg-surface-2"
        >
          <MenuIcon />
        </button>
      </header>

      {menuOpen && (
        <div
          className="fixed inset-0 z-40 lg:hidden"
          role="dialog"
          aria-modal="true"
          aria-label="Conta"
          id="menu-conta"
        >
          <button
            type="button"
            aria-label="Fechar menu"
            className="absolute inset-0 bg-black/40"
            onClick={() => setMenuOpen(false)}
          />
          <div className="absolute inset-x-0 bottom-0 rounded-t-3xl border-t border-line bg-surface p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] shadow-card">
            <div className="mb-4 flex items-center justify-between">
              <div className="min-w-0">
                <p className="truncate font-semibold">{me.user.name}</p>
                <p className="truncate text-sm text-muted">{me.user.email}</p>
              </div>
              <button
                type="button"
                onClick={() => setMenuOpen(false)}
                aria-label="Fechar"
                className="flex size-11 items-center justify-center rounded-xl hover:bg-surface-2"
              >
                <CloseIcon />
              </button>
            </div>
            {contextBlock}
            <Button variant="secondary" className="mt-4 w-full" onClick={doLogout}>
              <LogoutIcon /> Sair
            </Button>
          </div>
        </div>
      )}

      <main
        id="conteudo"
        className="mx-auto w-full max-w-6xl px-4 pb-28 pt-6 sm:px-6 lg:px-10 lg:pb-12 lg:pt-10"
      >
        {children}
      </main>

      {/* Navegação inferior (mobile) */}
      <nav
        aria-label="Principal"
        className="fixed inset-x-0 bottom-0 z-30 grid border-t border-line bg-surface/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden"
        style={{ gridTemplateColumns: `repeat(${items.length}, minmax(0, 1fr))` }}
      >
        {items.map((n) => (
          <Link
            key={n.href}
            href={n.href}
            aria-current={isActive(n.href) ? 'page' : undefined}
            className={`flex min-h-14 flex-col items-center justify-center gap-0.5 text-[0.7rem] font-medium ${
              isActive(n.href) ? 'text-link' : 'text-muted'
            }`}
          >
            <n.icon />
            {n.label}
          </Link>
        ))}
      </nav>
    </div>
  );
}
