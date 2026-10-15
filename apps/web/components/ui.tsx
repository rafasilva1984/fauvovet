'use client';
import {
  forwardRef,
  useId,
  type ButtonHTMLAttributes,
  type InputHTMLAttributes,
  type ReactNode,
} from 'react';

const cx = (...c: (string | false | null | undefined)[]) => c.filter(Boolean).join(' ');

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
const VARIANTS: Record<Variant, string> = {
  primary: 'bg-brand text-on-brand hover:brightness-110 active:brightness-95 shadow-sm',
  secondary: 'bg-surface text-ink border border-line hover:bg-surface-2',
  ghost: 'text-ink hover:bg-surface-2',
  danger: 'bg-danger-bg text-danger border border-danger/30 hover:brightness-95',
};

export function Button({
  variant = 'primary',
  loading,
  className,
  children,
  disabled,
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: Variant; loading?: boolean }) {
  return (
    <button
      {...props}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      className={cx(
        'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold transition',
        'disabled:cursor-not-allowed disabled:opacity-60',
        VARIANTS[variant],
        className,
      )}
    >
      {loading && <Spinner />}
      {children}
    </button>
  );
}

export function Spinner({ label }: { label?: string }) {
  return (
    <span role={label ? 'status' : undefined} className="inline-flex items-center gap-2">
      <span
        aria-hidden="true"
        className="size-4 animate-spin rounded-full border-2 border-current border-r-transparent"
      />
      {label && <span>{label}</span>}
    </span>
  );
}

interface FieldProps extends InputHTMLAttributes<HTMLInputElement> {
  label: string;
  hint?: string;
  error?: string;
  trailing?: ReactNode;
}

export const Field = forwardRef<HTMLInputElement, FieldProps>(function Field(
  { label, hint, error, trailing, className, id, ...props },
  ref,
) {
  const auto = useId();
  const inputId = id ?? auto;
  const hintId = `${inputId}-hint`;
  const errId = `${inputId}-err`;
  return (
    <div className={cx('flex flex-col gap-1.5', className)}>
      <label htmlFor={inputId} className="text-sm font-medium text-ink">
        {label}
        {props.required && (
          <span aria-hidden="true" className="text-muted">
            {' '}
            *
          </span>
        )}
      </label>
      <div className="relative">
        <input
          ref={ref}
          id={inputId}
          aria-invalid={error ? true : undefined}
          aria-describedby={cx(hint && hintId, error && errId) || undefined}
          className={cx(
            'min-h-11 w-full rounded-xl border bg-surface px-3.5 text-base text-ink placeholder:text-muted/70',
            'transition focus:border-brand focus:outline-none focus:ring-3 focus:ring-ring/40',
            error ? 'border-danger' : 'border-line',
            trailing ? 'pr-12' : '',
          )}
          {...props}
        />
        {trailing && <div className="absolute inset-y-0 right-1 flex items-center">{trailing}</div>}
      </div>
      {hint && !error && (
        <p id={hintId} className="text-xs text-muted">
          {hint}
        </p>
      )}
      {error && (
        <p id={errId} className="text-xs font-medium text-danger">
          {error}
        </p>
      )}
    </div>
  );
});

export function Alert({
  tone = 'danger',
  children,
}: {
  tone?: 'danger' | 'success' | 'warning' | 'info';
  children: ReactNode;
}) {
  const tones = {
    danger: 'bg-danger-bg text-danger border-danger/25',
    success: 'bg-success-bg text-success border-success/25',
    warning: 'bg-warning-bg text-warning border-warning/25',
    info: 'bg-surface-2 text-ink border-line',
  };
  return (
    <div
      role={tone === 'danger' ? 'alert' : 'status'}
      className={cx('rounded-xl border px-4 py-3 text-sm', tones[tone])}
    >
      {children}
    </div>
  );
}

export function Card({ className, children }: { className?: string; children: ReactNode }) {
  return (
    <div className={cx('rounded-2xl border border-line bg-surface p-5 shadow-card', className)}>
      {children}
    </div>
  );
}

export function Badge({
  children,
  tone = 'neutral',
}: {
  children: ReactNode;
  tone?: 'neutral' | 'brand';
}) {
  return (
    <span
      className={cx(
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
        tone === 'brand' ? 'bg-brand text-on-brand' : 'bg-surface-2 text-muted',
      )}
    >
      {children}
    </span>
  );
}

export function PageHeader({
  title,
  description,
  actions,
}: {
  title: string;
  description?: string;
  actions?: ReactNode;
}) {
  return (
    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-ink sm:text-[1.7rem]">{title}</h1>
        {description && <p className="mt-1 max-w-2xl text-sm text-muted">{description}</p>}
      </div>
      {actions && <div className="flex gap-2">{actions}</div>}
    </div>
  );
}

export function EmptyState({
  title,
  children,
  icon,
}: {
  title: string;
  children?: ReactNode;
  icon?: ReactNode;
}) {
  return (
    <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-line px-6 py-10 text-center">
      {icon && <div className="text-muted">{icon}</div>}
      <p className="font-semibold text-ink">{title}</p>
      {children && <div className="max-w-md text-sm text-muted">{children}</div>}
    </div>
  );
}

export function Skeleton({ className }: { className?: string }) {
  return (
    <div aria-hidden="true" className={cx('animate-pulse rounded-xl bg-surface-2', className)} />
  );
}
