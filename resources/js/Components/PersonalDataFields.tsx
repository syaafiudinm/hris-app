import { Field, Input, Select, Textarea } from "@/Components/ui";

export type PersonalData = {
    ktp_number: string;
    birth_place: string;
    birth_date: string;
    gender: string;
    religion: string;
    marital_status: string;
    dependents_count: number | "";
    ktp_address: string;
    domicile_address: string;
    emergency_contact_name: string;
    emergency_contact_relation: string;
    emergency_contact_phone: string;
};

type Option = { value: string; label: string };

export type PersonalOptions = {
    genders: Option[];
    religions: Option[];
    maritalStatuses: Option[];
};

const RELATIONS = [
    "Orang tua",
    "Suami",
    "Istri",
    "Saudara kandung",
    "Anak",
    "Kerabat",
    "Teman",
];

/** Nilai awal form dari data server yang mungkin masih kosong. */
export function personalDataFrom(
    source: Record<string, unknown> | null | undefined,
): PersonalData {
    const text = (key: string) => (source?.[key] as string | null) ?? "";
    const count = source?.dependents_count;

    return {
        ktp_number: text("ktp_number"),
        birth_place: text("birth_place"),
        birth_date: text("birth_date"),
        gender: text("gender"),
        religion: text("religion"),
        marital_status: text("marital_status"),
        dependents_count: typeof count === "number" ? count : "",
        ktp_address: text("ktp_address"),
        domicile_address: text("domicile_address"),
        emergency_contact_name: text("emergency_contact_name"),
        emergency_contact_relation: text("emergency_contact_relation"),
        emergency_contact_phone: text("emergency_contact_phone"),
    };
}

type Props = {
    data: PersonalData;
    setData: (field: keyof PersonalData, value: string | number) => void;
    errors: Partial<Record<string, string>>;
    options: PersonalOptions;
    /** Tandai isian sebagai wajib (halaman Data Diri milik karyawan). */
    required?: boolean;
};

export function IdentityFields({
    data,
    setData,
    errors,
    options,
    required = false,
}: Props) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <Field
                label="No KTP"
                error={errors.ktp_number}
                required={required}
                hint="16 digit sesuai KTP."
            >
                <Input
                    inputMode="numeric"
                    maxLength={16}
                    value={data.ktp_number}
                    onChange={(event) =>
                        setData(
                            "ktp_number",
                            event.target.value.replace(/\D/g, ""),
                        )
                    }
                    placeholder="7371xxxxxxxxxxxx"
                />
            </Field>
            <Field
                label="Jenis kelamin"
                error={errors.gender}
                required={required}
            >
                <Select
                    value={data.gender}
                    onChange={(event) => setData("gender", event.target.value)}
                >
                    <option value="">— pilih —</option>
                    {options.genders.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </Select>
            </Field>
            <Field
                label="Tempat lahir"
                error={errors.birth_place}
                required={required}
            >
                <Input
                    value={data.birth_place}
                    onChange={(event) =>
                        setData("birth_place", event.target.value)
                    }
                    placeholder="Makassar"
                />
            </Field>
            <Field
                label="Tanggal lahir"
                error={errors.birth_date}
                required={required}
            >
                <Input
                    type="date"
                    value={data.birth_date}
                    onChange={(event) =>
                        setData("birth_date", event.target.value)
                    }
                />
            </Field>
            <Field label="Agama" error={errors.religion} required={required}>
                <Select
                    value={data.religion}
                    onChange={(event) =>
                        setData("religion", event.target.value)
                    }
                >
                    <option value="">— pilih —</option>
                    {options.religions.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </Select>
            </Field>
            <Field
                label="Status pernikahan"
                error={errors.marital_status}
                required={required}
            >
                <Select
                    value={data.marital_status}
                    onChange={(event) =>
                        setData("marital_status", event.target.value)
                    }
                >
                    <option value="">— pilih —</option>
                    {options.maritalStatuses.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </Select>
            </Field>
            <Field
                label="Jumlah tanggungan se-KK"
                error={errors.dependents_count}
                required={required}
                hint="Isi 0 bila tidak ada tanggungan."
            >
                <Input
                    type="number"
                    min={0}
                    max={20}
                    value={data.dependents_count}
                    onChange={(event) =>
                        setData(
                            "dependents_count",
                            event.target.value === ""
                                ? ""
                                : Number(event.target.value),
                        )
                    }
                />
            </Field>
            <div className="sm:col-span-2">
                <Field
                    label="Alamat sesuai KTP"
                    error={errors.ktp_address}
                    required={required}
                >
                    <Textarea
                        rows={2}
                        value={data.ktp_address}
                        onChange={(event) =>
                            setData("ktp_address", event.target.value)
                        }
                    />
                </Field>
            </div>
            <div className="sm:col-span-2">
                <Field
                    label="Alamat domisili saat ini"
                    error={errors.domicile_address}
                    required={required}
                >
                    <Textarea
                        rows={2}
                        value={data.domicile_address}
                        onChange={(event) =>
                            setData("domicile_address", event.target.value)
                        }
                    />
                </Field>
                {data.ktp_address && (
                    <button
                        type="button"
                        onClick={() =>
                            setData("domicile_address", data.ktp_address)
                        }
                        className="mt-1 text-[11px] font-medium text-brand-600 hover:text-brand-700"
                    >
                        Sama dengan alamat KTP
                    </button>
                )}
            </div>
        </div>
    );
}

export function EmergencyContactFields({
    data,
    setData,
    errors,
    required = false,
}: Omit<Props, "options">) {
    return (
        <div className="grid gap-4 sm:grid-cols-3">
            <Field
                label="Nama kontak darurat"
                error={errors.emergency_contact_name}
                required={required}
            >
                <Input
                    value={data.emergency_contact_name}
                    onChange={(event) =>
                        setData("emergency_contact_name", event.target.value)
                    }
                />
            </Field>
            <Field
                label="Hubungan"
                error={errors.emergency_contact_relation}
                required={required}
            >
                <Input
                    list="emergency-relations"
                    value={data.emergency_contact_relation}
                    onChange={(event) =>
                        setData(
                            "emergency_contact_relation",
                            event.target.value,
                        )
                    }
                    placeholder="Orang tua"
                />
                <datalist id="emergency-relations">
                    {RELATIONS.map((relation) => (
                        <option key={relation} value={relation} />
                    ))}
                </datalist>
            </Field>
            <Field
                label="Nomor kontak darurat"
                error={errors.emergency_contact_phone}
                required={required}
            >
                <Input
                    inputMode="tel"
                    value={data.emergency_contact_phone}
                    onChange={(event) =>
                        setData("emergency_contact_phone", event.target.value)
                    }
                    placeholder="08xxxxxxxxxx"
                />
            </Field>
        </div>
    );
}
