import { useForm } from "@inertiajs/react";
import { useEffect } from "react";
import { Button, Field, Input, Select } from "@/Components/ui";

export type EmploymentTypeOption = {
    id: number;
    name: string;
    code: string;
    category: string;
    duration_months: number | null;
};

export type ConversionOptions = {
    departments: { id: number; name: string }[];
    employmentTypes: EmploymentTypeOption[];
    schemaTypes: string[];
    taxSchemes: string[];
};

const SCHEMA_LABELS: Record<string, string> = {
    fixed_project: "Fixed Project Fee",
    hourly: "Hourly Rate",
    daily: "Daily Rate",
    milestone: "Deliverable / Milestone",
    unit: "Unit / Output",
};

const TAX_LABELS: Record<string, string> = {
    pph21_berkesinambungan: "PPh 21 (Berkesinambungan)",
    pph21_tidak_berkesinambungan: "PPh 21 (Tidak Berkesinambungan)",
    pph23: "PPh 23",
    bebas_pajak: "Bebas Pajak",
};

/**
 * Form One-Click Hired Conversion.
 *
 * Dipakai bersama oleh papan pipeline dan halaman detail kandidat supaya
 * kedua jalur menghasilkan data karyawan yang sama — sebelumnya halaman
 * detail hanya mengubah tahap tanpa pernah membuat karyawan.
 */
export default function ConversionModal({
    applicant,
    options,
    onClose,
}: {
    applicant: { id: number; name: string; vacancyTitle: string | null };
    options: ConversionOptions;
    onClose: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({
        employment_type_id: "",
        department_id: "",
        position: "",
        basic_salary: 0,
        // Entitas mitra tidak punya durasi baku, jadi tanggal akhir kontraknya
        // diisi di sini agar tetap masuk peringatan kontrak H-30.
        contract_end: "",
        // Mitra fields
        schema_type: "hourly",
        rate_per_unit: 0,
        unit_label: "jam",
        tax_scheme: "pph21_tidak_berkesinambungan",
        custom_tax_percentage: 2.5,
    });

    // Escape menutup dialog — pintu keluar yang diharapkan pada modal.
    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (event.key === "Escape") onClose();
        }

        document.addEventListener("keydown", onKeyDown);

        return () => document.removeEventListener("keydown", onKeyDown);
    }, [onClose]);

    const selectedType = options.employmentTypes.find(
        (type) => type.id === Number(data.employment_type_id),
    );
    const isMitra = selectedType?.category === "mitra";

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post(`/rekrutmen/${applicant.id}/convert`, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-ink/30 backdrop-blur-sm">
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="conversion-title"
                className="mx-4 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-hairline bg-surface p-6 shadow-xl"
            >
                <h2
                    id="conversion-title"
                    className="text-lg font-semibold text-ink"
                >
                    Konversi ke Karyawan
                </h2>
                <p className="mt-1 text-xs text-ink-soft">
                    {applicant.name}
                    {applicant.vacancyTitle ? ` — ${applicant.vacancyTitle}` : ""}
                </p>

                <form onSubmit={submit} className="mt-5 space-y-4">
                    <Field
                        label="Entitas kerja"
                        error={errors.employment_type_id}
                        required
                    >
                        <Select
                            value={data.employment_type_id}
                            onChange={(event) =>
                                setData("employment_type_id", event.target.value)
                            }
                            required
                        >
                            <option value="">Pilih entitas…</option>
                            {options.employmentTypes.map((type) => (
                                <option key={type.id} value={type.id}>
                                    {type.name}
                                    {type.duration_months
                                        ? ` (${type.duration_months} bulan)`
                                        : ""}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Divisi" error={errors.department_id}>
                        <Select
                            value={data.department_id}
                            onChange={(event) =>
                                setData("department_id", event.target.value)
                            }
                        >
                            <option value="">Pilih divisi…</option>
                            {options.departments.map((department) => (
                                <option key={department.id} value={department.id}>
                                    {department.name}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Jabatan" error={errors.position}>
                        <Input
                            value={data.position}
                            onChange={(event) =>
                                setData("position", event.target.value)
                            }
                            placeholder="mis. Backend Engineer"
                        />
                    </Field>

                    <Field
                        label="Gaji pokok"
                        error={errors.basic_salary}
                        required
                        hint={
                            isMitra
                                ? "Mitra dibayar lewat skema di bawah — isi 0."
                                : undefined
                        }
                    >
                        <Input
                            type="number"
                            min={0}
                            step={100000}
                            value={data.basic_salary}
                            onChange={(event) =>
                                setData("basic_salary", Number(event.target.value))
                            }
                        />
                    </Field>

                    {/* Skema mitra — wajib, karena mitra tanpa skema akan
                        dilewati diam-diam oleh mesin payroll. */}
                    {isMitra && (
                        <div className="space-y-3 rounded-xl border border-hairline bg-surface-soft p-4">
                            <p className="text-xs font-semibold text-ink">
                                Skema Pembayaran Mitra
                            </p>

                            <Field
                                label="Kontrak berakhir"
                                error={errors.contract_end}
                                hint="Mitra tidak punya durasi baku — isi agar masuk peringatan H-30."
                            >
                                <Input
                                    type="date"
                                    value={data.contract_end}
                                    onChange={(event) =>
                                        setData("contract_end", event.target.value)
                                    }
                                />
                            </Field>

                            <Field label="Tipe skema" error={errors.schema_type}>
                                <Select
                                    value={data.schema_type}
                                    onChange={(event) =>
                                        setData("schema_type", event.target.value)
                                    }
                                >
                                    {options.schemaTypes.map((type) => (
                                        <option key={type} value={type}>
                                            {SCHEMA_LABELS[type] ?? type}
                                        </option>
                                    ))}
                                </Select>
                            </Field>

                            <Field label="Tarif" error={errors.rate_per_unit}>
                                <Input
                                    type="number"
                                    min={0}
                                    step={1000}
                                    value={data.rate_per_unit}
                                    onChange={(event) =>
                                        setData(
                                            "rate_per_unit",
                                            Number(event.target.value),
                                        )
                                    }
                                />
                            </Field>

                            <Field label="Satuan" error={errors.unit_label}>
                                <Input
                                    value={data.unit_label}
                                    onChange={(event) =>
                                        setData("unit_label", event.target.value)
                                    }
                                />
                            </Field>

                            <Field label="Skema pajak" error={errors.tax_scheme}>
                                <Select
                                    value={data.tax_scheme}
                                    onChange={(event) =>
                                        setData("tax_scheme", event.target.value)
                                    }
                                >
                                    {options.taxSchemes.map((scheme) => (
                                        <option key={scheme} value={scheme}>
                                            {TAX_LABELS[scheme] ?? scheme}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        </div>
                    )}

                    <div className="flex gap-2 pt-2">
                        <Button type="submit" disabled={processing}>
                            {processing ? "Mengkonversi…" : "Konversi"}
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={onClose}
                        >
                            Batal
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}
