import { Head, router } from "@inertiajs/react";
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
import { IconAlert, IconCheck, IconClock } from "@/Components/Icons";
import { angka } from "@/lib/format";

type Row = {
    id: number;
    employee: string | null;
    nik: string | null;
    department: string | null;
    typeLabel: string;
    startDate: string;
    endDate: string;
    totalDays: number;
    reason: string | null;
    status: string;
};

type Props = {
    requests: Paginated<Row>;
    filters: {
        status: string | null;
        leave_type: string | null;
        department_id: number | null;
        search: string | null;
    };
    options: {
        statuses: string[];
        leaveTypes: string[];
        departments: { id: number; name: string }[];
    };
    stats: { pending: number; approved: number; rejected: number };
};

const TYPE_LABELS: Record<string, string> = {
    annual: "Cuti Tahunan",
    sick: "Izin Sakit",
    unpaid: "Izin Tanpa Gaji",
    maternity: "Cuti Melahirkan",
    special: "Cuti Khusus",
};

export default function LeavesIndex({
    requests,
    filters,
    options,
    stats,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? "");

    function applyFilter(patch: Record<string, string | number | null>) {
        router.get(
            "/cuti",
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    }

    function decide(id: number, status: "approved" | "rejected") {
        router.patch(`/cuti/${id}`, { status }, { preserveScroll: true });
    }

    return (
        <AppLayout
            title="Approval Cuti & Izin"
            subtitle="Verifikasi pengajuan tim Anda"
            actions={
                <ExportMenu
                    targets={[
                        { label: "Rekap cuti & izin", url: "/export/cuti" },
                    ]}
                    params={filters}
                />
            }
        >
            <Head title="Approval Cuti" />

            <div className="space-y-5">
                <section className="grid gap-4 sm:grid-cols-3">
                    <StatTile
                        label="Menunggu persetujuan"
                        value={angka(stats.pending)}
                        caption="perlu tindakan Anda"
                        icon={<IconClock className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Disetujui"
                        value={angka(stats.approved)}
                        caption="sepanjang periode tercatat"
                        icon={<IconCheck className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Ditolak"
                        value={angka(stats.rejected)}
                        caption="sepanjang periode tercatat"
                        icon={<IconAlert className="h-4 w-4" />}
                    />
                </section>

                <Card
                    title="Daftar pengajuan"
                    subtitle={`${requests.total} pengajuan sesuai filter`}
                >
                    <div className="mb-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
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

                        <Select
                            value={filters.leave_type ?? ""}
                            onChange={(event) =>
                                applyFilter({
                                    leave_type: event.target.value || null,
                                })
                            }
                        >
                            <option value="">Semua jenis</option>
                            {options.leaveTypes.map((type) => (
                                <option key={type} value={type}>
                                    {TYPE_LABELS[type] ?? type}
                                </option>
                            ))}
                        </Select>

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
                    </div>

                    {requests.data.length === 0 ? (
                        <EmptyState message="Tidak ada pengajuan pada filter ini." />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[840px] text-sm">
                                <thead>
                                    <tr className="border-b border-hairline text-left text-xs text-ink-muted">
                                        <th className="pb-2 font-medium">
                                            Pemohon
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Jenis
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Tanggal
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            Hari
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Status
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            Tindakan
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {requests.data.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b border-hairline last:border-0"
                                        >
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
                                            <td className="py-2.5 text-ink-soft">
                                                {row.typeLabel}
                                                {row.reason && (
                                                    <p className="text-xs text-ink-muted">
                                                        {row.reason}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="tabular py-2.5 whitespace-nowrap text-ink-soft">
                                                {row.startDate} – {row.endDate}
                                            </td>
                                            <td className="tabular py-2.5 text-right text-ink">
                                                {row.totalDays}
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
                                            <td className="py-2.5">
                                                {row.status === "pending" ? (
                                                    <div className="flex justify-end gap-1.5">
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                decide(
                                                                    row.id,
                                                                    "approved",
                                                                )
                                                            }
                                                        >
                                                            Setujui
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="danger"
                                                            onClick={() =>
                                                                decide(
                                                                    row.id,
                                                                    "rejected",
                                                                )
                                                            }
                                                        >
                                                            Tolak
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <p className="text-right text-[11px] text-ink-muted">
                                                        selesai
                                                    </p>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <Pagination page={requests} />
                </Card>
            </div>
        </AppLayout>
    );
}
