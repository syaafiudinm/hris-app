import { Head, Link, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import Card from "@/Components/Card";
import ExportMenu from "@/Components/ExportMenu";
import AppLayout from "@/Layouts/AppLayout";
import StatTile from "@/Components/StatTile";
import {
    Badge,
    Button,
    EmptyState,
    Input,
    Pagination,
    Select,
    statusTone,
    type Paginated,
} from "@/Components/ui";
import { IconShield, IconWallet } from "@/Components/Icons";
import { angka, rupiah, rupiahCompact } from "@/lib/format";

const MONTHS = [
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
];

type Row = {
    id: number;
    employee: string | null;
    nik: string | null;
    department: string | null;
    type: string | null;
    payoutType: "employee" | "mitra";
    slipType: "salary" | "incentive";
    gross: number;
    bpjs: number;
    pph: number;
    net: number;
    status: string;
};

type Props = {
    payrolls: Paginated<Row>;
    filters: {
        year: number;
        month: number;
        search: string | null;
        payout_type: string | null;
        status: string | null;
    };
    summary: {
        employeeNet: number;
        mitraNet: number;
        employeeCount: number;
        mitraCount: number;
        companyBpjs: number;
        totalPph: number;
        periodLabel: string;
    };
    options: { years: number[]; statuses: string[] };
};

export default function PayrollIndex({
    payrolls,
    filters,
    summary,
    options,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? "");
    const runForm = useForm({
        year: filters.year,
        month: filters.month,
        overwrite_paid: false,
    });

    function applyFilter(patch: Record<string, string | number | null>) {
        router.get(
            "/payroll",
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    }

    return (
        <AppLayout
            title="Payroll & Kompensasi"
            subtitle={`Periode ${summary.periodLabel}`}
            actions={
                <ExportMenu
                    targets={[
                        { label: "Rekap gaji bulanan", url: "/export/payroll" },
                        {
                            label: "File transfer bank",
                            url: "/export/payroll-bank",
                            formats: ["csv"],
                        },
                        {
                            label: "Rekap pajak PPh 21/23",
                            url: "/export/payroll-pajak",
                        },
                    ]}
                    params={{ year: filters.year, month: filters.month }}
                />
            }
        >
            <Head title="Payroll" />

            <div className="space-y-5">
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Payroll karyawan"
                        value={rupiahCompact(summary.employeeNet)}
                        caption={`${angka(summary.employeeCount)} slip`}
                        icon={<IconWallet className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Fee mitra"
                        value={rupiahCompact(summary.mitraNet)}
                        caption={`${angka(summary.mitraCount)} voucher`}
                        icon={<IconWallet className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Kontribusi BPJS perusahaan"
                        value={rupiahCompact(summary.companyBpjs)}
                        caption="di luar potongan pekerja"
                        icon={<IconShield className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Total PPh dipotong"
                        value={rupiahCompact(summary.totalPph)}
                        caption="PPh 21 TER + pajak mitra"
                        icon={<IconWallet className="h-4 w-4" />}
                    />
                </section>

                <Card
                    title="Jalankan periode penggajian"
                    subtitle="Slip berstatus paid tidak ditimpa kecuali dicentang"
                >
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            runForm.post("/payroll/run", {
                                preserveScroll: true,
                            });
                        }}
                        className="flex flex-wrap items-end gap-3"
                    >
                        <div className="w-40">
                            <span className="mb-1.5 block text-xs font-medium text-ink-soft">
                                Bulan
                            </span>
                            <Select
                                value={runForm.data.month}
                                onChange={(event) =>
                                    runForm.setData(
                                        "month",
                                        Number(event.target.value),
                                    )
                                }
                            >
                                {MONTHS.map((label, index) => (
                                    <option key={label} value={index + 1}>
                                        {label}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        <div className="w-32">
                            <span className="mb-1.5 block text-xs font-medium text-ink-soft">
                                Tahun
                            </span>
                            <Select
                                value={runForm.data.year}
                                onChange={(event) =>
                                    runForm.setData(
                                        "year",
                                        Number(event.target.value),
                                    )
                                }
                            >
                                {options.years.map((year) => (
                                    <option key={year} value={year}>
                                        {year}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        <label className="flex items-center gap-2 pb-2.5 text-xs text-ink-soft">
                            <input
                                type="checkbox"
                                checked={runForm.data.overwrite_paid}
                                onChange={(event) =>
                                    runForm.setData(
                                        "overwrite_paid",
                                        event.target.checked,
                                    )
                                }
                                className="h-3.5 w-3.5 rounded border-hairline-strong text-brand-500 focus:ring-brand-100"
                            />
                            Timpa slip yang sudah dibayar
                        </label>

                        <Button
                            type="submit"
                            disabled={runForm.processing}
                            className="mb-0.5"
                        >
                            {runForm.processing
                                ? "Menghitung…"
                                : "Jalankan payroll"}
                        </Button>
                    </form>
                </Card>

                <Card
                    title="Daftar slip"
                    subtitle={`${payrolls.total} slip pada periode ini`}
                >
                    <div className="mb-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
                        <Select
                            value={filters.month}
                            onChange={(event) =>
                                applyFilter({ month: Number(event.target.value) })
                            }
                        >
                            {MONTHS.map((label, index) => (
                                <option key={label} value={index + 1}>
                                    {label}
                                </option>
                            ))}
                        </Select>
                        <Select
                            value={filters.year}
                            onChange={(event) =>
                                applyFilter({ year: Number(event.target.value) })
                            }
                        >
                            {options.years.map((year) => (
                                <option key={year} value={year}>
                                    {year}
                                </option>
                            ))}
                        </Select>
                        <Select
                            value={filters.payout_type ?? ""}
                            onChange={(event) =>
                                applyFilter({
                                    payout_type: event.target.value || null,
                                })
                            }
                        >
                            <option value="">Karyawan & mitra</option>
                            <option value="employee">Karyawan</option>
                            <option value="mitra">Mitra</option>
                        </Select>
                        <Select
                            value={filters.status ?? ""}
                            onChange={(event) =>
                                applyFilter({
                                    status: event.target.value || null,
                                })
                            }
                        >
                            <option value="">Semua status</option>
                            {options.statuses.map((status) => (
                                <option key={status} value={status}>
                                    {status}
                                </option>
                            ))}
                        </Select>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                applyFilter({ search: search || null });
                            }}
                        >
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari nama / NIK…"
                            />
                        </form>
                    </div>

                    {payrolls.data.length === 0 ? (
                        <EmptyState message="Belum ada slip pada periode ini. Jalankan payroll terlebih dahulu." />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[860px] text-sm">
                                <thead>
                                    <tr className="border-b border-hairline text-left text-xs text-ink-muted">
                                        <th className="pb-2 font-medium">
                                            Karyawan
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            Bruto
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            BPJS
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            PPh
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            Netto
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Status
                                        </th>
                                        <th className="pb-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {payrolls.data.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b border-hairline last:border-0"
                                        >
                                            <td className="py-2.5">
                                                <Link
                                                    href={`/payroll/${row.id}`}
                                                    className="font-medium text-ink transition hover:text-brand-600"
                                                >
                                                    {row.employee}
                                                </Link>
                                                <p className="text-xs text-ink-muted">
                                                    {row.nik} · {row.type}
                                                </p>
                                                {row.slipType === "incentive" && (
                                                    <span className="mt-1 inline-flex rounded-md bg-brand-50 px-2 py-0.5 text-[10px] font-medium text-brand-700">
                                                        slip insentif penjualan
                                                    </span>
                                                )}
                                            </td>
                                            <td className="tabular py-2.5 text-right text-ink-soft">
                                                {rupiah(row.gross)}
                                            </td>
                                            <td className="tabular py-2.5 text-right text-ink-soft">
                                                {row.bpjs > 0
                                                    ? rupiah(row.bpjs)
                                                    : "—"}
                                            </td>
                                            <td className="tabular py-2.5 text-right text-ink-soft">
                                                {rupiah(row.pph)}
                                            </td>
                                            <td className="tabular py-2.5 text-right font-medium text-ink">
                                                {rupiah(row.net)}
                                            </td>
                                            <td className="py-2.5">
                                                <Badge
                                                    tone={
                                                        statusTone[row.status] ??
                                                        "neutral"
                                                    }
                                                >
                                                    {row.status}
                                                </Badge>
                                            </td>
                                            <td className="py-2.5 text-right">
                                                <a
                                                    href={`/slip-gaji/${row.id}/dokumen`}
                                                    className="text-[11px] font-medium text-brand-600 hover:text-brand-700"
                                                >
                                                    {row.slipType === "incentive"
                                                        ? "Voucher insentif"
                                                        : row.payoutType === "mitra"
                                                          ? "Voucher"
                                                          : "Slip"}{" "}
                                                    PDF
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <Pagination page={payrolls} />
                </Card>
            </div>
        </AppLayout>
    );
}
