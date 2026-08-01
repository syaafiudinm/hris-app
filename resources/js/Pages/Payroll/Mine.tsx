import { Head } from "@inertiajs/react";
import Card from "@/Components/Card";
import AppLayout from "@/Layouts/AppLayout";
import { Badge, EmptyState, statusTone } from "@/Components/ui";
import { rupiah } from "@/lib/format";

type Props = {
    payrolls: {
        id: number;
        period: string;
        gross: number;
        net: number;
        payoutType: "employee" | "mitra";
        status: string;
    }[];
};

export default function PayrollMine({ payrolls }: Props) {
    return (
        <AppLayout
            title="Slip Gaji Saya"
            subtitle="Riwayat penerimaan 24 periode terakhir"
        >
            <Head title="Slip Gaji Saya" />

            <Card title="Riwayat penerimaan" className="max-w-3xl">
                {payrolls.length === 0 ? (
                    <EmptyState message="Belum ada slip yang diterbitkan untuk Anda." />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[520px] text-sm">
                            <thead>
                                <tr className="border-b border-hairline text-left text-xs text-ink-muted">
                                    <th className="pb-2 font-medium">Periode</th>
                                    <th className="pb-2 text-right font-medium">
                                        Bruto
                                    </th>
                                    <th className="pb-2 text-right font-medium">
                                        Diterima
                                    </th>
                                    <th className="pb-2 font-medium">Status</th>
                                    <th className="pb-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {payrolls.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-hairline last:border-0"
                                    >
                                        <td className="py-2.5 whitespace-nowrap text-ink">
                                            {row.period}
                                        </td>
                                        <td className="tabular py-2.5 text-right text-ink-soft">
                                            {rupiah(row.gross)}
                                        </td>
                                        <td className="tabular py-2.5 text-right font-medium text-ink">
                                            {rupiah(row.net)}
                                        </td>
                                        <td className="py-2.5">
                                            <Badge
                                                tone={
                                                    statusTone[row.status] ??
                                                    "neutral"
                                                }
                                            >
                                                {row.status}
                                            </Badge>
                                        </td>
                                        <td className="py-2.5 text-right">
                                            <a
                                                href={`/slip-gaji/${row.id}/dokumen`}
                                                className="text-[11px] font-medium text-brand-600 hover:text-brand-700"
                                            >
                                                Unduh{" "}
                                                {row.payoutType === "mitra"
                                                    ? "voucher"
                                                    : "slip"}
                                            </a>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
        </AppLayout>
    );
}
