import { Head, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import AppLayout from "@/Layouts/AppLayout";
import Card from "@/Components/Card";
import ExportMenu from "@/Components/ExportMenu";
import StatTile from "@/Components/StatTile";
import {
    Badge,
    Button,
    EmptyState,
    Field,
    Input,
    Pagination,
    Select,
    Textarea,
    type Paginated,
} from "@/Components/ui";
import { IconAlert, IconBox, IconCheck, IconClock } from "@/Components/Icons";
import { angka, rupiah } from "@/lib/format";

type Item = {
    id: number;
    code: string;
    name: string;
    category: string;
    brand: string | null;
    serialNumber: string | null;
    quantity: number;
    available: number;
    condition: string;
    status: string;
    location: string | null;
    purchasePrice: number | null;
    purchaseDate: string | null;
    notes: string | null;
};

type Loan = {
    id: number;
    itemId: number;
    item: string | null;
    itemCode: string | null;
    employee: string | null;
    nik: string | null;
    department: string | null;
    quantity: number;
    status: string;
    statusLabel: string;
    purpose: string;
    dueDate: string | null;
    daysToDue: number;
    isOverdue: boolean;
    handedOverAt: string | null;
    returnedAt: string | null;
    conditionOut: string | null;
    conditionIn: string | null;
    decisionNote: string | null;
    returnNote: string | null;
    /** Status tujuan yang diizinkan service — sumber tombol aksi. */
    actions: string[];
};

type Props = {
    items: Item[];
    loans: Paginated<Loan>;
    filters: {
        search: string | null;
        category: string | null;
        item_status: string | null;
        loan_status: string | null;
    };
    options: {
        categories: string[];
        conditions: string[];
        conditionLabels: Record<string, string>;
        itemStatuses: string[];
        itemStatusLabels: Record<string, string>;
        loanStatusLabels: Record<string, string>;
        employees: { id: number; label: string }[];
    };
    stats: {
        totalItems: number;
        totalUnits: number;
        borrowed: number;
        pending: number;
        overdue: number;
    };
};

/** Transisi status yang dikirim service → aksi yang dipahami controller. */
const ACTION_OF: Record<string, { key: string; label: string; needsCondition: boolean }> = {
    approved: { key: "approve", label: "Setujui", needsCondition: false },
    rejected: { key: "reject", label: "Tolak", needsCondition: false },
    borrowed: { key: "hand_over", label: "Serahkan barang", needsCondition: true },
    returned: { key: "return", label: "Catat pengembalian", needsCondition: true },
    lost: { key: "lost", label: "Tandai hilang", needsCondition: false },
};

const LOAN_TONE: Record<string, "neutral" | "brand" | "good" | "warning" | "critical"> = {
    requested: "warning",
    approved: "brand",
    borrowed: "brand",
    returned: "good",
    rejected: "neutral",
    lost: "critical",
};

const emptyItem = {
    code: "",
    name: "",
    category: "elektronik",
    brand: "",
    serial_number: "",
    quantity: 1,
    condition: "good",
    status: "active",
    location: "",
    purchase_price: "",
    purchase_date: "",
    notes: "",
};

export default function InventoryIndex({
    items,
    loans,
    filters,
    options,
    stats,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [panel, setPanel] = useState<"item" | "loan">("item");
    const [editing, setEditing] = useState<Item | null>(null);
    const [acting, setActing] = useState<{ loan: number; target: string } | null>(
        null,
    );

    const itemForm = useForm({ ...emptyItem });
    const loanForm = useForm({
        employee_id: "",
        inventory_item_id: "",
        quantity: 1,
        purpose: "",
        due_date: "",
    });
    const actionForm = useForm({ action: "", note: "", condition: "good" });

    // Penolakan transisi & stok kurang dilaporkan service di bawah kunci
    // "status" yang tidak ada di data form, jadi dibaca lewat peta longgar.
    const actionErrors = actionForm.errors as Record<string, string | undefined>;

    function applyFilter(patch: Record<string, string | null>) {
        router.get(
            "/inventaris",
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    }

    function startEdit(item: Item) {
        setPanel("item");
        setEditing(item);
        itemForm.setData({
            code: item.code,
            name: item.name,
            category: item.category,
            brand: item.brand ?? "",
            serial_number: item.serialNumber ?? "",
            quantity: item.quantity,
            condition: item.condition,
            status: item.status,
            location: item.location ?? "",
            purchase_price:
                item.purchasePrice !== null ? String(item.purchasePrice) : "",
            purchase_date: item.purchaseDate ?? "",
            notes: item.notes ?? "",
        });
    }

    function submitItem() {
        if (editing) {
            itemForm.patch(`/inventaris/aset/${editing.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setEditing(null);
                    itemForm.setData({ ...emptyItem });
                },
            });
            return;
        }

        itemForm.post("/inventaris/aset", {
            preserveScroll: true,
            onSuccess: () => itemForm.setData({ ...emptyItem }),
        });
    }

    function submitAction(loanId: number, target: string) {
        const action = ACTION_OF[target];
        if (!action) return;

        actionForm.transform((data) => ({ ...data, action: action.key }));
        actionForm.patch(`/inventaris/pinjaman/${loanId}`, {
            preserveScroll: true,
            onSuccess: () => {
                setActing(null);
                actionForm.reset();
            },
        });
    }

    return (
        <AppLayout
            title="Inventaris & Peminjaman"
            subtitle="Katalog aset perusahaan beserta siklus pinjam–kembali"
            actions={
                <div className="flex items-center gap-2">
                    <ExportMenu
                        targets={[
                            {
                                label: "Rekap peminjaman",
                                url: "/export/inventaris",
                            },
                        ]}
                        params={filters}
                    />
                    <Button
                        variant="secondary"
                        onClick={() => {
                            setEditing(null);
                            itemForm.setData({ ...emptyItem });
                            setPanel("item");
                        }}
                    >
                        Tambah aset
                    </Button>
                    <Button onClick={() => setPanel("loan")}>
                        Catat pinjaman
                    </Button>
                </div>
            }
        >
            <Head title="Inventaris & Peminjaman" />

            <div className="space-y-5">
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatTile
                        label="Jenis aset"
                        value={angka(stats.totalItems)}
                        caption={`${angka(stats.totalUnits)} unit tercatat`}
                        icon={<IconBox className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Sedang dipinjam"
                        value={angka(stats.borrowed)}
                        caption="unit di tangan pegawai"
                        icon={<IconCheck className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Menunggu persetujuan"
                        value={angka(stats.pending)}
                        caption="pengajuan belum diputuskan"
                        icon={<IconClock className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Lewat jatuh tempo"
                        value={angka(stats.overdue)}
                        caption="perlu ditagih pengembaliannya"
                        icon={<IconAlert className="h-4 w-4" />}
                        upIsGood={false}
                    />
                </section>

                <div className="grid gap-5 xl:grid-cols-3">
                    <div className="space-y-5 xl:col-span-2">
                        <Card
                            title="Peminjaman"
                            subtitle={`${loans.total} catatan sesuai filter`}
                        >
                            <div className="mb-4 grid gap-2 sm:grid-cols-2">
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
                                        placeholder="Cari peminjam / aset…"
                                    />
                                </form>

                                <Select
                                    value={filters.loan_status ?? ""}
                                    onChange={(event) =>
                                        applyFilter({
                                            loan_status:
                                                event.target.value || null,
                                        })
                                    }
                                >
                                    <option value="">Semua status</option>
                                    {Object.entries(
                                        options.loanStatusLabels,
                                    ).map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ))}
                                    <option value="overdue">
                                        Lewat jatuh tempo
                                    </option>
                                </Select>
                            </div>

                            {loans.data.length === 0 ? (
                                <EmptyState message="Belum ada peminjaman yang cocok." />
                            ) : (
                                <ul className="space-y-3">
                                    {loans.data.map((loan) => (
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
                                                    <p className="mt-0.5 text-xs text-ink-muted">
                                                        {loan.itemCode} ·{" "}
                                                        {loan.employee} ·{" "}
                                                        {loan.nik}
                                                        {loan.department
                                                            ? ` · ${loan.department}`
                                                            : ""}
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
                                                            LOAN_TONE[
                                                                loan.status
                                                            ] ?? "neutral"
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
                                                    Jatuh tempo {loan.dueDate}
                                                </span>
                                                {loan.handedOverAt && (
                                                    <span>
                                                        Diserahkan{" "}
                                                        {loan.handedOverAt}
                                                    </span>
                                                )}
                                                {loan.returnedAt && (
                                                    <span>
                                                        Dikembalikan{" "}
                                                        {loan.returnedAt}
                                                    </span>
                                                )}
                                                {loan.conditionIn && (
                                                    <span>
                                                        Kondisi kembali:{" "}
                                                        {options
                                                            .conditionLabels[
                                                            loan.conditionIn
                                                        ] ?? loan.conditionIn}
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

                                            {loan.actions.length > 0 && (
                                                <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-hairline pt-3">
                                                    {loan.actions.map(
                                                        (target) => (
                                                            <Button
                                                                key={target}
                                                                size="sm"
                                                                variant={
                                                                    target ===
                                                                        "rejected" ||
                                                                    target ===
                                                                        "lost"
                                                                        ? "danger"
                                                                        : target ===
                                                                            "approved"
                                                                          ? "primary"
                                                                          : "secondary"
                                                                }
                                                                onClick={() => {
                                                                    actionForm.reset();
                                                                    setActing(
                                                                        acting?.loan ===
                                                                            loan.id &&
                                                                            acting.target ===
                                                                                target
                                                                            ? null
                                                                            : {
                                                                                  loan: loan.id,
                                                                                  target,
                                                                              },
                                                                    );
                                                                }}
                                                            >
                                                                {ACTION_OF[
                                                                    target
                                                                ]?.label ??
                                                                    target}
                                                            </Button>
                                                        ),
                                                    )}
                                                </div>
                                            )}

                                            {acting?.loan === loan.id && (
                                                <div className="mt-3 space-y-3 rounded-xl bg-surface-soft p-3">
                                                    {ACTION_OF[acting.target]
                                                        ?.needsCondition && (
                                                        <Field label="Kondisi barang">
                                                            <Select
                                                                value={
                                                                    actionForm
                                                                        .data
                                                                        .condition
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    actionForm.setData(
                                                                        "condition",
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                            >
                                                                {options.conditions.map(
                                                                    (
                                                                        condition,
                                                                    ) => (
                                                                        <option
                                                                            key={
                                                                                condition
                                                                            }
                                                                            value={
                                                                                condition
                                                                            }
                                                                        >
                                                                            {options
                                                                                .conditionLabels[
                                                                                condition
                                                                            ] ??
                                                                                condition}
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </Select>
                                                        </Field>
                                                    )}

                                                    <Field
                                                        label="Catatan"
                                                        error={
                                                            actionErrors.note ??
                                                            actionErrors.status
                                                        }
                                                    >
                                                        <Textarea
                                                            rows={2}
                                                            value={
                                                                actionForm.data
                                                                    .note
                                                            }
                                                            onChange={(event) =>
                                                                actionForm.setData(
                                                                    "note",
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Opsional"
                                                        />
                                                    </Field>

                                                    <div className="flex gap-2">
                                                        <Button
                                                            size="sm"
                                                            disabled={
                                                                actionForm.processing
                                                            }
                                                            onClick={() =>
                                                                submitAction(
                                                                    loan.id,
                                                                    acting.target,
                                                                )
                                                            }
                                                        >
                                                            Konfirmasi{" "}
                                                            {ACTION_OF[
                                                                acting.target
                                                            ]?.label.toLowerCase()}
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                setActing(null)
                                                            }
                                                        >
                                                            Batal
                                                        </Button>
                                                    </div>
                                                </div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}

                            <Pagination page={loans} />
                        </Card>

                        <Card
                            title="Katalog aset"
                            subtitle={`${items.length} jenis aset`}
                        >
                            <div className="mb-4 grid gap-2 sm:grid-cols-2">
                                <Select
                                    value={filters.category ?? ""}
                                    onChange={(event) =>
                                        applyFilter({
                                            category: event.target.value || null,
                                        })
                                    }
                                >
                                    <option value="">Semua kategori</option>
                                    {options.categories.map((category) => (
                                        <option key={category} value={category}>
                                            {category}
                                        </option>
                                    ))}
                                </Select>
                                <Select
                                    value={filters.item_status ?? ""}
                                    onChange={(event) =>
                                        applyFilter({
                                            item_status:
                                                event.target.value || null,
                                        })
                                    }
                                >
                                    <option value="">Semua status aset</option>
                                    {options.itemStatuses.map((status) => (
                                        <option key={status} value={status}>
                                            {options.itemStatusLabels[status] ??
                                                status}
                                        </option>
                                    ))}
                                </Select>
                            </div>

                            {items.length === 0 ? (
                                <EmptyState message="Katalog aset masih kosong." />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[640px] text-left text-xs">
                                        <thead className="text-ink-muted">
                                            <tr className="border-b border-hairline">
                                                <th className="pb-2 font-medium">
                                                    Aset
                                                </th>
                                                <th className="pb-2 font-medium">
                                                    Kategori
                                                </th>
                                                <th className="pb-2 text-right font-medium">
                                                    Tersedia
                                                </th>
                                                <th className="pb-2 font-medium">
                                                    Kondisi
                                                </th>
                                                <th className="pb-2 font-medium">
                                                    Status
                                                </th>
                                                <th className="pb-2" />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {items.map((item) => (
                                                <tr
                                                    key={item.id}
                                                    className="border-b border-hairline last:border-0"
                                                >
                                                    <td className="py-2.5">
                                                        <p className="font-medium text-ink">
                                                            {item.name}
                                                        </p>
                                                        <p className="tabular text-[11px] text-ink-muted">
                                                            {item.code}
                                                            {item.serialNumber
                                                                ? ` · SN ${item.serialNumber}`
                                                                : ""}
                                                        </p>
                                                    </td>
                                                    <td className="py-2.5 text-ink-soft">
                                                        {item.category}
                                                    </td>
                                                    <td className="tabular py-2.5 text-right text-ink-soft">
                                                        {item.available} /{" "}
                                                        {item.quantity}
                                                    </td>
                                                    <td className="py-2.5">
                                                        <Badge
                                                            tone={
                                                                item.condition ===
                                                                "good"
                                                                    ? "good"
                                                                    : item.condition ===
                                                                        "minor"
                                                                      ? "warning"
                                                                      : "critical"
                                                            }
                                                        >
                                                            {options
                                                                .conditionLabels[
                                                                item.condition
                                                            ] ?? item.condition}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-2.5">
                                                        <Badge
                                                            tone={
                                                                item.status ===
                                                                "active"
                                                                    ? "brand"
                                                                    : "neutral"
                                                            }
                                                        >
                                                            {options
                                                                .itemStatusLabels[
                                                                item.status
                                                            ] ?? item.status}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-2.5 text-right whitespace-nowrap">
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                startEdit(item)
                                                            }
                                                        >
                                                            Ubah
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-[#d03b3b]"
                                                            onClick={() => {
                                                                if (
                                                                    confirm(
                                                                        `Hapus ${item.name} dari katalog?`,
                                                                    )
                                                                ) {
                                                                    router.delete(
                                                                        `/inventaris/aset/${item.id}`,
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    );
                                                                }
                                                            }}
                                                        >
                                                            Hapus
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </Card>
                    </div>

                    <div className="space-y-5">
                        {panel === "item" ? (
                            <Card
                                title={editing ? "Ubah aset" : "Aset baru"}
                                subtitle={
                                    editing
                                        ? `${editing.code} · ${editing.quantity - editing.available} unit sedang dipinjam`
                                        : "Tambahkan barang ke katalog peminjaman"
                                }
                            >
                                <div className="space-y-3">
                                    <Field
                                        label="Kode aset"
                                        required
                                        error={itemForm.errors.code}
                                    >
                                        <Input
                                            value={itemForm.data.code}
                                            onChange={(event) =>
                                                itemForm.setData(
                                                    "code",
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="AST-LP-001"
                                        />
                                    </Field>

                                    <Field
                                        label="Nama aset"
                                        required
                                        error={itemForm.errors.name}
                                    >
                                        <Input
                                            value={itemForm.data.name}
                                            onChange={(event) =>
                                                itemForm.setData(
                                                    "name",
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Laptop Lenovo ThinkPad"
                                        />
                                    </Field>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Field
                                            label="Kategori"
                                            error={itemForm.errors.category}
                                        >
                                            <Select
                                                value={itemForm.data.category}
                                                onChange={(event) =>
                                                    itemForm.setData(
                                                        "category",
                                                        event.target.value,
                                                    )
                                                }
                                            >
                                                {options.categories.map(
                                                    (category) => (
                                                        <option
                                                            key={category}
                                                            value={category}
                                                        >
                                                            {category}
                                                        </option>
                                                    ),
                                                )}
                                            </Select>
                                        </Field>

                                        <Field
                                            label="Jumlah unit"
                                            required
                                            error={itemForm.errors.quantity}
                                        >
                                            <Input
                                                type="number"
                                                min={0}
                                                value={itemForm.data.quantity}
                                                onChange={(event) =>
                                                    itemForm.setData(
                                                        "quantity",
                                                        Number(
                                                            event.target.value,
                                                        ),
                                                    )
                                                }
                                            />
                                        </Field>
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Field label="Merek">
                                            <Input
                                                value={itemForm.data.brand}
                                                onChange={(event) =>
                                                    itemForm.setData(
                                                        "brand",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field label="Nomor seri">
                                            <Input
                                                value={
                                                    itemForm.data.serial_number
                                                }
                                                onChange={(event) =>
                                                    itemForm.setData(
                                                        "serial_number",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Field label="Kondisi">
                                            <Select
                                                value={itemForm.data.condition}
                                                onChange={(event) =>
                                                    itemForm.setData(
                                                        "condition",
                                                        event.target.value,
                                                    )
                                                }
                                            >
                                                {options.conditions.map(
                                                    (condition) => (
                                                        <option
                                                            key={condition}
                                                            value={condition}
                                                        >
                                                            {options
                                                                .conditionLabels[
                                                                condition
                                                            ] ?? condition}
                                                        </option>
                                                    ),
                                                )}
                                            </Select>
                                        </Field>
                                        <Field label="Status">
                                            <Select
                                                value={itemForm.data.status}
                                                onChange={(event) =>
                                                    itemForm.setData(
                                                        "status",
                                                        event.target.value,
                                                    )
                                                }
                                            >
                                                {options.itemStatuses.map(
                                                    (status) => (
                                                        <option
                                                            key={status}
                                                            value={status}
                                                        >
                                                            {options
                                                                .itemStatusLabels[
                                                                status
                                                            ] ?? status}
                                                        </option>
                                                    ),
                                                )}
                                            </Select>
                                        </Field>
                                    </div>

                                    <Field label="Lokasi penyimpanan">
                                        <Input
                                            value={itemForm.data.location}
                                            onChange={(event) =>
                                                itemForm.setData(
                                                    "location",
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Gudang lantai 2"
                                        />
                                    </Field>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Field label="Harga perolehan">
                                            <Input
                                                type="number"
                                                min={0}
                                                value={
                                                    itemForm.data.purchase_price
                                                }
                                                onChange={(event) =>
                                                    itemForm.setData(
                                                        "purchase_price",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field label="Tanggal perolehan">
                                            <Input
                                                type="date"
                                                value={
                                                    itemForm.data.purchase_date
                                                }
                                                onChange={(event) =>
                                                    itemForm.setData(
                                                        "purchase_date",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    </div>

                                    <Field label="Catatan">
                                        <Textarea
                                            rows={2}
                                            value={itemForm.data.notes}
                                            onChange={(event) =>
                                                itemForm.setData(
                                                    "notes",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>

                                    <div className="flex gap-2 border-t border-hairline pt-3">
                                        <Button
                                            onClick={submitItem}
                                            disabled={itemForm.processing}
                                        >
                                            {editing
                                                ? "Simpan perubahan"
                                                : "Tambah ke katalog"}
                                        </Button>
                                        {editing && (
                                            <Button
                                                variant="ghost"
                                                onClick={() => {
                                                    setEditing(null);
                                                    itemForm.setData({
                                                        ...emptyItem,
                                                    });
                                                }}
                                            >
                                                Batal
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </Card>
                        ) : (
                            <Card
                                title="Catat pinjaman"
                                subtitle="Dicatat HR dan langsung berstatus disetujui"
                            >
                                <div className="space-y-3">
                                    <Field
                                        label="Peminjam"
                                        required
                                        error={loanForm.errors.employee_id}
                                    >
                                        <Select
                                            value={loanForm.data.employee_id}
                                            onChange={(event) =>
                                                loanForm.setData(
                                                    "employee_id",
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                Pilih pegawai…
                                            </option>
                                            {options.employees.map(
                                                (employee) => (
                                                    <option
                                                        key={employee.id}
                                                        value={employee.id}
                                                    >
                                                        {employee.label}
                                                    </option>
                                                ),
                                            )}
                                        </Select>
                                    </Field>

                                    <Field
                                        label="Aset"
                                        required
                                        error={
                                            loanForm.errors.inventory_item_id
                                        }
                                    >
                                        <Select
                                            value={
                                                loanForm.data.inventory_item_id
                                            }
                                            onChange={(event) =>
                                                loanForm.setData(
                                                    "inventory_item_id",
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="">
                                                Pilih aset…
                                            </option>
                                            {items
                                                .filter(
                                                    (item) =>
                                                        item.status ===
                                                        "active",
                                                )
                                                .map((item) => (
                                                    <option
                                                        key={item.id}
                                                        value={item.id}
                                                    >
                                                        {item.code} ·{" "}
                                                        {item.name} (
                                                        {item.available}{" "}
                                                        tersedia)
                                                    </option>
                                                ))}
                                        </Select>
                                    </Field>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Field
                                            label="Jumlah"
                                            required
                                            error={loanForm.errors.quantity}
                                        >
                                            <Input
                                                type="number"
                                                min={1}
                                                value={loanForm.data.quantity}
                                                onChange={(event) =>
                                                    loanForm.setData(
                                                        "quantity",
                                                        Number(
                                                            event.target.value,
                                                        ),
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label="Jatuh tempo"
                                            required
                                            error={loanForm.errors.due_date}
                                        >
                                            <Input
                                                type="date"
                                                value={loanForm.data.due_date}
                                                onChange={(event) =>
                                                    loanForm.setData(
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
                                        error={loanForm.errors.purpose}
                                    >
                                        <Textarea
                                            rows={3}
                                            value={loanForm.data.purpose}
                                            onChange={(event) =>
                                                loanForm.setData(
                                                    "purpose",
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Dinas luar kota, presentasi klien…"
                                        />
                                    </Field>

                                    <div className="flex gap-2 border-t border-hairline pt-3">
                                        <Button
                                            disabled={loanForm.processing}
                                            onClick={() =>
                                                loanForm.post(
                                                    "/inventaris/pinjaman",
                                                    {
                                                        preserveScroll: true,
                                                        onSuccess: () =>
                                                            loanForm.reset(),
                                                    },
                                                )
                                            }
                                        >
                                            Simpan pinjaman
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            onClick={() => setPanel("item")}
                                        >
                                            Tutup
                                        </Button>
                                    </div>
                                </div>
                            </Card>
                        )}

                        <Card
                            title="Nilai aset"
                            subtitle="Total harga perolehan yang tercatat"
                        >
                            <p className="tabular text-2xl font-semibold text-ink">
                                {rupiah(
                                    items.reduce(
                                        (total, item) =>
                                            total +
                                            (item.purchasePrice ?? 0) *
                                                item.quantity,
                                        0,
                                    ),
                                )}
                            </p>
                            <p className="mt-1 text-[11px] text-ink-muted">
                                Aset tanpa harga perolehan tidak ikut dihitung.
                            </p>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
