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
        slipType: "salary" | "incentive";
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
        details: PayrollDetails | null;
    };
};

type BpjsItem = {
    label: string;
    companyRate: number;
    workerRate: number;
    companyAmount: number;
    workerAmount: number;
    total: number;
};

type PayrollDetails = {
    bpjs?: {
        wageBase: number;
        items: BpjsItem[];
        companyPortion: number;
        workerPortion: number;
        grandTotal: number;
    } | null;
    // Skema penjualan mitra — slip gaji
    basis?: "allowance" | "bonus";
    presentDays?: number;
    workingDays?: number;
    totalUnits?: number;
    monthlyAllowance?: number;
    monthlyBase?: number;
    dailyBaseRate?: number;
    bonusPercentage?: number;
    umpReference?: number;
    // Slip insentif
    lines?: { product: string; quantity: number; rate: number; subtotal: number }[];
    incentiveAmount?: number;
    taxAmount?: number;
};

export default function PayrollShow({ payroll }: Props) {
    const { components: amount, employee } = payroll;
    const isMitra = payroll.payoutType === "mitra";

    return (
        <AppLayout
            title={
                payroll.slipType === "incentive"
                    ? "Voucher Insentif Penjualan"
                    : isMitra
                      ? "Payment Voucher"
                      : "Slip Gaji"
            }
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

                    </Card>

                    {payroll.details?.basis && (
                        <Card
                            title="Dasar gaji skema penjualan"
                            subtitle={`${payroll.details.presentDays} dari ${payroll.details.workingDays} hari kerja`}
                        >
                            <table className="w-full text-sm">
                                <tbody>
                                    <AmountRow
                                        label={
                                            payroll.details.basis === "bonus"
                                                ? `Bonus pencapaian — ${payroll.details.totalUnits} unit → ${payroll.details.bonusPercentage}% UMP`
                                                : `Uang makan & transport — ${payroll.details.totalUnits} unit, belum capai tier`
                                        }
                                        value={payroll.details.monthlyBase ?? 0}
                                    />
                                    {payroll.details.basis === "bonus" && (
                                        <tr className="border-b border-hairline">
                                            <td className="py-2 text-ink-muted line-through">
                                                Uang makan &amp; transport —
                                                digantikan bonus
                                            </td>
                                            <td className="tabular py-2 text-right text-ink-muted line-through">
                                                {rupiah(
                                                    payroll.details
                                                        .monthlyAllowance ?? 0,
                                                )}
                                            </td>
                                        </tr>
                                    )}
                                    <AmountRow
                                        label={`Tarif harian (÷ ${payroll.details.workingDays} hari)`}
                                        value={payroll.details.dailyBaseRate ?? 0}
                                    />
                                    <AmountRow
                                        label={`Dibayarkan (× ${payroll.details.presentDays} hari hadir)`}
                                        value={amount.gross}
                                        emphasis
                                    />
                                </tbody>
                            </table>

                            {payroll.details.basis === "bonus" && (
                                <p className="mt-3 rounded-lg bg-surface-soft px-3 py-2 text-[11px] text-ink-soft">
                                    Bonus pencapaian <strong>menggantikan</strong>{" "}
                                    uang makan &amp; transport sebagai dasar gaji,
                                    bukan menambahnya. Insentif per unit dibayar
                                    penuh pada slip terpisah.
                                </p>
                            )}
                        </Card>
                    )}

                    {payroll.details?.lines && (
                        <Card
                            title="Rincian insentif per produk"
                            subtitle={`${payroll.details.totalUnits} unit terjual`}
                        >
                            <table className="w-full text-sm">
                                <tbody>
                                    {payroll.details.lines.map((baris) => (
                                        <AmountRow
                                            key={baris.product}
                                            label={`${baris.product} — ${baris.quantity} unit × ${rupiah(baris.rate)}`}
                                            value={baris.subtotal}
                                        />
                                    ))}
                                    <AmountRow
                                        label="Total insentif"
                                        value={payroll.details.incentiveAmount ?? 0}
                                        emphasis
                                    />
                                </tbody>
                            </table>
                        </Card>
                    )}

                    {payroll.details?.bpjs && (
                        <Card
                            title="Iuran BPJS ditanggung perusahaan"
                            subtitle={`Dasar upah ${rupiah(payroll.details.bpjs.wageBase)} — tidak memotong penerimaan`}
                        >
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[460px] text-sm">
                                    <thead>
                                        <tr className="border-b border-hairline text-left text-xs text-ink-muted">
                                            <th className="pb-2 font-medium">
                                                Program
                                            </th>
                                            <th className="pb-2 text-right font-medium">
                                                Perusahaan
                                            </th>
                                            <th className="pb-2 text-right font-medium">
                                                Porsi pekerja
                                            </th>
                                            <th className="pb-2 text-right font-medium">
                                                Jumlah
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {payroll.details.bpjs.items.map((item) => (
                                            <tr
                                                key={item.label}
                                                className="border-b border-hairline last:border-0"
                                            >
                                                <td className="py-2 text-ink-soft">
                                                    {item.label}
                                                </td>
                                                <td className="tabular py-2 text-right text-ink">
                                                    {rupiah(item.companyAmount)}
                                                    <span className="block text-[10px] text-ink-muted">
                                                        {item.companyRate}%
                                                    </span>
                                                </td>
                                                <td className="tabular py-2 text-right text-ink">
                                                    {item.workerRate > 0
                                                        ? rupiah(item.workerAmount)
                                                        : "—"}
                                                    {item.workerRate > 0 && (
                                                        <span className="block text-[10px] text-ink-muted">
                                                            {item.workerRate}%
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="tabular py-2 text-right font-medium text-ink">
                                                    {rupiah(item.total)}
                                                </td>
                                            </tr>
                                        ))}
                                        <tr>
                                            <td className="py-2 text-sm font-semibold text-ink">
                                                Total
                                            </td>
                                            <td className="tabular py-2 text-right font-semibold text-ink">
                                                {rupiah(
                                                    payroll.details.bpjs
                                                        .companyPortion,
                                                )}
                                            </td>
                                            <td className="tabular py-2 text-right font-semibold text-ink">
                                                {rupiah(
                                                    payroll.details.bpjs
                                                        .workerPortion,
                                                )}
                                            </td>
                                            <td className="tabular py-2 text-right font-semibold text-ink">
                                                {rupiah(
                                                    payroll.details.bpjs
                                                        .grandTotal,
                                                )}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <p className="mt-3 rounded-lg bg-surface-soft px-3 py-2 text-[11px] text-ink-soft">
                                Porsi pekerja ikut dibayarkan perusahaan, sehingga
                                potongan BPJS pada slip bernilai {rupiah(0)}.
                            </p>
                        </Card>
                    )}
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
