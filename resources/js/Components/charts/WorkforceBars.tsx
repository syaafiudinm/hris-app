import { useState } from "react";
import { angka } from "@/lib/format";
import { Tooltip, useTooltip } from "./Tooltip";
import ViewToggle from "./ViewToggle";

export type WorkforceRow = {
    label: string;
    code: string;
    category: string;
    value: number;
};

/**
 * Kategori entitas kerja bersifat nominal, jadi seluruh bar memakai satu
 * warna seri (slot biru) — panjang bar yang membawa nilainya.
 */
export default function WorkforceBars({ data }: { data: WorkforceRow[] }) {
    const [view, setView] = useState<"chart" | "table">("chart");
    const { tooltip, show, hide } = useTooltip();

    const total = data.reduce((sum, row) => sum + row.value, 0);
    const max = Math.max(...data.map((row) => row.value), 1);

    return (
        <div>
            <div className="mb-4 flex items-center justify-between gap-3">
                <p className="text-xs text-ink-muted">
                    {angka(total)} tenaga kerja aktif
                </p>
                <ViewToggle view={view} onChange={setView} />
            </div>

            {view === "table" ? (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-hairline text-left text-xs text-ink-muted">
                            <th className="pb-2 font-medium">Entitas</th>
                            <th className="pb-2 text-right font-medium">
                                Jumlah
                            </th>
                            <th className="pb-2 text-right font-medium">
                                Porsi
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.map((row) => (
                            <tr
                                key={row.code}
                                className="border-b border-hairline last:border-0"
                            >
                                <td className="py-2 text-ink">{row.label}</td>
                                <td className="tabular py-2 text-right text-ink">
                                    {angka(row.value)}
                                </td>
                                <td className="tabular py-2 text-right text-ink-soft">
                                    {((row.value / total) * 100).toFixed(1)}%
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : (
                <div data-chart-root className="relative space-y-3">
                    {data.map((row) => (
                        <div
                            key={row.code}
                            className="group flex items-center gap-3 py-0.5"
                            onMouseEnter={(event) =>
                                show(
                                    event,
                                    <div className="whitespace-nowrap">
                                        <p className="font-medium text-ink">
                                            {row.label}
                                        </p>
                                        <p className="tabular text-ink-soft">
                                            {angka(row.value)} orang ·{" "}
                                            {((row.value / total) * 100).toFixed(
                                                1,
                                            )}
                                            %
                                        </p>
                                    </div>,
                                )
                            }
                            onMouseLeave={hide}
                        >
                            <span className="w-32 shrink-0 truncate text-xs text-ink-soft">
                                {row.label}
                            </span>
                            <span className="flex min-w-0 flex-1 items-center gap-2">
                                <span
                                    className="h-5 rounded-r bg-brand-500 transition-opacity group-hover:opacity-90"
                                    style={{
                                        width: `${Math.max((row.value / max) * 100, 2)}%`,
                                    }}
                                />
                                <span className="tabular shrink-0 text-xs font-medium text-ink">
                                    {angka(row.value)}
                                </span>
                            </span>
                        </div>
                    ))}
                    <Tooltip state={tooltip} />
                </div>
            )}
        </div>
    );
}
