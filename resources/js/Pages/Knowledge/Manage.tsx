import { Head, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import AppLayout from "@/Layouts/AppLayout";
import Card from "@/Components/Card";
import StatTile from "@/Components/StatTile";
import {
    Badge,
    Button,
    EmptyState,
    Field,
    Input,
    LinkButton,
    Select,
    Textarea,
} from "@/Components/ui";
import { IconBook, IconDownload } from "@/Components/Icons";
import { angka } from "@/lib/format";
import {
    CATEGORY_LABELS,
    CATEGORY_TONE,
    type DocumentItem,
} from "./Index";

type ManagedAnnouncement = {
    id: number;
    title: string;
    body: string;
    category: string;
    isPinned: boolean;
    audience: string;
    author: string | null;
    publishedAt: string | null;
    isPublished: boolean;
    targetType: string;
    targetDepartmentId: number | null;
    targetCategory: string | null;
};

type Options = {
    departments: { id: number; name: string }[];
    categories: string[];
    docTypes: string[];
    docTypeLabels: Record<string, string>;
    targetTypes: string[];
    employmentCategories: string[];
};

type Props = {
    announcements: ManagedAnnouncement[];
    documents: DocumentItem[];
    options: Options;
    stats: {
        published: number;
        draft: number;
        documents: number;
        downloads: number;
    };
};

const TARGET_LABELS: Record<string, string> = {
    all: "Seluruh perusahaan",
    department: "Divisi tertentu",
    employment_category: "Entitas kerja tertentu",
};

const ENTITY_LABELS: Record<string, string> = {
    probation: "Probation",
    pkwt: "PKWT",
    mitra: "Mitra",
};

const EMPTY_ANNOUNCEMENT: ManagedAnnouncement = {
    id: 0,
    title: "",
    body: "",
    category: "info",
    isPinned: false,
    audience: "",
    author: null,
    publishedAt: null,
    isPublished: false,
    targetType: "all",
    targetDepartmentId: null,
    targetCategory: null,
};

export default function KnowledgeManage({
    announcements,
    documents,
    options,
    stats,
}: Props) {
    const [editing, setEditing] = useState<ManagedAnnouncement | null>(null);
    const [uploading, setUploading] = useState(false);

    return (
        <AppLayout
            title="Kelola Knowledge Center"
            subtitle="Bulletin pengumuman dan repositori SOP/peraturan"
            actions={
                <div className="flex items-center gap-2">
                    <LinkButton href="/knowledge">Lihat sebagai pembaca</LinkButton>
                    <Button
                        onClick={() => setEditing({ ...EMPTY_ANNOUNCEMENT })}
                    >
                        Buat pengumuman
                    </Button>
                </div>
            }
        >
            <Head title="Kelola Knowledge Center" />

            <div className="space-y-5">
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Pengumuman terbit"
                        value={angka(stats.published)}
                        caption="terlihat karyawan"
                        icon={<IconBook className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Draft"
                        value={angka(stats.draft)}
                        caption="belum diterbitkan"
                        icon={<IconBook className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Dokumen"
                        value={angka(stats.documents)}
                        caption="SOP, peraturan, panduan, formulir"
                        icon={<IconDownload className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Total unduhan"
                        value={angka(stats.downloads)}
                        caption="sejak dokumen diunggah"
                        icon={<IconDownload className="h-4 w-4" />}
                    />
                </section>

                <div className="grid gap-5 xl:grid-cols-3">
                    <div className="space-y-5 xl:col-span-2">
                        <Card
                            title="Pengumuman"
                            subtitle={`${announcements.length} pengumuman`}
                        >
                            {announcements.length === 0 ? (
                                <EmptyState message="Belum ada pengumuman." />
                            ) : (
                                <ul className="space-y-3">
                                    {announcements.map((item) => (
                                        <li
                                            key={item.id}
                                            className="rounded-xl border border-hairline p-4"
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-2">
                                                <div className="min-w-0">
                                                    <p className="font-medium text-ink">
                                                        {item.title}
                                                        {item.isPinned && (
                                                            <span className="ml-2 text-[11px] font-normal text-brand-600">
                                                                disematkan
                                                            </span>
                                                        )}
                                                    </p>
                                                    <p className="mt-0.5 text-xs text-ink-muted">
                                                        {item.audience}
                                                        {item.publishedAt
                                                            ? ` · ${item.publishedAt}`
                                                            : ""}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-1.5">
                                                    <Badge
                                                        tone={
                                                            CATEGORY_TONE[
                                                                item.category
                                                            ] ?? "neutral"
                                                        }
                                                    >
                                                        {CATEGORY_LABELS[
                                                            item.category
                                                        ] ?? item.category}
                                                    </Badge>
                                                    <Badge
                                                        tone={
                                                            item.isPublished
                                                                ? "good"
                                                                : "neutral"
                                                        }
                                                    >
                                                        {item.isPublished
                                                            ? "terbit"
                                                            : "draft"}
                                                    </Badge>
                                                </div>
                                            </div>

                                            <p className="mt-2 line-clamp-2 text-xs text-ink-soft">
                                                {item.body}
                                            </p>

                                            <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-hairline pt-3">
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    onClick={() =>
                                                        setEditing(item)
                                                    }
                                                >
                                                    Ubah
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant={
                                                        item.isPublished
                                                            ? "secondary"
                                                            : "primary"
                                                    }
                                                    onClick={() =>
                                                        router.patch(
                                                            `/knowledge/pengumuman/${item.id}/status`,
                                                            {
                                                                publish:
                                                                    !item.isPublished,
                                                            },
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    {item.isPublished
                                                        ? "Tarik ke draft"
                                                        : "Terbitkan"}
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="danger"
                                                    className="ml-auto"
                                                    onClick={() => {
                                                        if (
                                                            confirm(
                                                                `Hapus pengumuman "${item.title}"?`,
                                                            )
                                                        ) {
                                                            router.delete(
                                                                `/knowledge/pengumuman/${item.id}`,
                                                                { preserveScroll: true },
                                                            );
                                                        }
                                                    }}
                                                >
                                                    Hapus
                                                </Button>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>

                        <Card
                            title="Dokumen"
                            subtitle={`${documents.length} berkas di repositori`}
                            action={
                                <Button
                                    size="sm"
                                    onClick={() => setUploading(true)}
                                >
                                    Unggah dokumen
                                </Button>
                            }
                        >
                            {documents.length === 0 ? (
                                <EmptyState message="Belum ada dokumen." />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[620px] text-sm">
                                        <thead>
                                            <tr className="border-b border-hairline text-left text-xs text-ink-muted">
                                                <th className="pb-2 font-medium">
                                                    Dokumen
                                                </th>
                                                <th className="pb-2 font-medium">
                                                    Audiens
                                                </th>
                                                <th className="pb-2 text-right font-medium">
                                                    Unduhan
                                                </th>
                                                <th className="pb-2" />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {documents.map((doc) => (
                                                <tr
                                                    key={doc.id}
                                                    className="border-b border-hairline last:border-0"
                                                >
                                                    <td className="py-2.5">
                                                        <p className="font-medium text-ink">
                                                            {doc.title}
                                                        </p>
                                                        <p className="text-xs text-ink-muted">
                                                            {doc.typeLabel} · v
                                                            {doc.version} ·{" "}
                                                            {doc.fileSize}
                                                        </p>
                                                    </td>
                                                    <td className="py-2.5 text-xs text-ink-soft">
                                                        {doc.audience}
                                                    </td>
                                                    <td className="tabular py-2.5 text-right text-ink">
                                                        {angka(
                                                            doc.downloadCount,
                                                        )}
                                                    </td>
                                                    <td className="py-2.5 text-right">
                                                        <div className="flex justify-end gap-2">
                                                            <a
                                                                href={`/knowledge/dokumen/${doc.id}`}
                                                                className="text-[11px] font-medium text-brand-600 hover:text-brand-700"
                                                            >
                                                                Unduh
                                                            </a>
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    if (
                                                                        confirm(
                                                                            `Hapus dokumen "${doc.title}"?`,
                                                                        )
                                                                    ) {
                                                                        router.delete(
                                                                            `/knowledge/dokumen/${doc.id}`,
                                                                            { preserveScroll: true },
                                                                        );
                                                                    }
                                                                }}
                                                                className="text-[11px] font-medium text-[#b53232]"
                                                            >
                                                                Hapus
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </Card>
                    </div>

                    <div className="space-y-5">
                        {editing ? (
                            <AnnouncementForm
                                key={editing.id}
                                announcement={editing}
                                options={options}
                                onClose={() => setEditing(null)}
                            />
                        ) : uploading ? (
                            <DocumentForm
                                options={options}
                                onClose={() => setUploading(false)}
                            />
                        ) : (
                            <Card
                                title="Cara penargetan"
                                subtitle="Menentukan siapa yang melihat konten"
                            >
                                <ul className="space-y-3 text-xs text-ink-soft">
                                    <li>
                                        <span className="font-medium text-ink">
                                            Seluruh perusahaan
                                        </span>{" "}
                                        — terlihat semua orang.
                                    </li>
                                    <li>
                                        <span className="font-medium text-ink">
                                            Divisi tertentu
                                        </span>{" "}
                                        — hanya anggota divisi itu.
                                    </li>
                                    <li>
                                        <span className="font-medium text-ink">
                                            Entitas kerja tertentu
                                        </span>{" "}
                                        — mis. khusus Mitra atau khusus Probation.
                                    </li>
                                </ul>

                                <p className="mt-4 border-t border-hairline pt-3 text-[11px] text-ink-muted">
                                    Penyaringan dilakukan di server, jadi konten
                                    yang bukan haknya tidak pernah terkirim ke
                                    perangkat karyawan. Dokumen juga hanya bisa
                                    diunduh lewat sistem, bukan tautan publik.
                                </p>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function TargetFields({
    options,
    targetType,
    departmentId,
    category,
    errors,
    onChange,
}: {
    options: Options;
    targetType: string;
    departmentId: number | string;
    category: string;
    errors: Record<string, string>;
    onChange: (field: string, value: string | number) => void;
}) {
    return (
        <>
            <Field label="Ditujukan kepada" error={errors.target_type} required>
                <Select
                    value={targetType}
                    onChange={(event) => onChange("target_type", event.target.value)}
                >
                    {options.targetTypes.map((type) => (
                        <option key={type} value={type}>
                            {TARGET_LABELS[type] ?? type}
                        </option>
                    ))}
                </Select>
            </Field>

            {targetType === "department" && (
                <Field label="Divisi" error={errors.target_department_id} required>
                    <Select
                        value={departmentId}
                        onChange={(event) =>
                            onChange(
                                "target_department_id",
                                Number(event.target.value) || "",
                            )
                        }
                    >
                        <option value="">— pilih divisi —</option>
                        {options.departments.map((department) => (
                            <option key={department.id} value={department.id}>
                                {department.name}
                            </option>
                        ))}
                    </Select>
                </Field>
            )}

            {targetType === "employment_category" && (
                <Field label="Entitas kerja" error={errors.target_category} required>
                    <Select
                        value={category}
                        onChange={(event) =>
                            onChange("target_category", event.target.value)
                        }
                    >
                        <option value="">— pilih entitas —</option>
                        {options.employmentCategories.map((item) => (
                            <option key={item} value={item}>
                                {ENTITY_LABELS[item] ?? item}
                            </option>
                        ))}
                    </Select>
                </Field>
            )}
        </>
    );
}

function AnnouncementForm({
    announcement,
    options,
    onClose,
}: {
    announcement: ManagedAnnouncement;
    options: Options;
    onClose: () => void;
}) {
    const isNew = announcement.id === 0;

    const { data, setData, post, patch, processing, errors } = useForm({
        title: announcement.title,
        body: announcement.body,
        category: announcement.category,
        target_type: announcement.targetType,
        target_department_id: announcement.targetDepartmentId ?? "",
        target_category: announcement.targetCategory ?? "",
        is_pinned: announcement.isPinned,
        publish: announcement.isPublished,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (isNew) {
            post("/knowledge/pengumuman", {
                preserveScroll: true,
                onSuccess: onClose,
            });
        } else {
            patch(`/knowledge/pengumuman/${announcement.id}`, {
                preserveScroll: true,
                onSuccess: onClose,
            });
        }
    }

    return (
        <Card title={isNew ? "Pengumuman baru" : "Ubah pengumuman"}>
            <form onSubmit={submit} className="space-y-4">
                <Field label="Judul" error={errors.title} required>
                    <Input
                        value={data.title}
                        onChange={(event) => setData("title", event.target.value)}
                    />
                </Field>

                <Field label="Isi" error={errors.body} required>
                    <Textarea
                        rows={6}
                        value={data.body}
                        onChange={(event) => setData("body", event.target.value)}
                        placeholder="Tulis isi pengumuman…"
                    />
                </Field>

                <Field label="Kategori" error={errors.category} required>
                    <Select
                        value={data.category}
                        onChange={(event) =>
                            setData("category", event.target.value)
                        }
                    >
                        {options.categories.map((category) => (
                            <option key={category} value={category}>
                                {CATEGORY_LABELS[category] ?? category}
                            </option>
                        ))}
                    </Select>
                </Field>

                <TargetFields
                    options={options}
                    targetType={data.target_type}
                    departmentId={data.target_department_id}
                    category={data.target_category}
                    errors={errors as Record<string, string>}
                    onChange={(field, value) =>
                        setData(field as keyof typeof data, value as never)
                    }
                />

                <label className="flex items-center gap-2 text-xs text-ink-soft">
                    <input
                        type="checkbox"
                        checked={data.is_pinned}
                        onChange={(event) =>
                            setData("is_pinned", event.target.checked)
                        }
                        className="h-3.5 w-3.5 rounded border-hairline-strong text-brand-500 focus:ring-brand-100"
                    />
                    Sematkan di atas
                </label>

                <label className="flex items-center gap-2 text-xs text-ink-soft">
                    <input
                        type="checkbox"
                        checked={data.publish}
                        onChange={(event) =>
                            setData("publish", event.target.checked)
                        }
                        className="h-3.5 w-3.5 rounded border-hairline-strong text-brand-500 focus:ring-brand-100"
                    />
                    Terbitkan sekarang
                </label>

                <div className="flex items-center gap-2 pt-1">
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

function DocumentForm({
    options,
    onClose,
}: {
    options: Options;
    onClose: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm<{
        title: string;
        description: string;
        doc_type: string;
        version: string;
        file: File | null;
        target_type: string;
        target_department_id: number | string;
        target_category: string;
    }>({
        title: "",
        description: "",
        doc_type: "sop",
        version: "1.0",
        file: null,
        target_type: "all",
        target_department_id: "",
        target_category: "",
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post("/knowledge/dokumen", {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: onClose,
        });
    }

    return (
        <Card title="Unggah dokumen">
            <form onSubmit={submit} className="space-y-4">
                <Field label="Judul" error={errors.title} required>
                    <Input
                        value={data.title}
                        onChange={(event) => setData("title", event.target.value)}
                    />
                </Field>

                <Field label="Deskripsi" error={errors.description}>
                    <Textarea
                        rows={3}
                        value={data.description}
                        onChange={(event) =>
                            setData("description", event.target.value)
                        }
                    />
                </Field>

                <Field label="Jenis dokumen" error={errors.doc_type} required>
                    <Select
                        value={data.doc_type}
                        onChange={(event) =>
                            setData("doc_type", event.target.value)
                        }
                    >
                        {options.docTypes.map((type) => (
                            <option key={type} value={type}>
                                {options.docTypeLabels[type] ?? type}
                            </option>
                        ))}
                    </Select>
                </Field>

                <Field label="Versi" error={errors.version}>
                    <Input
                        value={data.version}
                        onChange={(event) =>
                            setData("version", event.target.value)
                        }
                        placeholder="1.0"
                    />
                </Field>

                <Field
                    label="Berkas"
                    error={errors.file}
                    required
                    hint="PDF, Word, Excel, atau PowerPoint. Maksimal 10 MB."
                >
                    <input
                        type="file"
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                        onChange={(event) =>
                            setData("file", event.target.files?.[0] ?? null)
                        }
                        className="w-full rounded-lg border border-hairline bg-surface px-3 py-2 text-sm text-ink-soft file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1 file:text-xs file:font-medium file:text-brand-600"
                        required
                    />
                </Field>

                <TargetFields
                    options={options}
                    targetType={data.target_type}
                    departmentId={data.target_department_id}
                    category={data.target_category}
                    errors={errors as Record<string, string>}
                    onChange={(field, value) =>
                        setData(field as keyof typeof data, value as never)
                    }
                />

                <div className="flex items-center gap-2 pt-1">
                    <Button type="submit" disabled={processing}>
                        {processing ? "Mengunggah…" : "Unggah"}
                    </Button>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                </div>
            </form>
        </Card>
    );
}
