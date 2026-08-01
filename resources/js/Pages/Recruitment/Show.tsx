import { Head, Link, router, useForm } from "@inertiajs/react";
import AppLayout from "@/Layouts/AppLayout";
import Card from "@/Components/Card";
import { Badge, Button, Field, Textarea } from "@/Components/ui";

type StageHistoryEntry = {
    from: string;
    to: string;
    changed_by: string | null;
    changed_at: string;
};

type NoteEntry = {
    content: string;
    author: string;
    created_at: string;
};

type ApplicantDetail = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    stage: string;
    stageLabel: string;
    cvPath: string | null;
    notes: NoteEntry[];
    stageHistory: StageHistoryEntry[];
    appliedAt: string | null;
    stageChangedAt: string | null;
    vacancy: {
        id: number;
        title: string;
        category: string;
        department: string | null;
    };
    convertedEmployee: {
        id: number;
        nik: string;
        name: string;
        type: string | null;
    } | null;
};

type Props = {
    applicant: ApplicantDetail;
    stages: string[];
    stageLabels: Record<string, string>;
};

const STAGE_TONES: Record<string, string> = {
    applied: "neutral",
    screening: "brand",
    interview: "brand",
    offering: "warning",
    hired: "good",
    rejected: "critical",
};

export default function RecruitmentShow({ applicant, stages, stageLabels }: Props) {
    const noteForm = useForm({ content: "" });

    function submitNote(event: React.FormEvent) {
        event.preventDefault();
        noteForm.post(`/rekrutmen/${applicant.id}/note`, {
            preserveScroll: true,
            onSuccess: () => noteForm.reset(),
        });
    }

    function moveStage(newStage: string) {
        router.patch(
            `/rekrutmen/${applicant.id}/stage`,
            { stage: newStage },
            { preserveScroll: true },
        );
    }

    return (
        <AppLayout
            title={applicant.name}
            subtitle={`${applicant.vacancy.title} · ${applicant.stageLabel}`}
            actions={
                <div className="flex gap-2">
                    <Link
                        href="/rekrutmen"
                        className="inline-flex items-center gap-1.5 rounded-lg border border-hairline px-3 py-2 text-xs font-medium text-ink-soft transition hover:bg-surface-soft"
                    >
                        ← Pipeline
                    </Link>
                    {applicant.stage === "offering" && (
                        <a
                            href={`/rekrutmen/${applicant.id}/offering-letter`}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-3.5 py-2 text-xs font-medium text-white transition hover:bg-brand-600"
                        >
                            📄 Offering Letter
                        </a>
                    )}
                    {applicant.convertedEmployee && (
                        <a
                            href={`/rekrutmen/employee/${applicant.convertedEmployee.id}/contract`}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-3.5 py-2 text-xs font-medium text-white transition hover:bg-brand-600"
                        >
                            📄 Kontrak
                        </a>
                    )}
                </div>
            }
        >
            <Head title={`${applicant.name} — Rekrutmen`} />

            <div className="grid gap-5 xl:grid-cols-3">
                {/* Main content */}
                <div className="space-y-5 xl:col-span-2">
                    {/* Profile card */}
                    <Card title="Profil Kandidat">
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div>
                                <dt className="text-xs text-ink-muted">Nama</dt>
                                <dd className="font-medium text-ink">{applicant.name}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-ink-muted">Email</dt>
                                <dd className="text-ink">{applicant.email}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-ink-muted">Telepon</dt>
                                <dd className="text-ink">{applicant.phone ?? "-"}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-ink-muted">Tanggal Lamar</dt>
                                <dd className="text-ink">{applicant.appliedAt ?? "-"}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-ink-muted">Lowongan</dt>
                                <dd className="text-ink">{applicant.vacancy.title}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-ink-muted">Divisi</dt>
                                <dd className="text-ink">{applicant.vacancy.department ?? "-"}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-ink-muted">Status</dt>
                                <dd>
                                    <Badge tone={(STAGE_TONES[applicant.stage] ?? "neutral") as "good" | "neutral" | "brand" | "warning" | "critical"}>
                                        {applicant.stageLabel}
                                    </Badge>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-ink-muted">CV</dt>
                                <dd>
                                    {applicant.cvPath ? (
                                        <a
                                            href={`/storage/${applicant.cvPath}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-sm font-medium text-brand-600 hover:text-brand-700"
                                        >
                                            📄 Download CV
                                        </a>
                                    ) : (
                                        <span className="text-ink-muted">-</span>
                                    )}
                                </dd>
                            </div>
                        </dl>

                        {/* Converted employee info */}
                        {applicant.convertedEmployee && (
                            <div className="mt-4 rounded-xl bg-[#effaef] p-4">
                                <p className="text-xs font-semibold text-[#0a7a0a]">
                                    ✓ Dikonversi menjadi karyawan
                                </p>
                                <p className="mt-1 text-sm text-[#0a7a0a]">
                                    {applicant.convertedEmployee.name} · NIK{" "}
                                    {applicant.convertedEmployee.nik} ·{" "}
                                    {applicant.convertedEmployee.type}
                                </p>
                                <Link
                                    href={`/employees/${applicant.convertedEmployee.id}`}
                                    className="mt-2 inline-block text-xs font-medium text-brand-600 hover:text-brand-700"
                                >
                                    Lihat data karyawan →
                                </Link>
                            </div>
                        )}
                    </Card>

                    {/* Stage history */}
                    <Card
                        title="Riwayat Perpindahan Tahap"
                        subtitle={`${applicant.stageHistory.length} perubahan`}
                    >
                        {applicant.stageHistory.length === 0 ? (
                            <p className="text-sm text-ink-muted">Belum ada perpindahan tahap.</p>
                        ) : (
                            <div className="space-y-3">
                                {applicant.stageHistory.map((entry, index) => (
                                    <div
                                        key={index}
                                        className="flex items-start gap-3 border-b border-hairline pb-3 last:border-0 last:pb-0"
                                    >
                                        <div className="mt-1 h-2 w-2 shrink-0 rounded-full bg-brand-400" />
                                        <div>
                                            <p className="text-sm text-ink">
                                                <Badge tone="neutral">{stageLabels[entry.from] ?? entry.from}</Badge>
                                                {" → "}
                                                <Badge tone="brand">{stageLabels[entry.to] ?? entry.to}</Badge>
                                            </p>
                                            <p className="mt-1 text-[11px] text-ink-muted">
                                                {entry.changed_by ?? "System"} ·{" "}
                                                {new Date(entry.changed_at).toLocaleString("id-ID")}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>

                    {/* Notes */}
                    <Card
                        title="Catatan Interview"
                        subtitle={`${applicant.notes.length} catatan`}
                    >
                        <div className="space-y-3">
                            {applicant.notes.map((note, index) => (
                                <div
                                    key={index}
                                    className="rounded-lg border border-hairline p-3"
                                >
                                    <p className="whitespace-pre-line text-sm text-ink">
                                        {note.content}
                                    </p>
                                    <p className="mt-2 text-[11px] text-ink-muted">
                                        {note.author} ·{" "}
                                        {new Date(note.created_at).toLocaleString("id-ID")}
                                    </p>
                                </div>
                            ))}
                        </div>

                        <form onSubmit={submitNote} className="mt-4 space-y-3">
                            <Field label="Tambah catatan" error={noteForm.errors.content}>
                                <Textarea
                                    rows={3}
                                    value={noteForm.data.content}
                                    onChange={(e) =>
                                        noteForm.setData("content", e.target.value)
                                    }
                                    placeholder="Tulis catatan interview, observasi, atau evaluasi…"
                                />
                            </Field>
                            <Button
                                type="submit"
                                disabled={noteForm.processing || !noteForm.data.content.trim()}
                            >
                                {noteForm.processing ? "Menyimpan…" : "Simpan catatan"}
                            </Button>
                        </form>
                    </Card>
                </div>

                {/* Sidebar: actions */}
                <div className="space-y-5">
                    <Card title="Ubah Tahap">
                        <div className="space-y-2">
                            {stages.map((stage) => (
                                <button
                                    key={stage}
                                    type="button"
                                    disabled={stage === applicant.stage}
                                    onClick={() => moveStage(stage)}
                                    className={`w-full rounded-lg px-3 py-2 text-left text-xs font-medium transition ${
                                        stage === applicant.stage
                                            ? "bg-brand-50 text-brand-700 cursor-default"
                                            : "text-ink-soft hover:bg-surface-soft hover:text-ink"
                                    }`}
                                >
                                    {stage === applicant.stage ? "● " : "○ "}
                                    {stageLabels[stage]}
                                </button>
                            ))}
                        </div>
                    </Card>

                    <Card title="Dokumen">
                        <div className="space-y-2">
                            {applicant.stage === "offering" && (
                                <a
                                    href={`/rekrutmen/${applicant.id}/offering-letter`}
                                    className="block rounded-lg border border-hairline px-3 py-2 text-xs font-medium text-ink-soft transition hover:bg-surface-soft hover:text-ink"
                                >
                                    📄 Download Offering Letter (PDF)
                                </a>
                            )}
                            {applicant.convertedEmployee && (
                                <a
                                    href={`/rekrutmen/employee/${applicant.convertedEmployee.id}/contract`}
                                    className="block rounded-lg border border-hairline px-3 py-2 text-xs font-medium text-ink-soft transition hover:bg-surface-soft hover:text-ink"
                                >
                                    📄 Download Kontrak (PDF)
                                </a>
                            )}
                            {applicant.cvPath && (
                                <a
                                    href={`/storage/${applicant.cvPath}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="block rounded-lg border border-hairline px-3 py-2 text-xs font-medium text-ink-soft transition hover:bg-surface-soft hover:text-ink"
                                >
                                    📄 Download CV
                                </a>
                            )}
                            {!applicant.cvPath && applicant.stage !== "offering" && !applicant.convertedEmployee && (
                                <p className="text-xs text-ink-muted">Belum ada dokumen.</p>
                            )}
                        </div>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
