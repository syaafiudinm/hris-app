import { Head, Link, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import AppLayout from "@/Layouts/AppLayout";
import Card from "@/Components/Card";
import StatTile from "@/Components/StatTile";
import ExportMenu from "@/Components/ExportMenu";
import { Badge, Button, EmptyState, Field, Input, Select } from "@/Components/ui";
import { IconFunnel, IconUsers, IconCheck, IconAlert } from "@/Components/Icons";
import { angka } from "@/lib/format";

type ApplicantCard = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    stage: string;
    vacancyTitle: string | null;
    vacancyCategory: string | null;
    department: string | null;
    cvPath: string | null;
    convertedEmployeeId: number | null;
    appliedAt: string | null;
    stageChangedAt: string | null;
};

type PipelineStage = {
    key: string;
    label: string;
    applicants: ApplicantCard[];
};

type VacancySummary = {
    id: number;
    title: string;
    department: string | null;
    status: string;
    category: string;
    applicantCount: number;
    hiredCount: number;
};

type EmploymentTypeOption = {
    id: number;
    name: string;
    code: string;
    category: string;
    duration_months: number | null;
};

type Props = {
    pipeline: PipelineStage[];
    rejected: ApplicantCard[];
    vacancies: VacancySummary[];
    filters: { vacancy_id: number | null; department_id: number | null };
    options: {
        departments: { id: number; name: string }[];
        employmentTypes: EmploymentTypeOption[];
        schemaTypes: string[];
        taxSchemes: string[];
    };
    stats: {
        totalApplicants: number;
        totalHired: number;
        totalOpen: number;
        conversionRate: number;
    };
};

const STAGE_COLORS: Record<string, string> = {
    applied: "border-t-[#86b6ef]",
    screening: "border-t-[#5598e7]",
    interview: "border-t-[#2a78d6]",
    offering: "border-t-[#1c5cab]",
    hired: "border-t-[#104281]",
};

const ALL_STAGES = ["applied", "screening", "interview", "offering", "hired", "rejected"];

const STAGE_LABELS: Record<string, string> = {
    applied: "Applied",
    screening: "Screening",
    interview: "Interview",
    offering: "Offering",
    hired: "Hired",
    rejected: "Rejected",
};

const SCHEMA_LABELS: Record<string, string> = {
    fixed_project: "Fixed Project Fee",
    hourly: "Hourly Rate",
    daily: "Daily Rate",
    milestone: "Deliverable / Milestone",
    unit: "Unit / Output",
};

const TAX_LABELS: Record<string, string> = {
    pph21_berkesinambungan: "PPh 21 (Berkesinambungan)",
    pph21_tidak_berkesinambungan: "PPh 21 (Tidak Berkesinambungan)",
    pph23: "PPh 23",
    bebas_pajak: "Bebas Pajak",
};

export default function RecruitmentIndex({
    pipeline,
    rejected,
    vacancies,
    filters,
    options,
    stats,
}: Props) {
    const [showRejected, setShowRejected] = useState(false);
    const [converting, setConverting] = useState<ApplicantCard | null>(null);

    function applyFilter(patch: Record<string, string | number | null>) {
        router.get("/rekrutmen", { ...filters, ...patch }, { preserveState: true, replace: true });
    }

    function moveStage(applicant: ApplicantCard, newStage: string) {
        if (newStage === "hired" && !applicant.convertedEmployeeId) {
            setConverting(applicant);
            return;
        }

        router.patch(
            `/rekrutmen/${applicant.id}/stage`,
            { stage: newStage },
            { preserveScroll: true },
        );
    }

    return (
        <AppLayout
            title="Rekrutmen (ATS)"
            subtitle="Candidate Pipeline & Hiring Management"
            actions={
                <ExportMenu
                    targets={[
                        { label: "Database Pelamar", url: "/export/pelamar" },
                        { label: "Performa Lowongan", url: "/export/lowongan-performa" },
                        { label: "Conversion Rate", url: "/export/conversion-rate" },
                    ]}
                />
            }
        >
            <Head title="Rekrutmen" />

            <div className="space-y-5">
                {/* Stat tiles */}
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Total pelamar"
                        value={angka(stats.totalApplicants)}
                        caption="seluruh tahap"
                        icon={<IconUsers className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Lowongan terbuka"
                        value={angka(stats.totalOpen)}
                        caption="status open"
                        icon={<IconFunnel className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Sudah hired"
                        value={angka(stats.totalHired)}
                        caption="konversi berhasil"
                        icon={<IconCheck className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Conversion rate"
                        value={`${stats.conversionRate.toString().replace(".", ",")}%`}
                        caption="hired / total pelamar"
                        icon={<IconAlert className="h-4 w-4" />}
                    />
                </section>

                {/* Filters */}
                <div className="flex flex-wrap items-center gap-3">
                    <Select
                        value={filters.vacancy_id ?? ""}
                        onChange={(e) =>
                            applyFilter({ vacancy_id: e.target.value ? Number(e.target.value) : null })
                        }
                        className="max-w-xs"
                    >
                        <option value="">Semua lowongan</option>
                        {vacancies.map((v) => (
                            <option key={v.id} value={v.id}>
                                {v.title} ({v.applicantCount})
                            </option>
                        ))}
                    </Select>

                    <Select
                        value={filters.department_id ?? ""}
                        onChange={(e) =>
                            applyFilter({ department_id: e.target.value ? Number(e.target.value) : null })
                        }
                        className="max-w-xs"
                    >
                        <option value="">Semua divisi</option>
                        {options.departments.map((d) => (
                            <option key={d.id} value={d.id}>
                                {d.name}
                            </option>
                        ))}
                    </Select>
                </div>

                {/* Kanban board */}
                <div className="overflow-x-auto pb-4">
                    <div className="flex gap-4" style={{ minWidth: "960px" }}>
                        {pipeline.map((stage) => (
                            <div
                                key={stage.key}
                                className={`flex-1 rounded-xl border border-hairline bg-surface-soft ${STAGE_COLORS[stage.key]} border-t-4`}
                            >
                                <div className="flex items-center justify-between px-4 py-3">
                                    <h3 className="text-xs font-semibold text-ink">
                                        {stage.label}
                                    </h3>
                                    <span className="grid h-5 min-w-5 place-items-center rounded-full bg-brand-100 px-1.5 text-[10px] font-semibold text-brand-700">
                                        {stage.applicants.length}
                                    </span>
                                </div>
                                <div className="space-y-2 px-3 pb-3" style={{ maxHeight: "65vh", overflowY: "auto" }}>
                                    {stage.applicants.length === 0 ? (
                                        <p className="py-6 text-center text-[11px] text-ink-muted">
                                            Kosong
                                        </p>
                                    ) : (
                                        stage.applicants.map((applicant) => (
                                            <KanbanCard
                                                key={applicant.id}
                                                applicant={applicant}
                                                onMove={(stage) => moveStage(applicant, stage)}
                                            />
                                        ))
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Rejected */}
                <Card
                    title={`Ditolak (${rejected.length})`}
                    action={
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setShowRejected(!showRejected)}
                        >
                            {showRejected ? "Sembunyikan" : "Tampilkan"}
                        </Button>
                    }
                >
                    {showRejected ? (
                        rejected.length === 0 ? (
                            <EmptyState message="Tidak ada pelamar yang ditolak." />
                        ) : (
                            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {rejected.map((a) => (
                                    <div
                                        key={a.id}
                                        className="rounded-lg border border-hairline p-3"
                                    >
                                        <p className="text-sm font-medium text-ink">{a.name}</p>
                                        <p className="text-xs text-ink-muted">{a.vacancyTitle}</p>
                                    </div>
                                ))}
                            </div>
                        )
                    ) : (
                        <p className="text-xs text-ink-muted">
                            {rejected.length} pelamar ditolak. Klik
                            &quot;Tampilkan&quot; untuk melihat.
                        </p>
                    )}
                </Card>

                {/* Lowongan overview */}
                <Card title="Ringkasan Lowongan" subtitle="Semua lowongan beserta jumlah pelamar dan hired">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[500px] text-sm">
                            <thead>
                                <tr className="border-b border-hairline text-left text-xs text-ink-muted">
                                    <th className="pb-2 font-medium">Lowongan</th>
                                    <th className="pb-2 font-medium">Divisi</th>
                                    <th className="pb-2 text-center font-medium">Pelamar</th>
                                    <th className="pb-2 text-center font-medium">Hired</th>
                                    <th className="pb-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {vacancies.map((v) => (
                                    <tr key={v.id} className="border-b border-hairline last:border-0">
                                        <td className="py-2.5">
                                            <p className="font-medium text-ink">{v.title}</p>
                                        </td>
                                        <td className="py-2.5 text-ink-soft">{v.department ?? "-"}</td>
                                        <td className="tabular py-2.5 text-center text-ink">{v.applicantCount}</td>
                                        <td className="tabular py-2.5 text-center text-ink">{v.hiredCount}</td>
                                        <td className="py-2.5">
                                            <Badge tone={v.status === "open" ? "good" : "neutral"}>
                                                {v.status}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>

            {/* Conversion Modal */}
            {converting && (
                <ConversionModal
                    applicant={converting}
                    employmentTypes={options.employmentTypes}
                    departments={options.departments}
                    schemaTypes={options.schemaTypes}
                    taxSchemes={options.taxSchemes}
                    onClose={() => setConverting(null)}
                />
            )}
        </AppLayout>
    );
}

function KanbanCard({
    applicant,
    onMove,
}: {
    applicant: ApplicantCard;
    onMove: (stage: string) => void;
}) {
    return (
        <div className="rounded-lg border border-hairline bg-surface p-3 transition hover:shadow-md hover:shadow-brand-700/5">
            <Link
                href={`/rekrutmen/${applicant.id}`}
                className="block"
            >
                <p className="text-sm font-medium text-ink hover:text-brand-600 transition">
                    {applicant.name}
                </p>
                <p className="mt-0.5 truncate text-[11px] text-ink-muted">
                    {applicant.vacancyTitle}
                </p>
                {applicant.department && (
                    <p className="text-[11px] text-ink-muted">{applicant.department}</p>
                )}
            </Link>

            <div className="mt-2 flex items-center justify-between gap-2">
                <span className="text-[10px] text-ink-muted">
                    {applicant.appliedAt}
                </span>
                <select
                    value={applicant.stage}
                    onChange={(e) => onMove(e.target.value)}
                    className="rounded border border-hairline bg-surface-soft px-1.5 py-0.5 text-[10px] font-medium text-ink-soft outline-none focus:border-brand-400"
                >
                    {ALL_STAGES.map((s) => (
                        <option key={s} value={s}>
                            → {STAGE_LABELS[s]}
                        </option>
                    ))}
                </select>
            </div>

            {applicant.cvPath && (
                <a
                    href={`/storage/${applicant.cvPath}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="mt-1.5 inline-block text-[10px] font-medium text-brand-600 hover:text-brand-700"
                    onClick={(e) => e.stopPropagation()}
                >
                    📄 Lihat CV
                </a>
            )}
        </div>
    );
}

function ConversionModal({
    applicant,
    employmentTypes,
    departments,
    schemaTypes,
    taxSchemes,
    onClose,
}: {
    applicant: ApplicantCard;
    employmentTypes: EmploymentTypeOption[];
    departments: { id: number; name: string }[];
    schemaTypes: string[];
    taxSchemes: string[];
    onClose: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({
        employment_type_id: "",
        department_id: "",
        position: "",
        basic_salary: 0,
        // Mitra fields
        schema_type: "hourly",
        rate_per_unit: 0,
        unit_label: "jam",
        tax_scheme: "pph21_tidak_berkesinambungan",
        custom_tax_percentage: 2.5,
    });

    const selectedType = employmentTypes.find(
        (t) => t.id === Number(data.employment_type_id),
    );
    const isMitra = selectedType?.category === "mitra";

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post(`/rekrutmen/${applicant.id}/convert`, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-ink/30 backdrop-blur-sm">
            <div className="mx-4 w-full max-w-lg rounded-2xl border border-hairline bg-surface p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                <h2 className="text-lg font-semibold text-ink">
                    Konversi ke Karyawan
                </h2>
                <p className="mt-1 text-xs text-ink-soft">
                    {applicant.name} — {applicant.vacancyTitle}
                </p>

                <form onSubmit={submit} className="mt-5 space-y-4">
                    <Field label="Entitas kerja" error={errors.employment_type_id} required>
                        <Select
                            value={data.employment_type_id}
                            onChange={(e) => setData("employment_type_id", e.target.value)}
                            required
                        >
                            <option value="">Pilih entitas…</option>
                            {employmentTypes.map((t) => (
                                <option key={t.id} value={t.id}>
                                    {t.name}
                                    {t.duration_months ? ` (${t.duration_months} bulan)` : ""}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Divisi" error={errors.department_id}>
                        <Select
                            value={data.department_id}
                            onChange={(e) => setData("department_id", e.target.value)}
                        >
                            <option value="">Pilih divisi…</option>
                            {departments.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.name}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Jabatan" error={errors.position}>
                        <Input
                            value={data.position}
                            onChange={(e) => setData("position", e.target.value)}
                            placeholder="mis. Backend Engineer"
                        />
                    </Field>

                    <Field label="Gaji pokok" error={errors.basic_salary} required>
                        <Input
                            type="number"
                            min={0}
                            step={100000}
                            value={data.basic_salary}
                            onChange={(e) => setData("basic_salary", Number(e.target.value))}
                        />
                    </Field>

                    {/* Mitra-specific fields */}
                    {isMitra && (
                        <div className="rounded-xl border border-hairline bg-surface-soft p-4 space-y-3">
                            <p className="text-xs font-semibold text-ink">
                                Skema Pembayaran Mitra
                            </p>
                            <Field label="Tipe skema" error={errors.schema_type}>
                                <Select
                                    value={data.schema_type}
                                    onChange={(e) => setData("schema_type", e.target.value)}
                                >
                                    {schemaTypes.map((type) => (
                                        <option key={type} value={type}>
                                            {SCHEMA_LABELS[type] ?? type}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label="Tarif" error={errors.rate_per_unit}>
                                <Input
                                    type="number"
                                    min={0}
                                    step={1000}
                                    value={data.rate_per_unit}
                                    onChange={(e) =>
                                        setData("rate_per_unit", Number(e.target.value))
                                    }
                                />
                            </Field>
                            <Field label="Satuan" error={errors.unit_label}>
                                <Input
                                    value={data.unit_label}
                                    onChange={(e) => setData("unit_label", e.target.value)}
                                />
                            </Field>
                            <Field label="Skema pajak" error={errors.tax_scheme}>
                                <Select
                                    value={data.tax_scheme}
                                    onChange={(e) => setData("tax_scheme", e.target.value)}
                                >
                                    {taxSchemes.map((scheme) => (
                                        <option key={scheme} value={scheme}>
                                            {TAX_LABELS[scheme] ?? scheme}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        </div>
                    )}

                    <div className="flex gap-2 pt-2">
                        <Button type="submit" disabled={processing}>
                            {processing ? "Mengkonversi…" : "Konversi"}
                        </Button>
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Batal
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}
