import { AppShell } from '@/components/app-shell';
import { SessionProvider } from '@/lib/session';

export default function AuthenticatedLayout({ children }: { children: React.ReactNode }) {
  return (
    <SessionProvider>
      <AppShell>{children}</AppShell>
    </SessionProvider>
  );
}
