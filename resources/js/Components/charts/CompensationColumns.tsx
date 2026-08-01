import { useState } from "react";
import { compact, rupiah, rupiahCompact } from "@/lib/format";
import { Tooltip, useTooltip } from "./Tooltip";
import ViewToggle from "./ViewToggle";

export type CompensationRow = {
    label: string;
    fullLabel: string;
    employee: number;
    mitra: number;
};

// Dua seri pada satu ramp biru, jarak lightness lebar sehingga tetap
// terpisah pada simulasi buta warna (ΔE 18.9) dan keduanya >= 3:1.
const SERIES = {
    employee: { color: "#184f95", label: "Payroll Karyawan" },
    mitra: { color: "#3987e5", label: "Fee Mitra" },
};

export default function CompensationColumns({
    data,
}: {
    data: CompensationRow[];
}) {
    const [view, setView] = useState<"chart" | "table">("chart");
    const { tooltip, show, hide } = useTooltip();

    const totals = data.map((row) => row.employee + row.mitra);
    const axisMax = niceMax(Math.max(...totals, 1));
    const ticks = [1, 0.75, 0.5, 0.25, 0];

    return (
        <div>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <ul className="flex flex-wrap items-center gap-4">
                    {Object.values(SERIES).map((series) => (
                        <li
                            key={series.label}
                            className="flex items-center gap-1.5 text-xs text-ink-soft"
                        >
                            <span
                                className="h-2.5 w-2.5 rounded-sm"
                                style={{ background: series.color }}
                                aria-hidden
                            />
                            {series.label}
                        </li>
                    ))}
                </ul>
                <ViewToggle view={view} onChange={setView} />
            </div>

            {view === "table" ? (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[420px] text-sm">
                        <thead>
                            <tr className="border-b border-hairline text-left text-xs text-ink-muted">
                                <th className="pb-2 font-medium">Periode</th>
                                <th className="pb-2 text-right font-medium">
                                    Karyawan
                                </th>
                                <th className="pb-2 text-right font-medium">
                                    Mitra
                                </th>
                                <th className="pb-2 text-right font-medium">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.map((row) => (
                                <tr
                                    key={row.fullLabel}
                                    className="border-b border-hairline last:border-0"
                                >
                                    <td className="py-2 whitespace-nowrap text-ink">
                                        {row.fullLabel}
                                    </td>
                                    <td className="tabular py-2 text-right text-ink">
                                        {rupiahCompact(row.employee)}
                                    </td>
                                    <td className="tabular py-2 text-right text-ink">
                                        {rupiahCompact(row.mitra)}
                                    </td>
                                    <td className="tabular py-2 text-right font-medium text-ink">
                                        {rupiahCompact(row.employee + row.mitra)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div data-chart-root className="relative">
                    <div className="flex">
                        {/* Sumbu Y */}
                        <div className="relative h-56 w-14 shrink-0">
                            {ticks.map((tick) => (
                                <span
                                    key={tick}
                                    className="tabular absolute right-2 -translate-y-1/2 text-[10px] text-ink-muted"
                                    style={{ top: `${(1 - tick) * 100}%` }}
                                >
                                    {tick === 0 ? "0" : compact(axisMax * tick)}
                                </span>
                            ))}
                        </div>

                        {/* Area plot */}
                        <div className="relative h-56 flex-1">
                            {ticks.map((tick) => (
                                <span
                                    key={tick}
                                    className="absolute inset-x-0 h-px bg-hairline"
                                    style={{ top: `${(1 - tick) * 100}%` }}
                                    aria-hidden
                                />
                            ))}

                            <div className="absolute inset-0 flex items-end justify-between gap-1">
                                {data.map((row, index) => {
                                    const total = row.employee + row.mitra;
                                    const isLast = index === data.length - 1;

                                    return (
                                        <div
                                            key={row.fullLabel}
                                            className="group relative flex h-full flex-1 cursor-default items-end justify-center"
                                            onMouseEnter={(event) =>
                                                show(
                                                    event,
                                                    <div className="whitespace-nowrap">
                                                        <p className="font-medium text-ink">
                                                            {row.fullLabel}
                                                        </p>
                                                        <p className="tabular mt-1 text-ink-soft">
                                                            Karyawan:{" "}
                                                            {rupiah(
                                                                row.employee,
                                                            )}
                                                        </p>
                                                        <p className="tabular text-ink-soft">
                                                            Mitra:{" "}
                                                            {rupiah(row.mitra)}
                                                        </p>
                                                        <p className="tabular mt-1 font-medium text-ink">
                                                            Total:{" "}
                                                            {rupiah(total)}
                                                        </p>
                                                    </div>,
                                                )
                                            }
                                            onMouseLeave={hide}
                                        >
                                            {isLast && (
                                                <span className="tabular absolute -top-1 left-1/2 -translate-x-1/2 -translate-y-full whitespace-nowrap text-[10px] font-medium text-ink">
                                                    {rupiahCompact(total)}
                                                </span>
                                            )}

                                            <span
                                                className="flex w-6 max-w-full flex-col justify-end transition-opacity group-hover:opacity-90"
                                                style={{
                                                    height: `${(total / axisMax) * 100}%`,
                                                }}
                                            >
                                                {/* Segmen atas: ujung data membulat 4px */}
                                                <span
                                                    className="w-full rounded-t"
                                                    style={{
                                                        height: `${(row.mitra / Math.max(total, 1)) * 100}%`,
                                                        background:
                                                            SERIES.mitra.color,
                                                        // 2px jeda permukaan sebagai pemisah antar segmen
                                                        marginBottom: 2,
                                                    }}
                                                />
                                                <span
                                                    className="w-full flex-1"
                                                    style={{
                                                        background:
                                                            SERIES.employee
                                                                .color,
                                                    }}
                                                />
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>

                    {/* Sumbu X */}
                    <div className="flex">
                        <span className="w-14 shrink-0" />
                        <div className="flex flex-1 justify-between gap-1 pt-2">
                            {data.map((row) => (
                                <span
                                    key={row.fullLabel}
                                    className="flex-1 text-center text-[10px] text-ink-muted"
                                >
                                    {row.label}
                                </span>
                            ))}
                        </div>
                    </div>

                    <Tooltip state={tooltip} />
                </div>
            )}
        </div>
    );
}

/** Bulatkan batas atas sumbu ke angka bersih (1 / 2 / 2.5 / 5 × 10^n). */
function niceMax(value: number): number {
    const magnitude = 10 ** Math.floor(Math.log10(value));
    const scaled = value / magnitude;
    const step = scaled <= 1 ? 1 : scaled <= 2 ? 2 : scaled <= 2.5 ? 2.5 : scaled <= 5 ? 5 : 10;

    return step * magnitude;
}
