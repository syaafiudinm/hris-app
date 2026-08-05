import { Head, router, useForm } from "@inertiajs/react";
import AppLayout from "@/Layouts/AppLayout";
import Card from "@/Components/Card";
import {
    Badge,
    Button,
    EmptyState,
    Field,
    Input,
    Select,
    Textarea,
} from "@/Components/ui";

type Item = {
    id: number;
    label: string;
    category: string;
    available: number;
};

type Loan = {
    id: number;
    item: string | null;
    itemCode: string | null;
    quantity: number;
    status: string;
    statusLabel: string;
    purpose: string;
    dueDate: string | null;
    daysToDue: number;
    isOverdue: boolean;
    handedOverAt: string | null;
    returnedAt: string | null;
    decisionNote: string | null;
    returnNote: string | null;
};

type Props = {
    items: Item[];
    loans: Loan[];
    summary: { open: number; overdue: number };
};

const TONE: Record<string, "neutral" | "brand" | "good" | "warning" | "critical"> =
    {
        requested: "warning",
        approved: "brand",
        borrowed: "brand",
        returned: "good",
        rejected: "neutral",
        lost: "critical",
    };

export default function InventoryMine({ items, loans, summary }: Props) {
    const form = useForm({
        inventory_item_id: "",
        quantity: 1,
        purpose: "",
        due_date: "",
    });

    const selected = items.find(
        (item) => String(item.id) === form.data.inventory_item_id,
    );

    return (
        <AppLayout
            title="Pinjam Inventaris"
            subtitle="Ajukan peminjaman aset perusahaan dan pantau statusnya"
        >
            <Head title="Pinjam Inventaris" />

            <div className="grid gap-5 xl:grid-cols-3">
                <div className="space-y-5 xl:col-span-2">
                    <Card
                        title="Peminjaman saya"
                        subtitle={
                            summary.overdue > 0
                                ? `${summary.open} berjalan · ${summary.overdue} lewat jatuh tempo`
                                : `${summary.open} peminjaman berjalan`
                        }
                    >
                        {loans.length === 0 ? (
                            <EmptyState message="Anda belum pernah mengajukan peminjaman." />
                        ) : (
                            <ul className="space-y-3">
                                {loans.map((loan) => (
                                    <li
                                        key={loan.id}
                                        className="rounded-xl border border-hairline p-4"
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="font-medium text-ink">
                                                    {loan.item}
                                                    <span className="ml-1.5 text-xs font-normal text-ink-muted">
                                                        ×{loan.quantity}
                                                    </span>
                                                </p>
                                                <p className="tabular mt-0.5 text-[11px] text-ink-muted">
                                                    {loan.itemCode}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-1.5">
                                                {loan.isOverdue && (
                                                    <Badge tone="critical">
                                                        telat{" "}
                                                        {Math.abs(
                                                            loan.daysToDue,
                                                        )}{" "}
                                                        hari
                                                    </Badge>
                                                )}
                                                <Badge
                                                    tone={
                                                        TONE[loan.status] ??
                                                        "neutral"
                                                    }
                                                >
                                                    {loan.statusLabel}
                                                </Badge>
                                            </div>
                                        </div>

                                        <p className="mt-2 text-xs text-ink-soft">
                                            {loan.purpose}
                                        </p>

                                        <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-ink-muted">
                                            <span>
                                                Harus kembali {loan.dueDate}
                                            </span>
                                            {loan.handedOverAt && (
                                                <span>
                                                    Diterima {loan.handedOverAt}
                                                </span>
                                            )}
                                            {loan.returnedAt && (
                                                <span>
                                                    Dikembalikan{" "}
                                                    {loan.returnedAt}
                                                </span>
                                            )}
                                        </div>

                                        {(loan.decisionNote ||
                                            loan.returnNote) && (
                                            <p className="mt-2 border-l-2 border-hairline pl-2.5 text-[11px] text-ink-muted">
                                                {loan.decisionNote ??
                                                    loan.returnNote}
                                            </p>
                                        )}

                                        {loan.status === "requested" && (
                                            <div className="mt-3 border-t border-hairline pt-3">
                                                <Button
                                                    size="sm"
                                                    variant="danger"
                                                    onClick={() => {
                                                        if (
                                                            confirm(
                                                                "Batalkan pengajuan ini?",
                                                            )
                                                        ) {
                                                            router.delete(
                                                                `/inventaris-saya/${loan.id}`,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                >
                                                    Batalkan pengajuan
                                                </Button>
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>

                <div className="space-y-5">
                    <Card
                        title="Ajukan peminjaman"
                        subtitle="Pengajuan diteruskan ke HR untuk disetujui"
                    >
                        <div className="space-y-3">
                            <Field
                                label="Aset"
                                required
                                error={form.errors.inventory_item_id}
                            >
                                <Select
                                    value={form.data.inventory_item_id}
                                    onChange={(event) =>
                                        form.setData(
                                            "inventory_item_id",
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">Pilih aset…</option>
                                    {items.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.label} ({item.available}{" "}
                                            tersedia)
                                        </option>
                                    ))}
                                </Select>
                            </Field>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field
                                    label="Jumlah"
                                    required
                                    error={form.errors.quantity}
                                    hint={
                                        selected
                                            ? `${selected.available} unit tersedia`
                                            : undefined
                                    }
                                >
                                    <Input
                                        type="number"
                                        min={1}
                                        value={form.data.quantity}
                                        onChange={(event) =>
                                            form.setData(
                                                "quantity",
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Rencana kembali"
                                    required
                                    error={form.errors.due_date}
                                >
                                    <Input
                                        type="date"
                                        value={form.data.due_date}
                                        onChange={(event) =>
                                            form.setData(
                                                "due_date",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>

                            <Field
                                label="Keperluan"
                                required
                                error={form.errors.purpose}
                            >
                                <Textarea
                                    rows={3}
                                    value={form.data.purpose}
                                    onChange={(event) =>
                                        form.setData(
                                            "purpose",
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Contoh: kunjungan klien di Makassar, 3 hari."
                                />
                            </Field>

                            <Button
                                className="w-full"
                                disabled={form.processing}
                                onClick={() =>
                                    form.post("/inventaris-saya", {
                                        preserveScroll: true,
                                        onSuccess: () => form.reset(),
                                    })
                                }
                            >
                                Kirim pengajuan
                            </Button>
                        </div>
                    </Card>

                    <Card title="Cara kerja">
                        <ol className="space-y-2 text-xs text-ink-soft">
                            <li>
                                <span className="font-medium text-ink">
                                    1. Ajukan
                                </span>{" "}
                                — pilih aset, jumlah, dan rencana tanggal
                                kembali.
                            </li>
                            <li>
                                <span className="font-medium text-ink">
                                    2. Disetujui HR
                                </span>{" "}
                                — unit dikunci untuk Anda, belum berpindah
                                tangan.
                            </li>
                            <li>
                                <span className="font-medium text-ink">
                                    3. Serah terima
                                </span>{" "}
                                — HR mencatat kondisi barang saat diserahkan.
                            </li>
                            <li>
                                <span className="font-medium text-ink">
                                    4. Kembalikan
                                </span>{" "}
                                — sebelum jatuh tempo. Pinjaman yang belum
                                selesai menahan proses clearance saat Anda
                                keluar.
                            </li>
                        </ol>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
