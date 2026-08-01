import { Link } from "@inertiajs/react";
import type {
    ButtonHTMLAttributes,
    InputHTMLAttributes,
    ReactNode,
    SelectHTMLAttributes,
    TextareaHTMLAttributes,
} from "react";

/* ------------------------------------------------------------------ Button */

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: "primary" | "secondary" | "ghost" | "danger";
    size?: "sm" | "md";
};

const buttonVariants = {
    primary: "bg-brand-500 text-white hover:bg-brand-600 disabled:bg-brand-200",
    secondary:
        "border border-hairline bg-surface text-ink-soft hover:bg-surface-soft",
    ghost: "text-ink-soft hover:bg-surface-soft",
    danger: "border border-hairline bg-surface text-[#d03b3b] hover:bg-[#fdf2f2]",
};

export function Button({
    variant = "primary",
    size = "md",
    className = "",
    ...props
}: ButtonProps) {
    const sizing = size === "sm" ? "px-2.5 py-1.5 text-[11px]" : "px-3.5 py-2 text-xs";

    return (
        <button
            {...props}
            className={`inline-flex items-center justify-center gap-1.5 rounded-lg font-medium transition disabled:cursor-not-allowed disabled:opacity-60 ${buttonVariants[variant]} ${sizing} ${className}`}
        />
    );
}

export function LinkButton({
    href,
    children,
    variant = "secondary",
    className = "",
}: {
    href: string;
    children: ReactNode;
    variant?: keyof typeof buttonVariants;
    className?: string;
}) {
    return (
        <Link
            href={href}
            className={`inline-flex items-center justify-center gap-1.5 rounded-lg px-3.5 py-2 text-xs font-medium transition ${buttonVariants[variant]} ${className}`}
        >
            {children}
        </Link>
    );
}

/* ------------------------------------------------------------------- Field */

export function Field({
    label,
    error,
    hint,
    required,
    children,
}: {
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
    children: ReactNode;
}) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-xs font-medium text-ink-soft">
                {label}
                {required && <span className="text-[#d03b3b]"> *</span>}
            </span>
            {children}
            {error ? (
                <span className="mt-1 block text-[11px] text-[#d03b3b]">
                    {error}
                </span>
            ) : (
                hint && (
                    <span className="mt-1 block text-[11px] text-ink-muted">
                        {hint}
                    </span>
                )
            )}
        </label>
    );
}

const controlClass =
    "w-full rounded-lg border border-hairline bg-surface px-3 py-2 text-sm text-ink outline-none transition placeholder:text-ink-muted focus:border-brand-400 focus:ring-2 focus:ring-brand-100";

export function Input({
    className = "",
    ...props
}: InputHTMLAttributes<HTMLInputElement>) {
    return <input {...props} className={`${controlClass} ${className}`} />;
}

export function Textarea({
    className = "",
    ...props
}: TextareaHTMLAttributes<HTMLTextAreaElement>) {
    return <textarea {...props} className={`${controlClass} ${className}`} />;
}

export function Select({
    className = "",
    children,
    ...props
}: SelectHTMLAttributes<HTMLSelectElement>) {
    return (
        <select {...props} className={`${controlClass} ${className}`}>
            {children}
        </select>
    );
}

/* ------------------------------------------------------------------- Badge */

const badgeTones = {
    neutral: "bg-surface-soft text-ink-soft",
    brand: "bg-brand-50 text-brand-700",
    good: "bg-[#effaef] text-[#0a7a0a]",
    warning: "bg-[#fdf6e6] text-[#8a6100]",
    critical: "bg-[#fdf2f2] text-[#b53232]",
};

export function Badge({
    children,
    tone = "neutral",
}: {
    children: ReactNode;
    tone?: keyof typeof badgeTones;
}) {
    return (
        <span
            className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-medium whitespace-nowrap ${badgeTones[tone]}`}
        >
            {children}
        </span>
    );
}

/** Peta status ke tone badge — dipakai lintas modul agar konsisten. */
export const statusTone: Record<string, keyof typeof badgeTones> = {
    active: "good",
    present: "good",
    approved: "good",
    paid: "good",
    late: "warning",
    pending: "warning",
    draft: "neutral",
    inactive: "neutral",
    leave: "brand",
    holiday: "brand",
    absent: "critical",
    rejected: "critical",
    expired: "critical",
    resigned: "critical",
};

/* -------------------------------------------------------------- Pagination */

export type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
};

export function Pagination<T>({ page }: { page: Paginated<T> }) {
    if (page.total === 0) return null;

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 pt-4">
            <p className="text-xs text-ink-muted">
                Menampilkan {page.from ?? 0}–{page.to ?? 0} dari {page.total}{" "}
                data
            </p>
            <div className="flex flex-wrap gap-1">
                {page.links.map((link, index) =>
                    link.url ? (
                        <Link
                            key={index}
                            href={link.url}
                            preserveScroll
                            className={`rounded-md px-2.5 py-1.5 text-[11px] font-medium transition ${
                                link.active
                                    ? "bg-brand-500 text-white"
                                    : "text-ink-soft hover:bg-surface-soft"
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ) : (
                        <span
                            key={index}
                            className="rounded-md px-2.5 py-1.5 text-[11px] text-ink-muted"
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ),
                )}
            </div>
        </div>
    );
}

/* ------------------------------------------------------------- Empty state */

export function EmptyState({ message }: { message: string }) {
    return (
        <p className="py-10 text-center text-sm text-ink-muted">{message}</p>
    );
}
