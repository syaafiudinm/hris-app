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
    Select,
} from "@/Components/ui";
import { IconFunnel, IconUsers, IconWallet } from "@/Components/Icons";
import { angka, rupiah, rupiahCompact } from "@/lib/format";

type Product = {
    id: number;
    code: string;
    name: string;
    incentive: number;
    isActive: boolean;
    soldThisPeriod: number;
};

type Row = {
    id: number;
    name: string;
    nik: string;
    department: string | null;
    quantities: Record<number, number>;
    totalUnits: number;
    incentive: number;
    bonusPercentage: number;
    bonusAmount: number;
};

type Props = {
    rows: Row[];
    products: Product[];
    filters: { year: number; month: number; search: string | null };
    options: { years: number[] };
    summary: {
        periodLabel: string;
        totalUnits: number;
        totalIncentive: number;
        mitraCount: number;
    };
};

const MONTHS = [
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
];

export default function SalesIndex({
    rows,
    products,
    filters,
    options,
    summary,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [editingProduct, setEditingProduct] = useState<Product | null>(null);

    const activeProducts = products.filter((product) => product.isActive);

    function applyFilter(patch: Record<string, string | number | null>) {
        router.get(
            "/penjualan",
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    }

    return (
        <AppLayout
            title="Penjualan Mitra"
            subtitle={`Insentif & bonus periode ${summary.periodLabel}`}
            actions={
                <ExportMenu
                    targets={[
                        { label: "Rekap penjualan & insentif", url: "/export/penjualan" },
                    ]}
                    params={{ year: filters.year, month: filters.month }}
                />
            }
        >
            <Head title="Penjualan Mitra" />

            <div className="space-y-5">
                <section className="grid gap-4 sm:grid-cols-3">
                    <StatTile
                        label="Mitra skema penjualan"
                        value={angka(summary.mitraCount)}
                        caption="dibayar per unit terjual"
                        icon={<IconUsers className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Unit terjual"
                        value={angka(summary.totalUnits)}
                        caption={summary.periodLabel}
                        icon={<IconFunnel className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Total insentif"
                        value={rupiahCompact(summary.totalIncentive)}
                        caption="belum termasuk bonus & uang makan"
                        icon={<IconWallet className="h-4 w-4" />}
                    />
                </section>

                <div className="grid gap-5 xl:grid-cols-3">
                    <div className="xl:col-span-2">
                        <Card
                            title="Input unit terjual"
                            subtitle="Simpan per mitra, lalu jalankan ulang payroll periode ini"
                        >
                            <div className="mb-4 grid gap-2 sm:grid-cols-3">
                                <Select
                                    value={filters.month}
                                    onChange={(event) =>
                                        applyFilter({
                                            month: Number(event.target.value),
                                        })
                                    }
                                >
                                    {MONTHS.map((label, index) => (
                                        <option key={label} value={index + 1}>
                                            {label}
                                        </option>
                                    ))}
                                </Select>
                                <Select
                                    value={filters.year}
                                    onChange={(event) =>
                                        applyFilter({
                                            year: Number(event.target.value),
                                        })
                                    }
                                >
                                    {options.years.map((year) => (
                                        <option key={year} value={year}>
                                            {year}
                                        </option>
                                    ))}
                                </Select>
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
                                        placeholder="Cari mitra…"
                                    />
                                </form>
                            </div>

                            {rows.length === 0 ? (
                                <EmptyState message="Belum ada mitra dengan skema penjualan. Atur lewat menu Skema Mitra." />
                            ) : (
                                <ul className="space-y-3">
                                    {rows.map((row) => (
                                        <SalesRow
                                            key={`${row.id}-${filters.year}-${filters.month}`}
                                            row={row}
                                            products={activeProducts}
                                            period={{
                                                year: filters.year,
                                                month: filters.month,
                                            }}
                                        />
                                    ))}
                                </ul>
                            )}
                        </Card>
                    </div>

                    <div className="space-y-5">
                        <Card
                            title="Katalog produk"
                            subtitle="Insentif per unit terjual"
                            action={
                                <Button
                                    size="sm"
                                    onClick={() =>
                                        setEditingProduct({
                                            id: 0,
                                            code: "",
                                            name: "",
                                            incentive: 0,
                                            isActive: true,
                                            soldThisPeriod: 0,
                                        })
                                    }
                                >
                                    Tambah
                                </Button>
                            }
                        >
                            <ul className="space-y-2.5">
                                {products.map((product) => (
                                    <li
                                        key={product.id}
                                        className="flex items-start justify-between gap-3 border-b border-hairline pb-2.5 last:border-0 last:pb-0"
                                    >
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium text-ink">
                                                {product.name}
                                                {!product.isActive && (
                                                    <span className="ml-2 text-[11px] font-normal text-ink-muted">
                                                        nonaktif
                                                    </span>
                                                )}
                                            </p>
                                            <p className="tabular text-xs text-ink-soft">
                                                {rupiah(product.incentive)} / unit
                                            </p>
                                            <p className="text-[11px] text-ink-muted">
                                                {angka(product.soldThisPeriod)}{" "}
                                                unit periode ini
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setEditingProduct(product)
                                            }
                                            className="text-[11px] font-medium text-brand-600 transition hover:text-brand-700"
                                        >
                                            Ubah
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </Card>

                        {editingProduct ? (
                            <ProductForm
                                key={editingProduct.id}
                                product={editingProduct}
                                onClose={() => setEditingProduct(null)}
                            />
                        ) : (
                            <Card
                                title="Cara perhitungan"
                                subtitle="Semua komponen mengikuti hari hadir"
                            >
                                <ol className="space-y-2.5 text-xs text-ink-soft">
                                    <li>
                                        <span className="font-medium text-ink">
                                            Uang makan &amp; transport
                                        </span>{" "}
                                        — nilai bulanan dibagi hari kerja, dikali
                                        hari hadir.
                                    </li>
                                    <li>
                                        <span className="font-medium text-ink">
                                            Insentif
                                        </span>{" "}
                                        — unit terjual dikali insentif tiap
                                        produk. Tidak diprorata.
                                    </li>
                                    <li>
                                        <span className="font-medium text-ink">
                                            Bonus tier
                                        </span>{" "}
                                        — persentase UMP menurut total unit,
                                        lalu diprorata hari hadir.
                                    </li>
                                    <li>
                                        <span className="font-medium text-ink">
                                            Pajak
                                        </span>{" "}
                                        — hanya insentif: 50% × 2,5%.
                                    </li>
                                </ol>

                                <p className="mt-4 border-t border-hairline pt-3 text-[11px] text-ink-muted">
                                    Nilai uang makan, hari kerja, UMP, dan tier
                                    bonus diatur per mitra pada menu Skema Mitra.
                                </p>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function SalesRow({
    row,
    products,
    period,
}: {
    row: Row;
    products: Product[];
    period: { year: number; month: number };
}) {
    const { data, setData, post, processing } = useForm({
        period_year: period.year,
        period_month: period.month,
        quantities: row.quantities as Record<string, number>,
    });

    // Pratinjau langsung supaya HR melihat dampak input sebelum menyimpan.
    const totalUnits = Object.values(data.quantities).reduce(
        (sum, value) => sum + (Number(value) || 0),
        0,
    );
    const incentive = products.reduce(
        (sum, product) =>
            sum + (Number(data.quantities[product.id]) || 0) * product.incentive,
        0,
    );
    const changed = totalUnits !== row.totalUnits || incentive !== row.incentive;

    return (
        <li className="rounded-xl border border-hairline p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="font-medium text-ink">{row.name}</p>
                    <p className="text-xs text-ink-muted">
                        {row.nik}
                        {row.department ? ` · ${row.department}` : ""}
                    </p>
                </div>
                {row.bonusPercentage > 0 && (
                    <Badge tone="good">
                        Bonus {row.bonusPercentage}% UMP
                    </Badge>
                )}
            </div>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    post(`/penjualan/${row.id}`, { preserveScroll: true });
                }}
                className="mt-3"
            >
                <div className="grid gap-3 sm:grid-cols-3">
                    {products.map((product) => (
                        <label key={product.id} className="block">
                            <span className="mb-1 block text-[11px] font-medium text-ink-soft">
                                {product.name}
                                <span className="ml-1 font-normal text-ink-muted">
                                    {rupiahCompact(product.incentive)}
                                </span>
                            </span>
                            <Input
                                type="number"
                                min={0}
                                value={data.quantities[product.id] ?? 0}
                                onChange={(event) =>
                                    setData("quantities", {
                                        ...data.quantities,
                                        [product.id]: Number(event.target.value),
                                    })
                                }
                            />
                        </label>
                    ))}
                </div>

                <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-hairline pt-3 text-xs">
                    <span className="tabular text-ink-soft">
                        {angka(totalUnits)} unit
                    </span>
                    <span className="tabular text-ink">
                        Insentif {rupiah(incentive)}
                    </span>
                    {row.bonusAmount > 0 && (
                        <span className="tabular text-ink-soft">
                            Bonus penuh {rupiah(row.bonusAmount)}
                        </span>
                    )}
                    <Button
                        type="submit"
                        size="sm"
                        disabled={processing || !changed}
                        className="ml-auto"
                    >
                        {processing ? "Menyimpan…" : "Simpan"}
                    </Button>
                </div>
            </form>
        </li>
    );
}

function ProductForm({
    product,
    onClose,
}: {
    product: Product;
    onClose: () => void;
}) {
    const isNew = product.id === 0;

    const { data, setData, post, patch, processing, errors } = useForm({
        code: product.code,
        name: product.name,
        incentive_amount: product.incentive,
        is_active: product.isActive,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (isNew) {
            post("/penjualan-produk", {
                preserveScroll: true,
                onSuccess: onClose,
            });
        } else {
            patch(`/penjualan-produk/${product.id}`, {
                preserveScroll: true,
                onSuccess: onClose,
            });
        }
    }

    return (
        <Card title={isNew ? "Produk baru" : `Ubah ${product.name}`}>
            <form onSubmit={submit} className="space-y-4">
                <Field label="Kode" error={errors.code} required>
                    <Input
                        value={data.code}
                        onChange={(event) => setData("code", event.target.value)}
                        placeholder="ex2"
                    />
                </Field>

                <Field label="Nama" error={errors.name} required>
                    <Input
                        value={data.name}
                        onChange={(event) => setData("name", event.target.value)}
                        placeholder="EX2"
                    />
                </Field>

                <Field
                    label="Insentif per unit"
                    error={errors.incentive_amount}
                    required
                >
                    <Input
                        type="number"
                        min={0}
                        step={50000}
                        value={data.incentive_amount}
                        onChange={(event) =>
                            setData(
                                "incentive_amount",
                                Number(event.target.value),
                            )
                        }
                    />
                </Field>

                <label className="flex items-center gap-2 text-xs text-ink-soft">
                    <input
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(event) =>
                            setData("is_active", event.target.checked)
                        }
                        className="h-3.5 w-3.5 rounded border-hairline-strong text-brand-500 focus:ring-brand-100"
                    />
                    Aktif — tampil pada form input penjualan
                </label>

                <div className="flex flex-wrap items-center gap-2 pt-1">
                    <Button type="submit" disabled={processing}>
                        {processing ? "Menyimpan…" : "Simpan"}
                    </Button>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    {!isNew && product.soldThisPeriod === 0 && (
                        <Button
                            type="button"
                            variant="danger"
                            onClick={() => {
                                if (confirm(`Hapus produk ${product.name}?`)) {
                                    router.delete(
                                        `/penjualan-produk/${product.id}`,
                                        { preserveScroll: true, onSuccess: onClose },
                                    );
                                }
                            }}
                        >
                            Hapus
                        </Button>
                    )}
                </div>
            </form>
        </Card>
    );
}
