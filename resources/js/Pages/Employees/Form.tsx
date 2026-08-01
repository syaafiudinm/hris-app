import { Head, useForm } from "@inertiajs/react";
import Card from "@/Components/Card";
import AppLayout from "@/Layouts/AppLayout";
import { Button, Field, Input, LinkButton, Select } from "@/Components/ui";

type EmploymentTypeOption = {
    id: number;
    name: string;
    code: string;
    category: string;
    duration_months: number | null;
};

type Props = {
    employee: Record<string, string | number | null> | null;
    options: {
        employmentTypes: EmploymentTypeOption[];
        departments: { id: number; name: string }[];
        statuses: string[];
    };
};

export default function EmployeeForm({ employee, options }: Props) {
    const isEdit = Boolean(employee?.id);

    const { data, setData, post, put, processing, errors } = useForm({
        nik: (employee?.nik as string) ?? "",
        full_name: (employee?.full_name as string) ?? "",
        email: (employee?.email as string) ?? "",
        phone: (employee?.phone as string) ?? "",
        position: (employee?.position as string) ?? "",
        // Kosong berarti belum dipilih; dikirim sebagai "" agar validasi
        // required di server yang menolak, bukan angka 0 palsu.
        employment_type_id: (employee?.employment_type_id ?? "") as number | "",
        department_id: (employee?.department_id ?? "") as number | "",
        join_date: (employee?.join_date as string) ?? "",
        contract_start: (employee?.contract_start as string) ?? "",
        contract_end: (employee?.contract_end as string) ?? "",
        basic_salary: (employee?.basic_salary as number) ?? 0,
        status: (employee?.status as string) ?? "active",
    });

    const selectedType = options.employmentTypes.find(
        (type) => type.id === Number(data.employment_type_id),
    );
    const isMitra = selectedType?.category === "mitra";

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (isEdit) {
            put(`/employees/${employee?.id}`);
        } else {
            post("/employees");
        }
    }

    return (
        <AppLayout
            title={isEdit ? "Ubah Data Tenaga Kerja" : "Tambah Tenaga Kerja"}
            subtitle="Entitas kerja yang dipilih menentukan hak cuti & BPJS secara otomatis"
        >
            <Head title={isEdit ? "Ubah Tenaga Kerja" : "Tambah Tenaga Kerja"} />

            <form onSubmit={submit} className="max-w-3xl space-y-5">
                <Card title="Identitas">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="NIK" error={errors.nik} required>
                            <Input
                                value={data.nik}
                                onChange={(event) =>
                                    setData("nik", event.target.value)
                                }
                                placeholder="EMP-0001"
                            />
                        </Field>
                        <Field
                            label="Nama lengkap"
                            error={errors.full_name}
                            required
                        >
                            <Input
                                value={data.full_name}
                                onChange={(event) =>
                                    setData("full_name", event.target.value)
                                }
                            />
                        </Field>
                        <Field label="Email" error={errors.email}>
                            <Input
                                type="email"
                                value={data.email}
                                onChange={(event) =>
                                    setData("email", event.target.value)
                                }
                            />
                        </Field>
                        <Field label="Telepon" error={errors.phone}>
                            <Input
                                value={data.phone}
                                onChange={(event) =>
                                    setData("phone", event.target.value)
                                }
                            />
                        </Field>
                        <Field label="Jabatan" error={errors.position}>
                            <Input
                                value={data.position}
                                onChange={(event) =>
                                    setData("position", event.target.value)
                                }
                            />
                        </Field>
                        <Field label="Divisi" error={errors.department_id}>
                            <Select
                                value={data.department_id}
                                onChange={(event) =>
                                    setData(
                                        "department_id",
                                        Number(event.target.value) || "",
                                    )
                                }
                            >
                                <option value="">— pilih divisi —</option>
                                {options.departments.map((department) => (
                                    <option
                                        key={department.id}
                                        value={department.id}
                                    >
                                        {department.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    </div>
                </Card>

                <Card title="Entitas kerja & kontrak">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Entitas kerja"
                            error={errors.employment_type_id}
                            required
                            hint={
                                selectedType
                                    ? entityHint(selectedType.category)
                                    : undefined
                            }
                        >
                            <Select
                                value={data.employment_type_id}
                                onChange={(event) =>
                                    setData(
                                        "employment_type_id",
                                        Number(event.target.value) || "",
                                    )
                                }
                            >
                                <option value="">— pilih entitas —</option>
                                {options.employmentTypes.map((type) => (
                                    <option key={type.id} value={type.id}>
                                        {type.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <Field label="Status" error={errors.status} required>
                            <Select
                                value={data.status}
                                onChange={(event) =>
                                    setData("status", event.target.value)
                                }
                            >
                                {options.statuses.map((status) => (
                                    <option key={status} value={status}>
                                        {status}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <Field
                            label="Tanggal bergabung"
                            error={errors.join_date}
                            required
                        >
                            <Input
                                type="date"
                                value={data.join_date}
                                onChange={(event) =>
                                    setData("join_date", event.target.value)
                                }
                            />
                        </Field>

                        <Field
                            label="Gaji pokok"
                            error={errors.basic_salary}
                            required
                            hint={
                                isMitra
                                    ? "Mitra dibayar lewat Skema Mitra — isi 0 di sini."
                                    : undefined
                            }
                        >
                            <Input
                                type="number"
                                min={0}
                                step={100000}
                                value={data.basic_salary}
                                onChange={(event) =>
                                    setData(
                                        "basic_salary",
                                        Number(event.target.value),
                                    )
                                }
                            />
                        </Field>

                        <Field
                            label="Kontrak mulai"
                            error={errors.contract_start}
                        >
                            <Input
                                type="date"
                                value={data.contract_start}
                                onChange={(event) =>
                                    setData("contract_start", event.target.value)
                                }
                            />
                        </Field>

                        <Field
                            label="Kontrak berakhir"
                            error={errors.contract_end}
                            hint={
                                selectedType?.duration_months
                                    ? `Durasi standar ${selectedType.duration_months} bulan.`
                                    : undefined
                            }
                        >
                            <Input
                                type="date"
                                value={data.contract_end}
                                onChange={(event) =>
                                    setData("contract_end", event.target.value)
                                }
                            />
                        </Field>
                    </div>
                </Card>

                <div className="flex items-center gap-2">
                    <Button type="submit" disabled={processing}>
                        {processing ? "Menyimpan…" : "Simpan"}
                    </Button>
                    <LinkButton href="/employees">Batal</LinkButton>
                </div>
            </form>
        </AppLayout>
    );
}

function entityHint(category: string): string {
    switch (category) {
        case "probation":
            return "Masa percobaan: tanpa kuota cuti tahunan dan tanpa BPJS.";
        case "mitra":
            return "Mitra: tanpa cuti tahunan & BPJS, pembayaran lewat skema custom.";
        default:
            return "PKWT: hak cuti proporsional, BPJS, dan PPh 21 TER aktif.";
    }
}
