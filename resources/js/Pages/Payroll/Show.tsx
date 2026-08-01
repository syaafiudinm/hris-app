import { Head, router } from "@inertiajs/react";
import Card from "@/Components/Card";
import AppLayout from "@/Layouts/AppLayout";
import { Badge, Button, Select, statusTone } from "@/Components/ui";
import { rupiah } from "@/lib/format";

type Props = {
    payroll: {
        id: number;
        period: string;
        employee: {
            name: string | null;
            nik: string | null;
            position: string | null;
            department: string | null;
            type: string | null;
            isBpjsEligible: boolean;
        };
        payoutType: "employee" | "mitra";
        status: string;
        components: {
            basic: number;
            allowance: number;
            overtime: number;
            gross: number;
            bpjsEmployee: number;
            bpjsCompany: number;
            pph: number;
            other: number;
            net: number;
        };
        mitraSchema: {
            schemaType: string;
            rate: number;
            unitLabel: string | null;
            taxScheme: string;
        } | null;
    };
};

export default function PayrollShow({ payroll }: Props) {
    const { components: amount, employee } = payroll;
    const isMitra = payroll.payoutType === "mitra";

    return (
        <AppLayout
            title={isMitra ? "Payment Voucher" : "Slip Gaji"}
            subtitle={`${employee.name} · ${payroll.period}`}
            actions={
                <div className="flex items-center gap-2">
                    <Select
                        value={payroll.status}
                        onChange={(event) =>
                            router.patch(
                                `/payroll/${payroll.id}/status`,
                                { status: event.target.value },
                                { preserveScroll: true },
                            )
                        }
                        className="w-32"
                    >
                        <option value="draft">draft</option>
                        <option value="approved">approved</option>
                        <option value="paid">paid</option>
                    </Select>
                    <Button
                        onClick={() =>
                            window.open(
                                `/slip-gaji/${payroll.id}/dokumen`,
                                "_blank",
                            )
                        }
                    >
                        Unduh PDF
                    </Button>
                </div>
            }
        >
            <Head title={`Slip ${employee.name}`} />

            <div className="grid max-w-4xl gap-5 lg:grid-cols-3">
                <div className="space-y-5 lg:col-span-2">
                    <Card title="Rincian perhitungan">
                        <table className="w-full text-sm">
                            <tbody>
                                <SectionRow label="Penerimaan" />
                                <AmountRow
                                    label={
                                        isMitra
                                            ? "Nilai kompensasi dasar"
                                            : "Gaji pokok"
                                    }
                                    value={amount.basic}
                                />
                                {amount.allowance > 0 && (
                                    <AmountRow
                                        label={
                                            isMitra
                                                ? "Tunjangan / bonus"
                                                : "Tunjangan"
                                        }
                                        value={amount.allowance}
                                    />
                                )}
                                {!isMitra && (
                                    <AmountRow
                                        label="Upah lembur"
                                        value={amount.overtime}
                                    />
                                )}
                                <AmountRow
                                    label="Total bruto"
                                    value={amount.gross}
                                    emphasis
                                />

                                <SectionRow label="Potongan" />
                                {!isMitra && (
                                    <AmountRow
                                        label={
                                            employee.isBpjsEligible
                                                ? "BPJS (pekerja)"
                                                : `BPJS — tidak berlaku untuk ${employee.type}`
                                        }
                                        value={amount.bpjsEmployee}
                                    />
                                )}
                                <AmountRow
                                    label={
                                        isMitra
                                            ? "Pajak mitra"
                                            : "PPh 21 (TER PP 58/2023)"
                                    }
                                    value={amount.pph}
                                />
                                {amount.other > 0 && (
                                    <AmountRow
                                        label="Potongan lain / penalti"
                                        value={amount.other}
                                    />
                                )}

                                <tr>
                                    <td className="border-t-2 border-brand-500 py-3 text-sm font-semibold text-ink">
                                        {isMitra
                                            ? "Jumlah dibayarkan"
                                            : "Take home pay"}
                                    </td>
                                    <td className="tabular border-t-2 border-brand-500 py-3 text-right text-base font-semibold text-ink">
                                        {rupiah(amount.net)}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        {!isMitra && employee.isBpjsEligible && (
                            <p className="mt-4 rounded-lg bg-surface-soft px-3 py-2 text-[11px] text-ink-soft">
                                Kontribusi perusahaan{" "}
                                {rupiah(amount.bpjsCompany)} tidak memotong gaji
                                karyawan.
                            </p>
                        )}
                    </Card>
                </div>

                <div className="space-y-5">
                    <Card title="Penerima">
                        <dl className="space-y-3">
                            <Detail label="Nama" value={employee.name} />
                            <Detail label="NIK" value={employee.nik} />
                            <Detail label="Jabatan" value={employee.position} />
                            <Detail label="Divisi" value={employee.department} />
                            <div>
                                <dt className="text-[11px] text-ink-muted">
                                    Entitas kerja
                                </dt>
                                <dd className="mt-0.5">
                                    <Badge tone="brand">{employee.type}</Badge>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-[11px] text-ink-muted">
                                    Status slip
                                </dt>
                                <dd className="mt-0.5">
                                    <Badge
                                        tone={
                                            statusTone[payroll.status] ??
                                            "neutral"
                                        }
                                    >
                                        {payroll.status}
                                    </Badge>
                                </dd>
                            </div>
                        </dl>
                    </Card>

                    {payroll.mitraSchema && (
                        <Card title="Skema pembayaran">
                            <dl className="space-y-3">
                                <Detail
                                    label="Tipe"
                                    value={payroll.mitraSchema.schemaType.replace(
                                        /_/g,
                                        " ",
                                    )}
                                />
                                <Detail
                                    label="Tarif"
                                    value={`${rupiah(payroll.mitraSchema.rate)}${
                                        payroll.mitraSchema.unitLabel
                                            ? ` / ${payroll.mitraSchema.unitLabel}`
                                            : ""
                                    }`}
                                />
                                <Detail
                                    label="Skema pajak"
                                    value={payroll.mitraSchema.taxScheme.replace(
                                        /_/g,
                                        " ",
                                    )}
                                />
                            </dl>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

function SectionRow({ label }: { label: string }) {
    return (
        <tr>
            <td
                colSpan={2}
                className="pt-5 pb-1 text-[11px] font-medium tracking-wider text-ink-muted uppercase"
            >
                {label}
            </td>
        </tr>
    );
}

function AmountRow({
    label,
    value,
    emphasis,
}: {
    label: string;
    value: number;
    emphasis?: boolean;
}) {
    return (
        <tr className="border-b border-hairline">
            <td
                className={`py-2 ${emphasis ? "font-medium text-ink" : "text-ink-soft"}`}
            >
                {label}
            </td>
            <td
                className={`tabular py-2 text-right ${emphasis ? "font-medium text-ink" : "text-ink"}`}
            >
                {rupiah(value)}
            </td>
        </tr>
    );
}

function Detail({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <dt className="text-[11px] text-ink-muted">{label}</dt>
            <dd className="text-sm text-ink">{value ?? "-"}</dd>
        </div>
    );
}
