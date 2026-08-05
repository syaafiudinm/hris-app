import { Head, Link, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import AppLayout from "@/Layouts/AppLayout";
import Card from "@/Components/Card";
import ExportMenu from "@/Components/ExportMenu";
import StatTile from "@/Components/StatTile";
import {
    Badge,
    Button,
    EmptyState,
    Field,
    Input,
    Pagination,
    Select,
    Textarea,
    type Paginated,
} from "@/Components/ui";
import { IconAlert, IconCheck, IconDownload } from "@/Components/Icons";
import { angka } from "@/lib/format";

type ExitRow = {
    id: number;
    employeeId: number;
    name: string | null;
    nik: string | null;
    department: string | null;
    type: string | null;
    exitType: string;
    exitTypeLabel: string;
    submittedDate: string | null;
    lastWorkingDate: string;
    lastWorkingLabel: string;
    tenure: string;
    reason: string | null;
    notes: string | null;
    status: string;
    paklaringNumber: string | null;
    paklaringIssuedAt: string | null;
    /** Pinjaman inventaris yang belum tuntas — penghambat clearance. */
    openLoans: number;
};

type EligibleEmployee = {
    id: number;
    label: string;
    department: string | null;
    type: string | null;
    contractEnd: string | null;
};

type Options = {
    exitTypes: string[];
    exitTypeLabels: Record<string, string>;
    eligibleEmployees: EligibleEmployee[];
};

type Props = {
    exits: Paginated<ExitRow>;
    filters: {
        status: string | null;
        exit_type: string | null;
        search: string | null;
    };
    options: Options;
    stats: {
        draft: number;
        completed: number;
        issued: number;
        expiringSoon: number;
    };
};

export default function ExitsIndex({ exits, filters, options, stats }: Props) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [editing, setEditing] = useState<ExitRow | null>(null);
    const [creating, setCreating] = useState(false);

    function applyFilter(patch: Record<string, string | null>) {
        router.get(
            "/proses-keluar",
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    }

    return (
        <AppLayout
            title="Proses Keluar & Paklaring"
            subtitle="Offboarding karyawan dan penerbitan surat keterangan kerja"
            actions={
                <div className="flex items-center gap-2">
                    <ExportMenu
                        targets={[
                            {
                                label: "Rekap proses keluar",
                                url: "/export/proses-keluar",
                            },
                        ]}
                        params={filters}
                    />
                    <Button
                        onClick={() => {
                            setEditing(null);
                            setCreating(true);
                        }}
                    >
                        Catat proses keluar
                    </Button>
                </div>
            }
        >
            <Head title="Proses Keluar & Paklaring" />

            <div className="space-y-5">
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Sedang diproses"
                        value={angka(stats.draft)}
                        caption="masih berstatus draft"
                        icon={<IconAlert className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Selesai"
                        value={angka(stats.completed)}
                        caption="karyawan resmi keluar"
                        icon={<IconCheck className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Paklaring terbit"
                        value={angka(stats.issued)}
                        caption="dapat dicetak ulang kapan pun"
                        icon={<IconDownload className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Kontrak habis H-30"
                        value={angka(stats.expiringSoon)}
                        caption="belum dibuatkan proses keluar"
                        icon={<IconAlert className="h-4 w-4" />}
                    />
                </section>

                <div className="grid gap-5 xl:grid-cols-3">
                    <div className="xl:col-span-2">
                        <Card
                            title="Daftar proses keluar"
                            subtitle={`${exits.total} catatan sesuai filter`}
                        >
                            <div className="mb-4 grid gap-2 sm:grid-cols-3">
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
                                    <option value="draft">draft</option>
                                    <option value="completed">completed</option>
                                </Select>

                                <Select
                                    value={filters.exit_type ?? ""}
                                    onChange={(event) =>
                                        applyFilter({
                                            exit_type:
                                                event.target.value || null,
                                        })
                                    }
                                >
                                    <option value="">Semua jenis</option>
                                    {options.exitTypes.map((type) => (
                                        <option key={type} value={type}>
                                            {options.exitTypeLabels[type] ??
                                                type}
                                        </option>
                                    ))}
                                </Select>
                            </div>

                            {exits.data.length === 0 ? (
                                <EmptyState message="Belum ada proses keluar yang dicatat." />
                            ) : (
                                <ul className="space-y-3">
                                    {exits.data.map((exit) => (
                                        <li
                                            key={exit.id}
                                            className="rounded-xl border border-hairline p-4"
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <Link
                                                        href={`/employees/${exit.employeeId}`}
                                                        className="font-medium text-ink transition hover:text-brand-600"
                                                    >
                                                        {exit.name}
                                                    </Link>
                                                    <p className="mt-0.5 text-xs text-ink-muted">
                                                        {exit.nik}
                                                        {exit.department
                                                            ? ` · ${exit.department}`
                                                            : ""}
                                                        {exit.type
                                                            ? ` · ${exit.type}`
                                                            : ""}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-1.5">
                                                    <Badge tone="brand">
                                                        {exit.exitTypeLabel}
                                                    </Badge>
                                                    <Badge
                                                        tone={
                                                            exit.status ===
                                                            "completed"
                                                                ? "good"
                                                                : "warning"
                                                        }
                                                    >
                                                        {exit.status}
                                                    </Badge>
                                                </div>
                                            </div>

                                            <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-ink-soft">
                                                <span>
                                                    Hari terakhir{" "}
                                                    {exit.lastWorkingLabel}
                                                </span>
                                                <span>
                                                    Masa kerja {exit.tenure}
                                                </span>
                                                {exit.paklaringNumber && (
                                                    <span className="tabular">
                                                        No.{" "}
                                                        {exit.paklaringNumber}
                                                    </span>
                                                )}
                                            </div>

                                            {exit.reason && (
                                                <p className="mt-2 text-xs text-ink-muted">
                                                    {exit.reason}
                                                </p>
                                            )}

                                            {exit.openLoans > 0 &&
                                                exit.status === "draft" && (
                                                    <p className="mt-2 flex items-center gap-1.5 text-xs text-[#b53232]">
                                                        <IconAlert className="h-3.5 w-3.5" />
                                                        Clearance tertahan:{" "}
                                                        {exit.openLoans}{" "}
                                                        peminjaman inventaris
                                                        belum dikembalikan.
                                                    </p>
                                                )}

                                            <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-hairline pt-3">
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    onClick={() => {
                                                        setCreating(false);
                                                        setEditing(exit);
                                                    }}
                                                >
                                                    Ubah
                                                </Button>

                                                {exit.status === "draft" ? (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            router.patch(
                                                                `/proses-keluar/${exit.id}/status`,
                                                                { status: "completed" },
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        Tuntaskan &amp; terbitkan
                                                    </Button>
                                                ) : (
                                                    <>
                                                        <a
                                                            href={`/proses-keluar/${exit.id}/paklaring`}
                                                            className="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-2.5 py-1.5 text-[11px] font-medium text-white transition hover:bg-brand-600"
                                                        >
                                                            <IconDownload className="h-3.5 w-3.5" />
                                                            Paklaring PDF
                                                        </a>
                                                        <Button
                                                            size="sm"
                                                            variant="secondary"
                                                            onClick={() => {
                                                                if (
                                                                    confirm(
                                                                        `Buka kembali proses keluar ${exit.name}? Status karyawan akan kembali aktif.`,
                                                                    )
                                                                ) {
                                                                    router.patch(
                                                                        `/proses-keluar/${exit.id}/status`,
                                                                        { status: "draft" },
                                                                        { preserveScroll: true },
                                                                    );
                                                                }
                                                            }}
                                                        >
                                                            Buka kembali
                                                        </Button>
                                                    </>
                                                )}

                                                {exit.status === "draft" && (
                                                    <Button
                                                        size="sm"
                                                        variant="danger"
                                                        className="ml-auto"
                                                        onClick={() => {
                                                            if (
                                                                confirm(
                                                                    `Hapus draft proses keluar ${exit.name}?`,
                                                                )
                                                            ) {
                                                                router.delete(
                                                                    `/proses-keluar/${exit.id}`,
                                                                    { preserveScroll: true },
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        Hapus
                                                    </Button>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            <Pagination page={exits} />
                        </Card>
                    </div>

                    {creating || editing ? (
                        <ExitForm
                            key={editing?.id ?? "baru"}
                            exit={editing}
                            options={options}
                            onClose={() => {
                                setCreating(false);
                                setEditing(null);
                            }}
                        />
                    ) : (
                        <Card
                            title="Alur proses keluar"
                            subtitle="Dua tahap, agar surat tidak terbit prematur"
                        >
                            <ol className="space-y-3 text-xs text-ink-soft">
                                <li>
                                    <span className="font-medium text-ink">
                                        1. Draft
                                    </span>{" "}
                                    — catat jenis, hari kerja terakhir, dan
                                    alasannya. Karyawan masih berstatus aktif dan
                                    data masih bisa diperbaiki.
                                </li>
                                <li>
                                    <span className="font-medium text-ink">
                                        2. Tuntaskan
                                    </span>{" "}
                                    — status karyawan berubah otomatis
                                    (<em>resigned</em> atau <em>expired</em>),
                                    nomor paklaring terbit, dan PDF siap diunduh.
                                </li>
                            </ol>

                            <p className="mt-4 border-t border-hairline pt-3 text-[11px] text-ink-muted">
                                Nomor paklaring diterbitkan sekali dan tidak
                                berubah. Mantan karyawan sering meminta surat ini
                                lagi bertahun-tahun kemudian — untuk klaim JHT,
                                melamar kerja, atau pengajuan kredit — dan
                                cetakan ulang harus menghasilkan nomor yang sama.
                            </p>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

function ExitForm({
    exit,
    options,
    onClose,
}: {
    exit: ExitRow | null;
    options: Options;
    onClose: () => void;
}) {
    const isNew = exit === null;

    const { data, setData, post, patch, processing, errors } = useForm({
        employee_id: exit?.employeeId ?? "",
        exit_type: exit?.exitType ?? "resign",
        submitted_date: exit?.submittedDate ?? "",
        last_working_date: exit?.lastWorkingDate ?? "",
        reason: exit?.reason ?? "",
        notes: exit?.notes ?? "",
    });

    const selected = options.eligibleEmployees.find(
        (item) => item.id === Number(data.employee_id),
    );

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (isNew) {
            post("/proses-keluar", {
                preserveScroll: true,
                onSuccess: onClose,
            });
        } else {
            patch(`/proses-keluar/${exit.id}`, {
                preserveScroll: true,
                onSuccess: onClose,
            });
        }
    }

    return (
        <Card
            title={isNew ? "Catat proses keluar" : `Ubah — ${exit.name}`}
            subtitle={
                isNew
                    ? "Hanya karyawan aktif yang belum punya catatan keluar"
                    : undefined
            }
        >
            <form onSubmit={submit} className="space-y-4">
                {isNew && (
                    <Field
                        label="Karyawan"
                        error={errors.employee_id}
                        required
                        hint={
                            selected?.contractEnd
                                ? `Kontrak berakhir ${selected.contractEnd}.`
                                : undefined
                        }
                    >
                        <Select
                            value={data.employee_id}
                            onChange={(event) =>
                                setData(
                                    "employee_id",
                                    Number(event.target.value) || "",
                                )
                            }
                            required
                        >
                            <option value="">— pilih karyawan —</option>
                            {options.eligibleEmployees.map((employee) => (
                                <option key={employee.id} value={employee.id}>
                                    {employee.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                )}

                <Field label="Jenis" error={errors.exit_type} required>
                    <Select
                        value={data.exit_type}
                        onChange={(event) =>
                            setData("exit_type", event.target.value)
                        }
                    >
                        {options.exitTypes.map((type) => (
                            <option key={type} value={type}>
                                {options.exitTypeLabels[type] ?? type}
                            </option>
                        ))}
                    </Select>
                </Field>

                <Field
                    label="Tanggal pengajuan"
                    error={errors.submitted_date}
                    hint="Untuk pengunduran diri; kosongkan bila tidak relevan."
                >
                    <Input
                        type="date"
                        value={data.submitted_date}
                        onChange={(event) =>
                            setData("submitted_date", event.target.value)
                        }
                    />
                </Field>

                <Field
                    label="Hari kerja terakhir"
                    error={errors.last_working_date}
                    required
                    hint="Dipakai menghitung masa kerja pada paklaring."
                >
                    <Input
                        type="date"
                        value={data.last_working_date}
                        onChange={(event) =>
                            setData("last_working_date", event.target.value)
                        }
                    />
                </Field>

                <Field label="Alasan" error={errors.reason}>
                    <Textarea
                        rows={3}
                        value={data.reason}
                        onChange={(event) =>
                            setData("reason", event.target.value)
                        }
                        placeholder="Melanjutkan studi, pindah kota, dll."
                    />
                </Field>

                <Field
                    label="Catatan internal"
                    error={errors.notes}
                    hint="Tidak dicetak pada paklaring."
                >
                    <Textarea
                        rows={3}
                        value={data.notes}
                        onChange={(event) => setData("notes", event.target.value)}
                        placeholder="Hasil exit interview, serah terima pekerjaan…"
                    />
                </Field>

                <div className="flex items-center gap-2 pt-1">
                    <Button type="submit" disabled={processing}>
                        {processing ? "Menyimpan…" : "Simpan draft"}
                    </Button>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                </div>
            </form>
        </Card>
    );
}
