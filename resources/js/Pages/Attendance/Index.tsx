import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import Card from "@/Components/Card";
import ExportMenu from "@/Components/ExportMenu";
import AppLayout from "@/Layouts/AppLayout";
import StatTile from "@/Components/StatTile";
import {
    Badge,
    EmptyState,
    Input,
    Pagination,
    Select,
    statusTone,
    type Paginated,
} from "@/Components/ui";
import { IconAlert, IconCheck, IconClock, IconShield } from "@/Components/Icons";
import { angka } from "@/lib/format";

type Row = {
    id: number;
    date: string;
    employee: string | null;
    nik: string | null;
    department: string | null;
    category: string | null;
    clockIn: string | null;
    clockOut: string | null;
    status: string;
    lateMinutes: number;
    workHours: number;
    isFakeGps: boolean;
};

type Props = {
    records: Paginated<Row>;
    filters: {
        from: string;
        to: string;
        search: string | null;
        department_id: number | null;
        category: string | null;
        status: string | null;
        fake_gps_only: boolean;
    };
    options: {
        departments: { id: number; name: string }[];
        statuses: string[];
        categories: string[];
    };
    stats: {
        present: number;
        late: number;
        absent: number;
        leave: number;
        fakeGps: number;
        rangeLabel: string;
    };
};

export default function AttendanceIndex({
    records,
    filters,
    options,
    stats,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? "");

    function applyFilter(patch: Record<string, string | number | boolean | null>) {
        router.get(
            "/absensi",
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    }

    return (
        <AppLayout
            title="Rekap Absensi"
            subtitle={stats.rangeLabel}
            actions={
                <ExportMenu
                    targets={[
                        { label: "Laporan absensi", url: "/export/absensi" },
                        {
                            label: "Laporan keterlambatan",
                            url: "/export/keterlambatan",
                        },
                        {
                            label: "Timesheet jam kerja mitra",
                            url: "/export/timesheet-mitra",
                        },
                    ]}
                    params={filters}
                />
            }
        >
            <Head title="Rekap Absensi" />

            <div className="space-y-5">
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Hadir tepat waktu"
                        value={angka(stats.present)}
                        caption="pada rentang terpilih"
                        icon={<IconCheck className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Terlambat"
                        value={angka(stats.late)}
                        caption="clock-in setelah 08:00"
                        icon={<IconClock className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Tanpa keterangan"
                        value={angka(stats.absent)}
                        caption="perlu tindak lanjut atasan"
                        icon={<IconAlert className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Flag fake GPS"
                        value={angka(stats.fakeGps)}
                        caption="menunggu verifikasi HR"
                        icon={<IconShield className="h-4 w-4" />}
                    />
                </section>

                <Card
                    title="Catatan kehadiran"
                    subtitle={`${records.total} record sesuai filter`}
                >
                    <div className="mb-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-6">
                        <Input
                            type="date"
                            value={filters.from}
                            onChange={(event) =>
                                applyFilter({ from: event.target.value })
                            }
                        />
                        <Input
                            type="date"
                            value={filters.to}
                            onChange={(event) =>
                                applyFilter({ to: event.target.value })
                            }
                        />
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
                        <Select
                            value={filters.department_id ?? ""}
                            onChange={(event) =>
                                applyFilter({
                                    department_id:
                                        Number(event.target.value) || null,
                                })
                            }
                        >
                            <option value="">Semua divisi</option>
                            {options.departments.map((department) => (
                                <option key={department.id} value={department.id}>
                                    {department.name}
                                </option>
                            ))}
                        </Select>
                        <Select
                            value={filters.category ?? ""}
                            onChange={(event) =>
                                applyFilter({
                                    category: event.target.value || null,
                                })
                            }
                        >
                            <option value="">Semua entitas</option>
                            {options.categories.map((category) => (
                                <option key={category} value={category}>
                                    {category}
                                </option>
                            ))}
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
                    </div>

                    <label className="mb-4 flex items-center gap-2 text-xs text-ink-soft">
                        <input
                            type="checkbox"
                            checked={filters.fake_gps_only}
                            onChange={(event) =>
                                applyFilter({
                                    fake_gps_only: event.target.checked,
                                })
                            }
                            className="h-3.5 w-3.5 rounded border-hairline-strong text-brand-500 focus:ring-brand-100"
                        />
                        Tampilkan hanya yang ditandai fake GPS
                    </label>

                    {records.data.length === 0 ? (
                        <EmptyState message="Tidak ada catatan pada filter ini." />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[820px] text-sm">
                                <thead>
                                    <tr className="border-b border-hairline text-left text-xs text-ink-muted">
                                        <th className="pb-2 font-medium">
                                            Tanggal
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Karyawan
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Masuk / Pulang
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            Jam kerja
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            Telat
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {records.data.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b border-hairline last:border-0"
                                        >
                                            <td className="tabular py-2.5 whitespace-nowrap text-ink-soft">
                                                {row.date}
                                            </td>
                                            <td className="py-2.5">
                                                <p className="font-medium text-ink">
                                                    {row.employee}
                                                </p>
                                                <p className="text-xs text-ink-muted">
                                                    {row.nik}
                                                    {row.department
                                                        ? ` · ${row.department}`
                                                        : ""}
                                                </p>
                                            </td>
                                            <td className="tabular py-2.5 whitespace-nowrap text-ink-soft">
                                                {row.clockIn ?? "—"} –{" "}
                                                {row.clockOut ?? "—"}
                                            </td>
                                            <td className="tabular py-2.5 text-right text-ink">
                                                {row.workHours > 0
                                                    ? `${row.workHours} j`
                                                    : "—"}
                                            </td>
                                            <td className="tabular py-2.5 text-right text-ink-soft">
                                                {row.lateMinutes > 0
                                                    ? `${row.lateMinutes} m`
                                                    : "—"}
                                            </td>
                                            <td className="py-2.5">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <Badge
                                                        tone={
                                                            statusTone[
                                                                row.status
                                                            ] ?? "neutral"
                                                        }
                                                    >
                                                        {row.status}
                                                    </Badge>
                                                    {row.isFakeGps && (
                                                        <Badge tone="critical">
                                                            <IconShield className="h-3 w-3" />
                                                            fake GPS
                                                        </Badge>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <Pagination page={records} />
                </Card>
            </div>
        </AppLayout>
    );
}
