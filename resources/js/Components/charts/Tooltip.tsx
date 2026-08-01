import { useCallback, useState, type ReactNode } from "react";

export type TooltipState = {
    x: number;
    y: number;
    content: ReactNode;
} | null;

export function useTooltip() {
    const [tooltip, setTooltip] = useState<TooltipState>(null);

    const show = useCallback(
        (event: { currentTarget: HTMLElement }, content: ReactNode) => {
            const target = event.currentTarget;
            const container = target.closest("[data-chart-root]");
            if (!(container instanceof HTMLElement)) return;

            const bounds = container.getBoundingClientRect();
            const rect = target.getBoundingClientRect();

            setTooltip({
                x: rect.left - bounds.left + rect.width / 2,
                y: rect.top - bounds.top,
                content,
            });
        },
        [],
    );

    const hide = useCallback(() => setTooltip(null), []);

    return { tooltip, show, hide };
}

export function Tooltip({ state }: { state: TooltipState }) {
    if (!state) return null;

    return (
        <div
            role="tooltip"
            className="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-lg border border-hairline bg-surface px-3 py-2 text-xs shadow-lg shadow-brand-700/5"
            style={{ left: state.x, top: state.y - 8 }}
        >
            {state.content}
        </div>
    );
}
