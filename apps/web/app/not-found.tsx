import Link from 'next/link';

export default function NotFound() {
  return (
    <main
      id="conteudo"
      className="mx-auto flex min-h-dvh max-w-md flex-col items-center justify-center gap-3 px-6 text-center"
    >
      <p className="text-sm font-semibold text-muted">404</p>
      <h1 className="text-2xl font-semibold">Página não encontrada</h1>
      <p className="text-muted">O endereço pode ter mudado ou você não tem acesso a ele.</p>
      <Link href="/painel" className="font-semibold text-link underline underline-offset-4">
        Voltar ao painel
      </Link>
    </main>
  );
}
