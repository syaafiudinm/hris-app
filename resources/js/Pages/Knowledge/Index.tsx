import { Head, router, usePage } from "@inertiajs/react";
import { useState } from "react";
import AppLayout from "@/Layouts/AppLayout";
import Card from "@/Components/Card";
import { Badge, EmptyState, Input, LinkButton, Select } from "@/Components/ui";
import { IconAlert, IconBook, IconDownload } from "@/Components/Icons";
import type { PageProps } from "@/types";

export type AnnouncementItem = {
    id: number;
    title: string;
    body: string;
    category: string;
    isPinned: boolean;
    audience: string;
    author: string | null;
    publishedAt: string | null;
};

export type DocumentItem = {
    id: number;
    title: string;
    description: string | null;
    docType: string;
    typeLabel: string;
    version: string;
    fileName: string;
    fileSize: string;
    audience: string;
    uploader: string | null;
    downloadCount: number;
    uploadedAt: string | null;
};

type Props = {
    announcements: AnnouncementItem[];
    documents: DocumentItem[];
    filters: { doc_type: string | null; search: string | null };
    options: {
        docTypes: string[];
        docTypeLabels: Record<string, string>;
    };
};

export const CATEGORY_TONE: Record<
    string,
    "neutral" | "brand" | "warning" | "critical"
> = {
    info: "brand",
    policy: "neutral",
    urgent: "critical",
};

export const CATEGORY_LABELS: Record<string, string> = {
    info: "Informasi",
    policy: "Kebijakan",
    urgent: "Penting",
};

export default function KnowledgeIndex({
    announcements,
    documents,
    filters,
    options,
}: Props) {
    const { auth } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search ?? "");

    function applyFilter(patch: Record<string, string | null>) {
        router.get(
            "/knowledge",
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    }

    const pinned = announcements.filter((item) => item.isPinned);
    const regular = announcements.filter((item) => !item.isPinned);

    return (
        <AppLayout
            title="Knowledge Center"
            subtitle="Pengumuman perusahaan, SOP, dan peraturan"
            actions={
                auth.user?.isSuperAdmin ? (
                    <LinkButton href="/knowledge/kelola" variant="primary">
                        Kelola konten
                    </LinkButton>
                ) : undefined
            }
        >
            <Head title="Knowledge Center" />

            <div className="grid gap-5 xl:grid-cols-5">
                {/* Bulletin pengumuman */}
                <div className="space-y-5 xl:col-span-3">
                    {pinned.length > 0 && (
                        <Card
                            title="Disematkan"
                            subtitle="Pengumuman yang perlu diperhatikan"
                        >
                            <ul className="space-y-4">
                                {pinned.map((item) => (
                                    <AnnouncementBlock
                                        key={item.id}
                                        item={item}
                                        highlighted
                                    />
                                ))}
                            </ul>
                        </Card>
                    )}

                    <Card
                        title="Bulletin pengumuman"
                        subtitle={`${announcements.length} pengumuman untuk Anda`}
                    >
                        {regular.length === 0 ? (
                            <EmptyState message="Belum ada pengumuman baru." />
                        ) : (
                            <ul className="space-y-5">
                                {regular.map((item) => (
                                    <AnnouncementBlock
                                        key={item.id}
                                        item={item}
                                    />
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>

                {/* Repositori dokumen */}
                <div className="xl:col-span-2">
                    <Card
                        title="SOP & Peraturan"
                        subtitle={`${documents.length} dokumen tersedia`}
                    >
                        <div className="mb-4 grid gap-2">
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
                                    placeholder="Cari dokumen…"
                                />
                            </form>

                            <Select
                                value={filters.doc_type ?? ""}
                                onChange={(event) =>
                                    applyFilter({
                                        doc_type: event.target.value || null,
                                    })
                                }
                            >
                                <option value="">Semua jenis</option>
                                {options.docTypes.map((type) => (
                                    <option key={type} value={type}>
                                        {options.docTypeLabels[type] ?? type}
                                    </option>
                                ))}
                            </Select>
                        </div>

                        {documents.length === 0 ? (
                            <EmptyState message="Tidak ada dokumen pada filter ini." />
                        ) : (
                            <ul className="space-y-2.5">
                                {documents.map((doc) => (
                                    <li
                                        key={doc.id}
                                        className="rounded-xl border border-hairline p-3.5"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium text-ink">
                                                    {doc.title}
                                                </p>
                                                {doc.description && (
                                                    <p className="mt-0.5 text-xs text-ink-soft">
                                                        {doc.description}
                                                    </p>
                                                )}
                                            </div>
                                            <Badge tone="brand">
                                                {doc.typeLabel}
                                            </Badge>
                                        </div>

                                        <div className="mt-2.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-ink-muted">
                                            <span>v{doc.version}</span>
                                            <span>{doc.fileSize}</span>
                                            <span>{doc.audience}</span>
                                        </div>

                                        <a
                                            href={`/knowledge/dokumen/${doc.id}`}
                                            className="mt-2.5 inline-flex items-center gap-1.5 text-[11px] font-medium text-brand-600 transition hover:text-brand-700"
                                        >
                                            <IconDownload className="h-3.5 w-3.5" />
                                            Unduh {doc.fileName}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

function AnnouncementBlock({
    item,
    highlighted = false,
}: {
    item: AnnouncementItem;
    highlighted?: boolean;
}) {
    return (
        <li
            className={
                highlighted
                    ? "rounded-xl bg-surface-soft p-4"
                    : "border-b border-hairline pb-5 last:border-0 last:pb-0"
            }
        >
            <div className="flex flex-wrap items-start justify-between gap-2">
                <h3 className="text-[15px] font-semibold text-ink">
                    {item.title}
                </h3>
                <div className="flex items-center gap-1.5">
                    {item.category === "urgent" && (
                        <IconAlert
                            className="h-3.5 w-3.5"
                            style={{ color: "#d03b3b" }}
                            aria-hidden
                        />
                    )}
                    <Badge tone={CATEGORY_TONE[item.category] ?? "neutral"}>
                        {CATEGORY_LABELS[item.category] ?? item.category}
                    </Badge>
                </div>
            </div>

            {/* whitespace-pre-line agar paragraf yang diketik HR tetap utuh */}
            <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-ink-soft">
                {item.body}
            </p>

            <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-ink-muted">
                <span className="inline-flex items-center gap-1">
                    <IconBook className="h-3 w-3" />
                    {item.audience}
                </span>
                {item.author && <span>oleh {item.author}</span>}
                {item.publishedAt && <span>{item.publishedAt}</span>}
            </div>
        </li>
    );
}
