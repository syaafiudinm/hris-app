import type { ReactNode } from "react";

type CardProps = {
    title?: string;
    subtitle?: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
};

export default function Card({
    title,
    subtitle,
    action,
    children,
    className = "",
}: CardProps) {
    return (
        <section
            className={`rounded-2xl border border-hairline bg-surface ${className}`}
        >
            {(title || action) && (
                <header className="flex flex-wrap items-start justify-between gap-3 px-5 pt-5">
                    <div>
                        {title && (
                            <h2 className="text-[15px] font-semibold tracking-tight text-ink">
                                {title}
                            </h2>
                        )}
                        {subtitle && (
                            <p className="mt-0.5 text-xs text-ink-soft">
                                {subtitle}
                            </p>
                        )}
                    </div>
                    {action}
                </header>
            )}
            <div className="p-5">{children}</div>
        </section>
    );
}
