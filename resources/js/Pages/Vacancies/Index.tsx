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
    Field,
    Input,
    Pagination,
    Select,
    Textarea,
    type Paginated,
} from "@/Components/ui";
import { IconFunnel, IconUsers } from "@/Components/Icons";
import { angka } from "@/lib/format";

type Vacancy = {
    id: number;
    title: string;
    category: string;
    department_id: number | null;
    department: string | null;
    location: string | null;
    description: string | null;
    requirements: string | null;
    quota: number;
    status: string;
    publishedAt: string | null;
    publishedAtRaw: string | null;
    applicantCount: number;
    hiredCount: number;
};

type Props = {
    vacancies: Paginated<Vacancy>;
    filters: {
        search: string | null;
        status: string | null;
        category: string | null;
        department_id: number | null;
    };
    options: {
        departments: { id: number; name: string }[];
        statuses: string[];
        categories: string[];
    };
    stats: {
        total: number;
        open: number;
        draft: number;
        totalApplicants: number;
    };
};

const CATEGORY_LABELS: Record<string, string> = {
    probation: "Probation Track",
    pkwt: "Full-time PKWT",
    mitra: "Mitra / Freelance",
};

const STATUS_TONE: Record<string, "good" | "neutral" | "critical"> = {
    open: "good",
    draft: "neutral",
    closed: "critical",
};

const EMPTY_FORM = {
    id: 0,
    title: "",
    category: "pkwt",
    department_id: null,
    department: null,
    location: "",
    description: "",
    requirements: "",
    quota: 1,
    status: "draft",
    publishedAt: null,
    publishedAtRaw: null,
    applicantCount: 0,
    hiredCount: 0,
} satisfies Vacancy;

export default function VacanciesIndex({
    vacancies,
    filters,
    options,
    stats,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [editing, setEditing] = useState<Vacancy | null>(null);

    function applyFilter(patch: Record<string, string | number | null>) {
        router.get(
            "/lowongan",
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    }

    return (
        <AppLayout
            title="Manajemen Lowongan"
            subtitle="Sumber data Portal Karier Publik dan pipeline rekrutmen"
            actions={
                <div className="flex items-center gap-2">
                    <ExportMenu
                        targets={[
                            {
                                label: "Performa lowongan",
                                url: "/export/lowongan-performa",
                            },
                        ]}
                    />
                    <Button onClick={() => setEditing({ ...EMPTY_FORM })}>
                        Buat lowongan
                    </Button>
                </div>
            }
        >
            <Head title="Manajemen Lowongan" />

            <div className="space-y-5">
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Total lowongan"
                        value={angka(stats.total)}
                        caption="semua status"
                        icon={<IconFunnel className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Terbuka"
                        value={angka(stats.open)}
                        caption="tampil di portal karier"
                        icon={<IconFunnel className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Draft"
                        value={angka(stats.draft)}
                        caption="belum dipublikasi"
                        icon={<IconFunnel className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Total pelamar"
                        value={angka(stats.totalApplicants)}
                        caption="dari seluruh lowongan"
                        icon={<IconUsers className="h-4 w-4" />}
                    />
                </section>

                <div className="grid gap-5 xl:grid-cols-3">
                    <div className="xl:col-span-2">
                        <Card
                            title="Daftar lowongan"
                            subtitle={`${vacancies.total} lowongan sesuai filter`}
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
                                        placeholder="Cari judul…"
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
                                    value={filters.category ?? ""}
                                    onChange={(event) =>
                                        applyFilter({
                                            category: event.target.value || null,
                                        })
                                    }
                                >
                                    <option value="">Semua kategori</option>
                                    {options.categories.map((category) => (
                                        <option key={category} value={category}>
                                            {CATEGORY_LABELS[category] ??
                                                category}
                                        </option>
                                    ))}
                                </Select>

                                <Select
                                    value={filters.department_id ?? ""}
                                    onChange={(event) =>
                                        applyFilter({
                                            department_id:
                                                Number(event.target.value) ||
                                                null,
                                        })
                                    }
                                >
                                    <option value="">Semua divisi</option>
                                    {options.departments.map((department) => (
                                        <option
                                            key={department.id}
                                            value={department.id}
                                        >
                                            {department.name}
                                        </option>
                                    ))}
                                </Select>
                            </div>

                            {vacancies.data.length === 0 ? (
                                <EmptyState message="Belum ada lowongan. Klik “Buat lowongan” untuk menambahkan." />
                            ) : (
                                <ul className="space-y-3">
                                    {vacancies.data.map((vacancy) => (
                                        <li
                                            key={vacancy.id}
                                            className="rounded-xl border border-hairline p-4"
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="font-medium text-ink">
                                                        {vacancy.title}
                                                    </p>
                                                    <p className="mt-0.5 text-xs text-ink-muted">
                                                        {CATEGORY_LABELS[
                                                            vacancy.category
                                                        ] ?? vacancy.category}
                                                        {vacancy.department
                                                            ? ` · ${vacancy.department}`
                                                            : ""}
                                                        {vacancy.location
                                                            ? ` · ${vacancy.location}`
                                                            : ""}
                                                    </p>
                                                </div>
                                                <Badge
                                                    tone={
                                                        STATUS_TONE[
                                                            vacancy.status
                                                        ] ?? "neutral"
                                                    }
                                                >
                                                    {vacancy.status}
                                                </Badge>
                                            </div>

                                            <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-ink-soft">
                                                <span className="tabular">
                                                    {angka(
                                                        vacancy.applicantCount,
                                                    )}{" "}
                                                    pelamar
                                                </span>
                                                <span className="tabular">
                                                    {angka(vacancy.hiredCount)}{" "}
                                                    hired
                                                </span>
                                                <span className="tabular">
                                                    kuota {vacancy.quota}
                                                </span>
                                                {vacancy.publishedAt && (
                                                    <span>
                                                        publik{" "}
                                                        {vacancy.publishedAt}
                                                    </span>
                                                )}
                                            </div>

                                            <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-hairline pt-3">
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    onClick={() =>
                                                        setEditing(vacancy)
                                                    }
                                                >
                                                    Ubah
                                                </Button>

                                                {vacancy.status === "open" ? (
                                                    <Button
                                                        size="sm"
                                                        variant="secondary"
                                                        onClick={() =>
                                                            router.patch(
                                                                `/lowongan/${vacancy.id}/status`,
                                                                { status: "closed" },
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        Tutup
                                                    </Button>
                                                ) : (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            router.patch(
                                                                `/lowongan/${vacancy.id}/status`,
                                                                { status: "open" },
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        Publikasikan
                                                    </Button>
                                                )}

                                                {vacancy.status === "open" && (
                                                    <Link
                                                        href={`/karier/${vacancy.id}`}
                                                        className="text-[11px] font-medium text-brand-600 transition hover:text-brand-700"
                                                    >
                                                        Lihat di portal
                                                    </Link>
                                                )}

                                                {vacancy.applicantCount ===
                                                    0 && (
                                                    <Button
                                                        size="sm"
                                                        variant="danger"
                                                        className="ml-auto"
                                                        onClick={() => {
                                                            if (
                                                                confirm(
                                                                    `Hapus lowongan "${vacancy.title}"?`,
                                                                )
                                                            ) {
                                                                router.delete(
                                                                    `/lowongan/${vacancy.id}`,
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

                            <Pagination page={vacancies} />
                        </Card>
                    </div>

                    {editing ? (
                        <VacancyForm
                            key={editing.id}
                            vacancy={editing}
                            options={options}
                            onClose={() => setEditing(null)}
                        />
                    ) : (
                        <Card
                            title="Alur publikasi"
                            subtitle="Status menentukan apa yang terlihat publik"
                        >
                            <ul className="space-y-3 text-xs text-ink-soft">
                                <li>
                                    <span className="font-medium text-ink">
                                        Draft
                                    </span>{" "}
                                    — hanya terlihat di halaman ini. Aman untuk
                                    menyiapkan deskripsi sebelum tayang.
                                </li>
                                <li>
                                    <span className="font-medium text-ink">
                                        Open
                                    </span>{" "}
                                    — tampil di Portal Karier dan menerima
                                    lamaran. Tanggal publikasi diisi otomatis
                                    saat pertama kali dibuka.
                                </li>
                                <li>
                                    <span className="font-medium text-ink">
                                        Closed
                                    </span>{" "}
                                    — hilang dari portal, tapi pelamar yang sudah
                                    masuk tetap ada di pipeline.
                                </li>
                            </ul>

                            <p className="mt-4 border-t border-hairline pt-3 text-[11px] text-ink-muted">
                                Lowongan yang sudah punya pelamar tidak dapat
                                dihapus — tutup saja agar riwayat rekrutmen tetap
                                utuh.
                            </p>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

function VacancyForm({
    vacancy,
    options,
    onClose,
}: {
    vacancy: Vacancy;
    options: Props["options"];
    onClose: () => void;
}) {
    const isNew = vacancy.id === 0;

    const { data, setData, post, patch, processing, errors } = useForm({
        title: vacancy.title,
        offered_category: vacancy.category,
        department_id: vacancy.department_id ?? "",
        location: vacancy.location ?? "",
        description: vacancy.description ?? "",
        requirements: vacancy.requirements ?? "",
        quota: vacancy.quota,
        status: vacancy.status,
        published_at: vacancy.publishedAtRaw ?? "",
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (isNew) {
            post("/lowongan", { preserveScroll: true, onSuccess: onClose });
        } else {
            patch(`/lowongan/${vacancy.id}`, {
                preserveScroll: true,
                onSuccess: onClose,
            });
        }
    }

    return (
        <Card
            title={isNew ? "Lowongan baru" : "Ubah lowongan"}
            subtitle={
                isNew
                    ? undefined
                    : `${vacancy.applicantCount} pelamar sudah masuk`
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <Field label="Judul lowongan" error={errors.title} required>
                    <Input
                        value={data.title}
                        onChange={(event) =>
                            setData("title", event.target.value)
                        }
                        placeholder="mis. Senior Backend Engineer"
                    />
                </Field>

                <Field
                    label="Kategori entitas"
                    error={errors.offered_category}
                    required
                    hint="Label ini yang tampil di portal karier."
                >
                    <Select
                        value={data.offered_category}
                        onChange={(event) =>
                            setData("offered_category", event.target.value)
                        }
                    >
                        {options.categories.map((category) => (
                            <option key={category} value={category}>
                                {CATEGORY_LABELS[category] ?? category}
                            </option>
                        ))}
                    </Select>
                </Field>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Divisi" error={errors.department_id}>
                        <Select
                            value={data.department_id}
                            onChange={(event) =>
                                setData(
                                    "department_id",
                                    Number(event.target.value) || "",
                                )
                            }
                        >
                            <option value="">— pilih —</option>
                            {options.departments.map((department) => (
                                <option key={department.id} value={department.id}>
                                    {department.name}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Lokasi" error={errors.location}>
                        <Input
                            value={data.location}
                            onChange={(event) =>
                                setData("location", event.target.value)
                            }
                            placeholder="Jakarta"
                        />
                    </Field>
                </div>

                <Field label="Deskripsi pekerjaan" error={errors.description}>
                    <Textarea
                        rows={4}
                        value={data.description}
                        onChange={(event) =>
                            setData("description", event.target.value)
                        }
                        placeholder="Ringkasan peran dan tanggung jawab…"
                    />
                </Field>

                <Field label="Kualifikasi" error={errors.requirements}>
                    <Textarea
                        rows={4}
                        value={data.requirements}
                        onChange={(event) =>
                            setData("requirements", event.target.value)
                        }
                        placeholder="Satu kualifikasi per baris…"
                    />
                </Field>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Kuota posisi" error={errors.quota} required>
                        <Input
                            type="number"
                            min={1}
                            max={999}
                            value={data.quota}
                            onChange={(event) =>
                                setData("quota", Number(event.target.value))
                            }
                        />
                    </Field>

                    <Field label="Status" error={errors.status} required>
                        <Select
                            value={data.status}
                            onChange={(event) =>
                                setData("status", event.target.value)
                            }
                        >
                            {options.statuses.map((status) => (
                                <option key={status} value={status}>
                                    {status}
                                </option>
                            ))}
                        </Select>
                    </Field>
                </div>

                <Field
                    label="Tanggal publikasi"
                    error={errors.published_at}
                    hint="Kosongkan untuk diisi otomatis saat dipublikasikan."
                >
                    <Input
                        type="date"
                        value={data.published_at}
                        onChange={(event) =>
                            setData("published_at", event.target.value)
                        }
                    />
                </Field>

                <div className="flex flex-wrap items-center gap-2 pt-1">
                    <Button type="submit" disabled={processing}>
                        {processing ? "Menyimpan…" : "Simpan"}
                    </Button>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                </div>
            </form>
        </Card>
    );
}
