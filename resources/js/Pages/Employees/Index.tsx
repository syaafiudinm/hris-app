import { Head, Link, router } from "@inertiajs/react";
import { useState } from "react";
import Card from "@/Components/Card";
import ExportMenu from "@/Components/ExportMenu";
import AppLayout from "@/Layouts/AppLayout";
import StatTile from "@/Components/StatTile";
import {
    Badge,
    EmptyState,
    Input,
    LinkButton,
    Pagination,
    Select,
    statusTone,
    type Paginated,
} from "@/Components/ui";
import { IconAlert, IconUsers } from "@/Components/Icons";
import { angka, rupiahCompact } from "@/lib/format";

type Row = {
    id: number;
    nik: string;
    name: string;
    email: string | null;
    position: string | null;
    department: string | null;
    type: string | null;
    category: string | null;
    contractEnd: string | null;
    daysLeft: number | null;
    salary: number;
    status: string;
};

type Props = {
    employees: Paginated<Row>;
    filters: {
        search: string | null;
        employment_type_id: number | null;
        department_id: number | null;
        category: string | null;
        status: string | null;
    };
    options: {
        employmentTypes: { id: number; name: string; code: string }[];
        departments: { id: number; name: string }[];
        statuses: string[];
    };
    stats: { total: number; active: number; expiring: number };
};

export default function EmployeesIndex({
    employees,
    filters,
    options,
    stats,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? "");

    function applyFilter(patch: Record<string, string | number | null>) {
        router.get(
            "/employees",
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    }

    return (
        <AppLayout
            title="Data Tenaga Kerja"
            subtitle="Data induk karyawan & mitra beserta aturan entitas kerjanya"
            actions={
                <div className="flex items-center gap-2">
                    <ExportMenu
                        targets={[
                            {
                                label: "Data induk tenaga kerja",
                                url: "/export/tenaga-kerja",
                            },
                            {
                                label: "Rekap kontrak expiring (H-30)",
                                url: "/export/kontrak-expiring",
                            },
                        ]}
                        params={filters}
                    />
                    <LinkButton href="/employees/create" variant="secondary">
                        Tambah
                    </LinkButton>
                </div>
            }
        >
            <Head title="Data Tenaga Kerja" />

            <div className="space-y-5">
                <section className="grid gap-4 sm:grid-cols-3">
                    <StatTile
                        label="Total tenaga kerja"
                        value={angka(stats.total)}
                        caption="termasuk non-aktif"
                        icon={<IconUsers className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Aktif"
                        value={angka(stats.active)}
                        caption="sedang dalam kontrak berjalan"
                        icon={<IconUsers className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Kontrak berakhir H-30"
                        value={angka(stats.expiring)}
                        caption="perlu evaluasi perpanjangan"
                        icon={<IconAlert className="h-4 w-4" />}
                    />
                </section>

                <Card
                    title="Daftar tenaga kerja"
                    subtitle={`${employees.total} data sesuai filter`}
                >
                    {/* Satu baris filter di atas seluruh tabel */}
                    <div className="mb-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                applyFilter({ search: search || null });
                            }}
                        >
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Cari nama, NIK, email…"
                            />
                        </form>

                        <Select
                            value={filters.category ?? ""}
                            onChange={(event) =>
                                applyFilter({
                                    category: event.target.value || null,
                                })
                            }
                        >
                            <option value="">Semua kategori</option>
                            <option value="probation">Probation</option>
                            <option value="pkwt">PKWT</option>
                            <option value="mitra">Mitra</option>
                        </Select>

                        <Select
                            value={filters.employment_type_id ?? ""}
                            onChange={(event) =>
                                applyFilter({
                                    employment_type_id:
                                        Number(event.target.value) || null,
                                })
                            }
                        >
                            <option value="">Semua entitas</option>
                            {options.employmentTypes.map((type) => (
                                <option key={type.id} value={type.id}>
                                    {type.name}
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

                    {employees.data.length === 0 ? (
                        <EmptyState message="Tidak ada data pada filter ini." />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[860px] text-sm">
                                <thead>
                                    <tr className="border-b border-hairline text-left text-xs text-ink-muted">
                                        <th className="pb-2 font-medium">Nama</th>
                                        <th className="pb-2 font-medium">
                                            Jabatan
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Entitas
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Kontrak
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            Gaji Pokok
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {employees.data.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b border-hairline last:border-0"
                                        >
                                            <td className="py-2.5">
                                                <Link
                                                    href={`/employees/${row.id}`}
                                                    className="font-medium text-ink transition hover:text-brand-600"
                                                >
                                                    {row.name}
                                                </Link>
                                                <p className="text-xs text-ink-muted">
                                                    {row.nik}
                                                    {row.department
                                                        ? ` · ${row.department}`
                                                        : ""}
                                                </p>
                                            </td>
                                            <td className="py-2.5 text-ink-soft">
                                                {row.position ?? "-"}
                                            </td>
                                            <td className="py-2.5">
                                                <Badge tone="brand">
                                                    {row.type}
                                                </Badge>
                                            </td>
                                            <td className="tabular py-2.5 whitespace-nowrap text-ink-soft">
                                                {row.contractEnd ?? "-"}
                                                {row.daysLeft !== null &&
                                                    row.daysLeft >= 0 &&
                                                    row.daysLeft <= 30 && (
                                                        <span className="ml-1.5 text-[11px] font-medium text-[#b53232]">
                                                            H-{row.daysLeft}
                                                        </span>
                                                    )}
                                            </td>
                                            <td className="tabular py-2.5 text-right text-ink">
                                                {row.category === "mitra"
                                                    ? "—"
                                                    : rupiahCompact(row.salary)}
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
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <Pagination page={employees} />
                </Card>
            </div>
        </AppLayout>
    );
}
