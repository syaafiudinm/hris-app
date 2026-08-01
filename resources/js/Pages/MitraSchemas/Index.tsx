import { Head, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import Card from "@/Components/Card";
import AppLayout from "@/Layouts/AppLayout";
import StatTile from "@/Components/StatTile";
import {
    Badge,
    Button,
    EmptyState,
    Field,
    Input,
    Pagination,
    Select,
    type Paginated,
} from "@/Components/ui";
import { IconAlert, IconWallet } from "@/Components/Icons";
import { angka, rupiah } from "@/lib/format";

type Schema = {
    id: number;
    schemaType: string;
    rate: number;
    unitLabel: string | null;
    taxScheme: string;
    taxPercentage: number;
    components: Record<string, unknown>;
};

type Mitra = {
    id: number;
    nik: string;
    name: string;
    department: string | null;
    status: string;
    schema: Schema | null;
};

type Props = {
    mitra: Paginated<Mitra>;
    filters: { search: string | null; unconfigured: boolean };
    options: { schemaTypes: string[]; taxSchemes: string[] };
    stats: { total: number; unconfigured: number };
};

const SCHEMA_LABELS: Record<string, string> = {
    fixed_project: "Fixed Project Fee",
    hourly: "Hourly Rate",
    daily: "Daily Rate",
    milestone: "Deliverable / Milestone",
    unit: "Unit / Output",
};

const SCHEMA_HINTS: Record<string, string> = {
    fixed_project: "Dibayar penuh satu kali per periode proyek.",
    hourly: "Kuantitas ditarik otomatis dari total jam kerja pada timesheet.",
    daily: "Kuantitas ditarik otomatis dari jumlah hari hadir.",
    milestone: "Pencairan bertahap; persentase diatur pada daftar milestone.",
    unit: "Kuantitas unit diisi manual oleh HR saat periode dijalankan.",
};

const TAX_LABELS: Record<string, string> = {
    pph21_berkesinambungan: "PPh 21 Bukan Pegawai (Berkesinambungan)",
    pph21_tidak_berkesinambungan: "PPh 21 Bukan Pegawai (Tidak Berkesinambungan)",
    pph23: "PPh 23",
    bebas_pajak: "Bebas Pajak",
};

const DEFAULT_UNIT_LABEL: Record<string, string> = {
    hourly: "jam",
    daily: "hari",
    unit: "unit",
    fixed_project: "proyek",
    milestone: "milestone",
};

export default function MitraSchemasIndex({
    mitra,
    filters,
    options,
    stats,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [editing, setEditing] = useState<Mitra | null>(null);

    function applyFilter(patch: Record<string, string | boolean | null>) {
        router.get(
            "/skema-mitra",
            { ...filters, ...patch },
            { preserveState: true, replace: true },
        );
    }

    return (
        <AppLayout
            title="Skema Pembayaran Mitra"
            subtitle="Builder skema kompensasi custom per individu mitra"
        >
            <Head title="Skema Mitra" />

            <div className="space-y-5">
                <section className="grid gap-4 sm:grid-cols-2">
                    <StatTile
                        label="Total mitra"
                        value={angka(stats.total)}
                        caption="seluruh entitas kategori mitra"
                        icon={<IconWallet className="h-4 w-4" />}
                    />
                    <StatTile
                        label="Belum punya skema"
                        value={angka(stats.unconfigured)}
                        caption="dilewati saat payroll dijalankan"
                        icon={<IconAlert className="h-4 w-4" />}
                    />
                </section>

                <div className="grid gap-5 xl:grid-cols-3">
                    <div className="xl:col-span-2">
                        <Card
                            title="Daftar mitra"
                            subtitle={`${mitra.total} mitra sesuai filter`}
                        >
                            <div className="mb-4 flex flex-wrap items-center gap-3">
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        applyFilter({ search: search || null });
                                    }}
                                    className="min-w-52 flex-1"
                                >
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Cari nama / NIK mitra…"
                                    />
                                </form>

                                <label className="flex items-center gap-2 text-xs text-ink-soft">
                                    <input
                                        type="checkbox"
                                        checked={filters.unconfigured}
                                        onChange={(event) =>
                                            applyFilter({
                                                unconfigured:
                                                    event.target.checked,
                                            })
                                        }
                                        className="h-3.5 w-3.5 rounded border-hairline-strong text-brand-500 focus:ring-brand-100"
                                    />
                                    Hanya yang belum diatur
                                </label>
                            </div>

                            {mitra.data.length === 0 ? (
                                <EmptyState message="Tidak ada mitra pada filter ini." />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[640px] text-sm">
                                        <thead>
                                            <tr className="border-b border-hairline text-left text-xs text-ink-muted">
                                                <th className="pb-2 font-medium">
                                                    Mitra
                                                </th>
                                                <th className="pb-2 font-medium">
                                                    Skema
                                                </th>
                                                <th className="pb-2 text-right font-medium">
                                                    Tarif
                                                </th>
                                                <th className="pb-2" />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {mitra.data.map((row) => (
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
                                                        {row.schema ? (
                                                            <Badge tone="brand">
                                                                {SCHEMA_LABELS[
                                                                    row.schema
                                                                        .schemaType
                                                                ] ??
                                                                    row.schema
                                                                        .schemaType}
                                                            </Badge>
                                                        ) : (
                                                            <Badge tone="warning">
                                                                belum diatur
                                                            </Badge>
                                                        )}
                                                    </td>
                                                    <td className="tabular py-2.5 text-right text-ink">
                                                        {row.schema
                                                            ? `${rupiah(row.schema.rate)}${
                                                                  row.schema
                                                                      .unitLabel
                                                                      ? ` / ${row.schema.unitLabel}`
                                                                      : ""
                                                              }`
                                                            : "—"}
                                                    </td>
                                                    <td className="py-2.5 text-right">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setEditing(row)
                                                            }
                                                            className="text-[11px] font-medium text-brand-600 transition hover:text-brand-700"
                                                        >
                                                            {row.schema
                                                                ? "Ubah"
                                                                : "Atur"}
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            <Pagination page={mitra} />
                        </Card>
                    </div>

                    {editing ? (
                        <SchemaBuilder
                            key={editing.id}
                            mitra={editing}
                            options={options}
                            onClose={() => setEditing(null)}
                        />
                    ) : (
                        <Card
                            title="Lima opsi skema"
                            subtitle="Pilih satu per mitra; kuantitas sebagian ditarik otomatis"
                        >
                            <ul className="space-y-3 text-xs">
                                {options.schemaTypes.map((type) => (
                                    <li key={type}>
                                        <p className="font-medium text-ink">
                                            {SCHEMA_LABELS[type] ?? type}
                                        </p>
                                        <p className="text-ink-soft">
                                            {SCHEMA_HINTS[type]}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

function SchemaBuilder({
    mitra,
    options,
    onClose,
}: {
    mitra: Mitra;
    options: { schemaTypes: string[]; taxSchemes: string[] };
    onClose: () => void;
}) {
    const existing = mitra.schema;

    const { data, setData, post, processing, errors } = useForm({
        schema_type: existing?.schemaType ?? "hourly",
        rate_per_unit: existing?.rate ?? 0,
        unit_label: existing?.unitLabel ?? "jam",
        tax_scheme: existing?.taxScheme ?? "pph21_tidak_berkesinambungan",
        custom_tax_percentage: existing?.taxPercentage ?? 2.5,
        transport_allowance:
            (existing?.components?.transport_allowance as number) ?? 0,
    });

    return (
        <Card
            title={existing ? "Ubah skema" : "Atur skema"}
            subtitle={mitra.name}
        >
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    post(`/skema-mitra/${mitra.id}`, {
                        preserveScroll: true,
                        onSuccess: onClose,
                    });
                }}
                className="space-y-4"
            >
                <Field
                    label="Tipe skema"
                    error={errors.schema_type}
                    required
                    hint={SCHEMA_HINTS[data.schema_type]}
                >
                    <Select
                        value={data.schema_type}
                        onChange={(event) => {
                            const type = event.target.value;
                            setData("schema_type", type);
                            setData(
                                "unit_label",
                                DEFAULT_UNIT_LABEL[type] ?? "unit",
                            );
                        }}
                    >
                        {options.schemaTypes.map((type) => (
                            <option key={type} value={type}>
                                {SCHEMA_LABELS[type] ?? type}
                            </option>
                        ))}
                    </Select>
                </Field>

                <Field label="Tarif" error={errors.rate_per_unit} required>
                    <Input
                        type="number"
                        min={0}
                        step={1000}
                        value={data.rate_per_unit}
                        onChange={(event) =>
                            setData("rate_per_unit", Number(event.target.value))
                        }
                    />
                </Field>

                <Field label="Satuan" error={errors.unit_label}>
                    <Input
                        value={data.unit_label}
                        onChange={(event) =>
                            setData("unit_label", event.target.value)
                        }
                        placeholder="jam / hari / artikel"
                    />
                </Field>

                <Field label="Skema pajak" error={errors.tax_scheme} required>
                    <Select
                        value={data.tax_scheme}
                        onChange={(event) => {
                            const scheme = event.target.value;
                            setData("tax_scheme", scheme);
                            setData(
                                "custom_tax_percentage",
                                scheme === "pph23"
                                    ? 2
                                    : scheme === "bebas_pajak"
                                      ? 0
                                      : 2.5,
                            );
                        }}
                    >
                        {options.taxSchemes.map((scheme) => (
                            <option key={scheme} value={scheme}>
                                {TAX_LABELS[scheme] ?? scheme}
                            </option>
                        ))}
                    </Select>
                </Field>

                <Field
                    label="Persentase pajak (%)"
                    error={errors.custom_tax_percentage}
                    required
                >
                    <Input
                        type="number"
                        min={0}
                        max={100}
                        step={0.5}
                        value={data.custom_tax_percentage}
                        onChange={(event) =>
                            setData(
                                "custom_tax_percentage",
                                Number(event.target.value),
                            )
                        }
                    />
                </Field>

                <Field
                    label="Tunjangan transport (opsional)"
                    error={errors.transport_allowance}
                    hint="Ditambahkan ke bruto setiap periode."
                >
                    <Input
                        type="number"
                        min={0}
                        step={50000}
                        value={data.transport_allowance}
                        onChange={(event) =>
                            setData(
                                "transport_allowance",
                                Number(event.target.value),
                            )
                        }
                    />
                </Field>

                <div className="flex flex-wrap items-center gap-2 pt-1">
                    <Button type="submit" disabled={processing}>
                        {processing ? "Menyimpan…" : "Simpan skema"}
                    </Button>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    {existing && (
                        <Button
                            type="button"
                            variant="danger"
                            onClick={() => {
                                if (confirm(`Hapus skema ${mitra.name}?`)) {
                                    router.delete(
                                        `/skema-mitra/${existing.id}`,
                                        {
                                            preserveScroll: true,
                                            onSuccess: onClose,
                                        },
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
