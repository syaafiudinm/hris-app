import { Head, router, useForm } from "@inertiajs/react";
import Card from "@/Components/Card";
import AppLayout from "@/Layouts/AppLayout";
import { Badge, Button, EmptyState, Field, Input, Select, Textarea, statusTone } from "@/Components/ui";
import { IconAlert, IconCheck } from "@/Components/Icons";

type LeaveRow = {
    id: number;
    typeLabel: string;
    startDate: string;
    endDate: string;
    totalDays: number;
    reason: string | null;
    status: string;
};

type Props = {
    employee: { name: string; type: string | null; category: string | null };
    policy: {
        isLeaveEligible: boolean;
        blockedReason: string | null;
        quota: number;
        used: number;
        remaining: number;
    };
    requests: LeaveRow[];
    options: { leaveTypes: string[]; allowedTypes: string[] };
};

const TYPE_LABELS: Record<string, string> = {
    annual: "Cuti Tahunan",
    sick: "Izin Sakit",
    unpaid: "Izin Tanpa Gaji",
    maternity: "Cuti Melahirkan",
    special: "Cuti Khusus",
};

export default function LeavesMine({
    employee,
    policy,
    requests,
    options,
}: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        leave_type: options.allowedTypes[0] ?? "sick",
        start_date: "",
        end_date: "",
        reason: "",
    });

    return (
        <AppLayout
            title="Cuti & Izin Saya"
            subtitle={`${employee.name} · ${employee.type}`}
        >
            <Head title="Cuti & Izin Saya" />

            <div className="grid gap-5 xl:grid-cols-3">
                <div className="space-y-5 xl:col-span-2">
                    <Card title="Ajukan cuti / izin">
                        {policy.blockedReason && (
                            <div className="mb-4 flex gap-2.5 rounded-xl bg-surface-soft px-4 py-3">
                                <IconAlert
                                    className="mt-0.5 h-4 w-4 shrink-0"
                                    style={{ color: "#8a6100" }}
                                />
                                <p className="text-xs text-ink-soft">
                                    {policy.blockedReason}
                                </p>
                            </div>
                        )}

                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                post("/cuti-saya", {
                                    preserveScroll: true,
                                    onSuccess: () => reset("start_date", "end_date", "reason"),
                                });
                            }}
                            className="space-y-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-3">
                                <Field
                                    label="Jenis"
                                    error={errors.leave_type}
                                    required
                                >
                                    <Select
                                        value={data.leave_type}
                                        onChange={(event) =>
                                            setData(
                                                "leave_type",
                                                event.target.value,
                                            )
                                        }
                                    >
                                        {options.allowedTypes.map((type) => (
                                            <option key={type} value={type}>
                                                {TYPE_LABELS[type] ?? type}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>

                                <Field
                                    label="Mulai"
                                    error={errors.start_date}
                                    required
                                >
                                    <Input
                                        type="date"
                                        value={data.start_date}
                                        onChange={(event) =>
                                            setData(
                                                "start_date",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Selesai"
                                    error={errors.end_date}
                                    required
                                >
                                    <Input
                                        type="date"
                                        value={data.end_date}
                                        onChange={(event) =>
                                            setData(
                                                "end_date",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>

                            <Field label="Alasan" error={errors.reason}>
                                <Textarea
                                    rows={3}
                                    value={data.reason}
                                    onChange={(event) =>
                                        setData("reason", event.target.value)
                                    }
                                    placeholder="Keperluan pribadi, kontrol dokter, dll."
                                />
                            </Field>

                            <Button type="submit" disabled={processing}>
                                {processing ? "Mengirim…" : "Kirim pengajuan"}
                            </Button>
                        </form>
                    </Card>

                    <Card title="Riwayat pengajuan">
                        {requests.length === 0 ? (
                            <EmptyState message="Belum ada pengajuan." />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[560px] text-sm">
                                    <thead>
                                        <tr className="border-b border-hairline text-left text-xs text-ink-muted">
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
                                            <th className="pb-2" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {requests.map((row) => (
                                            <tr
                                                key={row.id}
                                                className="border-b border-hairline last:border-0"
                                            >
                                                <td className="py-2.5 text-ink">
                                                    {row.typeLabel}
                                                </td>
                                                <td className="tabular py-2.5 whitespace-nowrap text-ink-soft">
                                                    {row.startDate} –{" "}
                                                    {row.endDate}
                                                </td>
                                                <td className="tabular py-2.5 text-right text-ink">
                                                    {row.totalDays}
                                                </td>
                                                <td className="py-2.5">
                                                    <Badge
                                                        tone={
                                                            statusTone[
                                                                row.status
                                                            ] ?? "neutral"
                                                        }
                                                    >
                                                        {row.status}
                                                    </Badge>
                                                </td>
                                                <td className="py-2.5 text-right">
                                                    {row.status === "pending" && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                router.delete(
                                                                    `/cuti-saya/${row.id}`,
                                                                    {
                                                                        preserveScroll:
                                                                            true,
                                                                    },
                                                                )
                                                            }
                                                            className="text-[11px] font-medium text-[#b53232]"
                                                        >
                                                            Batalkan
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                </div>

                <Card title="Saldo cuti tahunan">
                    {policy.isLeaveEligible ? (
                        <>
                            <p className="text-[56px] leading-none font-semibold tracking-tight text-ink">
                                {policy.remaining}
                            </p>
                            <p className="mt-2 text-sm text-ink-soft">
                                hari tersisa dari kuota {policy.quota} hari
                            </p>

                            {/* Meter: isi memakai warna aksen, track step lebih terang */}
                            <div className="mt-4 h-2 overflow-hidden rounded-full bg-brand-100">
                                <div
                                    className="h-full rounded-full bg-brand-500"
                                    style={{
                                        width: `${policy.quota > 0 ? (policy.used / policy.quota) * 100 : 0}%`,
                                    }}
                                />
                            </div>
                            <p className="mt-2 text-xs text-ink-muted">
                                {policy.used} hari terpakai tahun ini
                            </p>

                            <p className="mt-5 flex items-start gap-2 border-t border-hairline pt-4 text-xs text-ink-soft">
                                <IconCheck
                                    className="mt-0.5 h-3.5 w-3.5 shrink-0"
                                    style={{ color: "#0ca30c" }}
                                />
                                Entitas {employee.type} berhak atas cuti tahunan
                                penuh.
                            </p>
                        </>
                    ) : (
                        <div className="py-2">
                            <p className="text-sm font-medium text-ink">
                                Tidak ada kuota cuti tahunan
                            </p>
                            <p className="mt-1.5 text-xs text-ink-soft">
                                {policy.blockedReason}
                            </p>
                            <p className="mt-4 border-t border-hairline pt-4 text-xs text-ink-muted">
                                Pengajuan cuti tahunan akan ditolak server dengan
                                kode 403, bukan hanya disembunyikan dari tampilan.
                            </p>
                        </div>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
