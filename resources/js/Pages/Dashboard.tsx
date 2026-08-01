import { Head } from "@inertiajs/react";
import AppLayout from "@/Layouts/AppLayout";
import Card from "@/Components/Card";
import StatTile from "@/Components/StatTile";
import CompensationColumns, {
    type CompensationRow,
} from "@/Components/charts/CompensationColumns";
import PipelineFunnel, {
    type PipelineStage,
} from "@/Components/charts/PipelineFunnel";
import WorkforceBars, {
    type WorkforceRow,
} from "@/Components/charts/WorkforceBars";
import {
    IconAlert,
    IconCheck,
    IconClock,
    IconDownload,
    IconShield,
    IconUsers,
    IconWallet,
} from "@/Components/Icons";
import { angka, rupiahCompact } from "@/lib/format";

type Summary = {
    totalWorkforce: number;
    employeeCount: number;
    mitraCount: number;
    probationCount: number;
    monthlyCost: number;
    monthlyCostDelta: number | null;
    periodLabel: string;
    pendingLeaves: number;
    expiringCount: number;
    fakeGpsFlags: number;
};

type AttendanceToday = {
    present: number;
    late: number;
    absent: number;
    leave: number;
    recorded: number;
    presentRate: number;
    avgMitraHours: number;
};

type ExpiringContract = {
    id: number;
    nik: string;
    name: string;
    position: string | null;
    department: string | null;
    type: string | null;
    category: string | null;
    endDate: string | null;
    daysLeft: number;
    severity: "critical" | "warning";
};

type Props = {
    summary: Summary;
    workforceDistribution: WorkforceRow[];
    compensationTrend: CompensationRow[];
    attendanceToday: AttendanceToday;
    expiringContracts: ExpiringContract[];
    recruitmentPipeline: {
        stages: PipelineStage[];
        totalApplicants: number;
        conversionRate: number;
    };
    generatedAt: string;
};

export default function Dashboard({
    summary,
    workforceDistribution,
    compensationTrend,
    attendanceToday,
    expiringContracts,
    recruitmentPipeline,
    generatedAt,
}: Props) {
    return (
        <AppLayout
            title="Executive Dashboard"
            subtitle="HR Analytics — rasio SDM & pengeluaran kompensasi"
            actions={
                <button
                    type="button"
                    className="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-3.5 py-2 text-xs font-medium text-white transition hover:bg-brand-600"
                >
                    <IconDownload className="h-4 w-4" />
                    Export data
                </button>
            }
        >
            <Head title="Dashboard" />

            <div className="space-y-5">
                {/* Ringkasan utama — satu angka pemimpin */}
                <section className="rounded-2xl border border-hairline bg-surface p-6 sm:p-7">
                    <div className="flex flex-wrap items-end justify-between gap-6">
                        <div>
                            <p className="text-xs font-medium text-ink-soft">
                                Total tenaga kerja aktif
                            </p>
                            <p className="mt-2 text-[56px] font-semibold leading-none tracking-tight text-ink">
                                {angka(summary.totalWorkforce)}
                            </p>
                            <p className="mt-3 text-sm text-ink-soft">
                                {angka(summary.employeeCount)} karyawan ·{" "}
                                {angka(summary.mitraCount)} mitra ·{" "}
                                {angka(summary.probationCount)} probation
                            </p>
                        </div>

                        <dl className="grid grid-cols-2 gap-x-8 gap-y-3 sm:grid-cols-3">
                            <div>
                                <dt className="text-[11px] text-ink-muted">
                                    Cuti menunggu approval
                                </dt>
                                <dd className="tabular mt-0.5 text-lg font-semibold text-ink">
                                    {angka(summary.pendingLeaves)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-[11px] text-ink-muted">
                                    Kontrak berakhir H-30
                                </dt>
                                <dd className="tabular mt-0.5 text-lg font-semibold text-ink">
                                    {angka(summary.expiringCount)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-[11px] text-ink-muted">
                                    Flag fake GPS (30 hari)
                                </dt>
                                <dd className="tabular mt-0.5 flex items-center gap-1.5 text-lg font-semibold text-ink">
                                    {summary.fakeGpsFlags > 0 && (
                                        <IconShield
                                            className="h-4 w-4"
                                            style={{ color: "#ec835a" }}
                                        />
                                    )}
                                    {angka(summary.fakeGpsFlags)}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </section>

                {/* Stat tiles */}
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Karyawan (PKWT & Probation)"
                        value={angka(summary.employeeCount)}
                        caption="entitas dengan hak penuh & probation"
                        icon={<IconUsers className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Mitra / Freelance"
                        value={angka(summary.mitraCount)}
                        caption="skema pembayaran custom"
                        icon={<IconWallet className="h-4 w-4" />}
                    />
                    <StatTile
                        label={`Biaya kompensasi ${summary.periodLabel}`}
                        value={rupiahCompact(summary.monthlyCost)}
                        caption="payroll karyawan + fee mitra"
                        delta={summary.monthlyCostDelta}
                        upIsGood={false}
                        icon={<IconWallet className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Kehadiran hari ini"
                        value={`${attendanceToday.presentRate.toString().replace(".", ",")}%`}
                        caption={`${angka(attendanceToday.recorded)} record absensi`}
                        icon={<IconClock className="h-4 w-4" />}
                    />
                </section>

                {/* Grafik utama */}
                <section className="grid gap-5 xl:grid-cols-5">
                    <Card
                        title="Biaya kompensasi 6 bulan terakhir"
                        subtitle="Payroll karyawan dipisah dari fee mitra"
                        className="xl:col-span-3"
                    >
                        <CompensationColumns data={compensationTrend} />
                    </Card>

                    <Card
                        title="Distribusi entitas kerja"
                        subtitle="Komposisi tenaga kerja aktif"
                        className="xl:col-span-2"
                    >
                        <WorkforceBars data={workforceDistribution} />
                    </Card>
                </section>

                {/* Kontrak & absensi */}
                <section className="grid gap-5 xl:grid-cols-5">
                    <Card
                        title="Peringatan kontrak kadaluarsa"
                        subtitle="Probation, PKWT, dan Mitra yang berakhir dalam 30 hari"
                        className="xl:col-span-3"
                        action={
                            <button
                                type="button"
                                className="inline-flex items-center gap-1.5 rounded-lg border border-hairline px-2.5 py-1.5 text-[11px] font-medium text-ink-soft transition hover:bg-surface-soft"
                            >
                                <IconDownload className="h-3.5 w-3.5" />
                                Export
                            </button>
                        }
                    >
                        {expiringContracts.length === 0 ? (
                            <p className="py-6 text-center text-sm text-ink-muted">
                                Tidak ada kontrak yang berakhir dalam 30 hari.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[520px] text-sm">
                                    <thead>
                                        <tr className="border-b border-hairline text-left text-xs text-ink-muted">
                                            <th className="pb-2 font-medium">
                                                Nama
                                            </th>
                                            <th className="pb-2 font-medium">
                                                Entitas
                                            </th>
                                            <th className="pb-2 font-medium">
                                                Berakhir
                                            </th>
                                            <th className="pb-2 text-right font-medium">
                                                Sisa
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {expiringContracts.map((row) => (
                                            <tr
                                                key={row.id}
                                                className="border-b border-hairline last:border-0"
                                            >
                                                <td className="py-2.5">
                                                    <p className="font-medium text-ink">
                                                        {row.name}
                                                    </p>
                                                    <p className="text-xs text-ink-muted">
                                                        {row.nik}
                                                        {row.department
                                                            ? ` · ${row.department}`
                                                            : ""}
                                                    </p>
                                                </td>
                                                <td className="py-2.5">
                                                    <span className="inline-flex rounded-md bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-700">
                                                        {row.type}
                                                    </span>
                                                </td>
                                                <td className="tabular py-2.5 whitespace-nowrap text-ink-soft">
                                                    {row.endDate}
                                                </td>
                                                <td className="py-2.5 text-right">
                                                    <span
                                                        className="inline-flex items-center gap-1 text-xs font-medium"
                                                        style={{
                                                            color:
                                                                row.severity ===
                                                                "critical"
                                                                    ? "#d03b3b"
                                                                    : "#a06a00",
                                                        }}
                                                    >
                                                        <IconAlert className="h-3.5 w-3.5" />
                                                        <span className="tabular">
                                                            {row.daysLeft} hari
                                                        </span>
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>

                    <div className="space-y-5 xl:col-span-2">
                        <Card
                            title="Absensi hari ini"
                            subtitle={`Rata-rata jam kerja mitra 30 hari: ${attendanceToday.avgMitraHours.toString().replace(".", ",")} jam/hari`}
                        >
                            <ul className="space-y-2.5">
                                {[
                                    {
                                        label: "Hadir tepat waktu",
                                        value: attendanceToday.present,
                                        color: "#0ca30c",
                                        icon: (
                                            <IconCheck className="h-3.5 w-3.5" />
                                        ),
                                    },
                                    {
                                        label: "Terlambat",
                                        value: attendanceToday.late,
                                        color: "#a06a00",
                                        icon: (
                                            <IconClock className="h-3.5 w-3.5" />
                                        ),
                                    },
                                    {
                                        label: "Cuti / izin",
                                        value: attendanceToday.leave,
                                        color: "#55677d",
                                        icon: (
                                            <IconClock className="h-3.5 w-3.5" />
                                        ),
                                    },
                                    {
                                        label: "Tanpa keterangan",
                                        value: attendanceToday.absent,
                                        color: "#d03b3b",
                                        icon: (
                                            <IconAlert className="h-3.5 w-3.5" />
                                        ),
                                    },
                                ].map((row) => (
                                    <li
                                        key={row.label}
                                        className="flex items-center justify-between gap-3 text-sm"
                                    >
                                        <span
                                            className="inline-flex items-center gap-2 text-ink-soft"
                                            style={{ color: row.color }}
                                        >
                                            {row.icon}
                                            <span className="text-ink-soft">
                                                {row.label}
                                            </span>
                                        </span>
                                        <span className="tabular font-medium text-ink">
                                            {angka(row.value)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </Card>

                        <Card
                            title="Pipeline rekrutmen"
                            subtitle={`${angka(recruitmentPipeline.totalApplicants)} pelamar · conversion ${recruitmentPipeline.conversionRate.toString().replace(".", ",")}%`}
                        >
                            <PipelineFunnel
                                stages={recruitmentPipeline.stages}
                            />
                        </Card>
                    </div>
                </section>

                <p className="pb-2 text-center text-[11px] text-ink-muted">
                    Data per {generatedAt}
                </p>
            </div>
        </AppLayout>
    );
}
