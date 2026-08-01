import type { ReactNode } from "react";
import { IconArrowDown, IconArrowUp } from "@/Components/Icons";

type Props = {
    label: string;
    value: string;
    caption?: string;
    icon?: ReactNode;
    delta?: number | null;
    deltaSuffix?: string;
    /** Arah naik dianggap baik? Biaya naik = tidak baik. */
    upIsGood?: boolean;
};

export default function StatTile({
    label,
    value,
    caption,
    icon,
    delta = null,
    deltaSuffix = "vs bulan lalu",
    upIsGood = true,
}: Props) {
    const hasDelta = delta !== null && delta !== undefined;
    const isUp = hasDelta && delta > 0;
    const isGood = isUp === upIsGood;

    return (
        <div className="rounded-2xl border border-hairline bg-surface p-5">
            <div className="flex items-start justify-between gap-3">
                <p className="text-xs font-medium text-ink-soft">{label}</p>
                {icon && (
                    <span className="text-brand-500" aria-hidden>
                        {icon}
                    </span>
                )}
            </div>

            <p className="mt-3 text-[28px] font-semibold leading-none tracking-tight text-ink">
                {value}
            </p>

            <div className="mt-2 flex items-center gap-1.5 text-xs">
                {hasDelta && delta !== 0 && (
                    <span
                        className="inline-flex items-center gap-1 font-medium"
                        style={{ color: isGood ? "#006300" : "#d03b3b" }}
                    >
                        {isUp ? (
                            <IconArrowUp className="h-3.5 w-3.5" />
                        ) : (
                            <IconArrowDown className="h-3.5 w-3.5" />
                        )}
                        {Math.abs(delta).toString().replace(".", ",")}%
                    </span>
                )}
                <span className="text-ink-muted">
                    {hasDelta && delta !== 0 ? deltaSuffix : caption}
                </span>
            </div>
        </div>
    );
}
