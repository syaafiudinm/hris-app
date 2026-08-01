import { Head, router } from "@inertiajs/react";
import Card from "@/Components/Card";
import AppLayout from "@/Layouts/AppLayout";
import { Badge, Button, LinkButton, statusTone } from "@/Components/ui";
import { IconAlert, IconCheck } from "@/Components/Icons";
import { rupiah } from "@/lib/format";

type Props = {
    employee: {
        id: number;
        nik: string;
        name: string;
        email: string | null;
        phone: string | null;
        position: string | null;
        department: string | null;
        type: string | null;
        category: string | null;
        joinDate: string | null;
        contractStart: string | null;
        contractEnd: string | null;
        daysLeft: number | null;
        salary: number;
        status: string;
        isLeaveEligible: boolean;
        isBpjsEligible: boolean;
        leaveQuota: number;
    };
    mitraSchema: {
        schemaType: string;
        rate: number;
        unitLabel: string | null;
        taxScheme: string;
        taxPercentage: number;
    } | null;
    exit: {
        id: number;
        typeLabel: string;
        lastWorkingDate: string;
        tenure: string;
        status: string;
        paklaringNumber: string | null;
    } | null;
    recentPayrolls: {
        id: number;
        period: string;
        gross: number;
        net: number;
    }[];
};

const SCHEMA_LABELS: Record<string, string> = {
    fixed_project: "Fixed Project Fee",
    hourly: "Hourly Rate",
    daily: "Daily Rate",
    milestone: "Deliverable / Milestone",
    unit: "Unit / Output",
};

export default function EmployeeShow({
    employee,
    mitraSchema,
    exit,
    recentPayrolls,
}: Props) {
    return (
        <AppLayout
            title={employee.name}
            subtitle={`${employee.nik}${employee.position ? ` · ${employee.position}` : ""}`}
            actions={
                <div className="flex items-center gap-2">
                    <LinkButton href={`/employees/${employee.id}/edit`}>
                        Ubah
                    </LinkButton>
                    <Button
                        variant="danger"
                        onClick={() => {
                            if (
                                confirm(
                                    `Hapus data ${employee.name}? Tindakan ini tidak dapat dibatalkan.`,
                                )
                            ) {
                                router.delete(`/employees/${employee.id}`);
                            }
                        }}
                    >
                        Hapus
                    </Button>
                </div>
            }
        >
            <Head title={employee.name} />

            <div className="grid gap-5 xl:grid-cols-3">
                <div className="space-y-5 xl:col-span-2">
                    <Card title="Informasi kepegawaian">
                        <dl className="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                            <Detail label="Entitas kerja">
                                <Badge tone="brand">{employee.type}</Badge>
                            </Detail>
                            <Detail label="Status">
                                <Badge
                                    tone={
                                        statusTone[employee.status] ?? "neutral"
                                    }
                                >
                                    {employee.status}
                                </Badge>
                            </Detail>
                            <Detail label="Divisi">
                                {employee.department ?? "-"}
                            </Detail>
                            <Detail label="Email">
                                {employee.email ?? "-"}
                            </Detail>
                            <Detail label="Telepon">
                                {employee.phone ?? "-"}
                            </Detail>
                            <Detail label="Bergabung">
                                {employee.joinDate ?? "-"}
                            </Detail>
                            <Detail label="Kontrak mulai">
                                {employee.contractStart ?? "-"}
                            </Detail>
                            <Detail label="Kontrak berakhir">
                                {employee.contractEnd ?? "-"}
                                {employee.daysLeft !== null &&
                                    employee.daysLeft >= 0 &&
                                    employee.daysLeft <= 30 && (
                                        <span className="ml-2 text-xs font-medium text-[#b53232]">
                                            H-{employee.daysLeft}
                                        </span>
                                    )}
                            </Detail>
                            {employee.category !== "mitra" && (
                                <Detail label="Gaji pokok">
                                    {rupiah(employee.salary)}
                                </Detail>
                            )}
                        </dl>
                    </Card>

                    {mitraSchema && (
                        <Card
                            title="Skema pembayaran mitra"
                            subtitle="Dibaca mesin payroll saat periode dijalankan"
                            action={
                                <LinkButton href="/skema-mitra">
                                    Kelola
                                </LinkButton>
                            }
                        >
                            <dl className="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                                <Detail label="Tipe skema">
                                    {SCHEMA_LABELS[mitraSchema.schemaType] ??
                                        mitraSchema.schemaType}
                                </Detail>
                                <Detail label="Tarif">
                                    {rupiah(mitraSchema.rate)}
                                    {mitraSchema.unitLabel
                                        ? ` / ${mitraSchema.unitLabel}`
                                        : ""}
                                </Detail>
                                <Detail label="Skema pajak">
                                    {mitraSchema.taxScheme.replace(/_/g, " ")}
                                </Detail>
                                <Detail label="Persentase pajak">
                                    {mitraSchema.taxPercentage}%
                                </Detail>
                            </dl>
                        </Card>
                    )}

                    {exit && (
                        <Card
                            title="Proses keluar"
                            subtitle="Offboarding dan surat keterangan kerja"
                            action={
                                <LinkButton href="/proses-keluar">
                                    Kelola
                                </LinkButton>
                            }
                        >
                            <dl className="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                                <Detail label="Jenis">{exit.typeLabel}</Detail>
                                <Detail label="Status">
                                    <Badge
                                        tone={
                                            exit.status === "completed"
                                                ? "good"
                                                : "warning"
                                        }
                                    >
                                        {exit.status}
                                    </Badge>
                                </Detail>
                                <Detail label="Hari kerja terakhir">
                                    {exit.lastWorkingDate}
                                </Detail>
                                <Detail label="Masa kerja">{exit.tenure}</Detail>
                            </dl>

                            {exit.paklaringNumber && (
                                <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-hairline pt-4">
                                    <p className="text-xs text-ink-soft">
                                        Paklaring{" "}
                                        <span className="tabular font-medium text-ink">
                                            {exit.paklaringNumber}
                                        </span>
                                    </p>
                                    <a
                                        href={`/proses-keluar/${exit.id}/paklaring`}
                                        className="text-[11px] font-medium text-brand-600 hover:text-brand-700"
                                    >
                                        Unduh PDF
                                    </a>
                                </div>
                            )}
                        </Card>
                    )}

                    <Card title="Riwayat penggajian terakhir">
                        {recentPayrolls.length === 0 ? (
                            <p className="py-6 text-center text-sm text-ink-muted">
                                Belum ada slip untuk karyawan ini.
                            </p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-hairline text-left text-xs text-ink-muted">
                                        <th className="pb-2 font-medium">
                                            Periode
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            Bruto
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            Netto
                                        </th>
                                        <th className="pb-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentPayrolls.map((payroll) => (
                                        <tr
                                            key={payroll.id}
                                            className="border-b border-hairline last:border-0"
                                        >
                                            <td className="tabular py-2 text-ink">
                                                {payroll.period}
                                            </td>
                                            <td className="tabular py-2 text-right text-ink-soft">
                                                {rupiah(payroll.gross)}
                                            </td>
                                            <td className="tabular py-2 text-right font-medium text-ink">
                                                {rupiah(payroll.net)}
                                            </td>
                                            <td className="py-2 text-right">
                                                <a
                                                    href={`/slip-gaji/${payroll.id}/dokumen`}
                                                    className="text-[11px] font-medium text-brand-600 hover:text-brand-700"
                                                >
                                                    PDF
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </Card>
                </div>

                {/* Aturan yang ditegakkan sistem untuk entitas ini */}
                <Card
                    title="Hak berdasarkan entitas"
                    subtitle="Ditegakkan server-side, bukan sekadar tampilan"
                >
                    <ul className="space-y-3">
                        <Rule
                            active={employee.isLeaveEligible}
                            label="Cuti tahunan"
                            detail={
                                employee.isLeaveEligible
                                    ? `Kuota ${employee.leaveQuota} hari per tahun.`
                                    : "Pengajuan cuti tahunan ditolak sistem (403)."
                            }
                        />
                        <Rule
                            active={employee.isBpjsEligible}
                            label="BPJS Kesehatan & Ketenagakerjaan"
                            detail={
                                employee.isBpjsEligible
                                    ? "Potongan pekerja & kontribusi perusahaan dihitung."
                                    : "Potongan dan kontribusi BPJS di-set 0 oleh mesin payroll."
                            }
                        />
                        <Rule
                            active={employee.category !== "mitra"}
                            label="PPh 21 TER"
                            detail={
                                employee.category === "mitra"
                                    ? "Mitra memakai skema pajak sendiri (PPh 21 bukan pegawai / PPh 23)."
                                    : "Dipotong dengan tarif efektif bulanan PP 58/2023."
                            }
                        />
                    </ul>
                </Card>
            </div>
        </AppLayout>
    );
}

function Detail({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <dt className="text-[11px] text-ink-muted">{label}</dt>
            <dd className="mt-0.5 text-sm text-ink">{children}</dd>
        </div>
    );
}

function Rule({
    active,
    label,
    detail,
}: {
    active: boolean;
    label: string;
    detail: string;
}) {
    return (
        <li className="flex gap-2.5">
            <span
                className="mt-0.5 shrink-0"
                style={{ color: active ? "#0ca30c" : "#8fa1b6" }}
                aria-hidden
            >
                {active ? (
                    <IconCheck className="h-4 w-4" />
                ) : (
                    <IconAlert className="h-4 w-4" />
                )}
            </span>
            <div>
                <p className="text-sm font-medium text-ink">
                    {label}{" "}
                    <span className="text-[11px] font-normal text-ink-muted">
                        {active ? "aktif" : "tidak berlaku"}
                    </span>
                </p>
                <p className="text-xs text-ink-soft">{detail}</p>
            </div>
        </li>
    );
}
