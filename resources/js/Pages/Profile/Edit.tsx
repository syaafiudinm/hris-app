import { Head, useForm } from "@inertiajs/react";
import AppLayout from "@/Layouts/AppLayout";
import Card from "@/Components/Card";
import EmployeeDocuments, {
    type DocumentSlot,
} from "@/Components/EmployeeDocuments";
import {
    EmergencyContactFields,
    IdentityFields,
    personalDataFrom,
    type PersonalOptions,
} from "@/Components/PersonalDataFields";
import { Button, Field, Input } from "@/Components/ui";

type Props = {
    profile: Record<string, string | number | null>;
    employment: {
        nik: string;
        department: string | null;
        position: string | null;
        joinDate: string | null;
        cooperation: string | null;
        employmentType: string | null;
    };
    summary: {
        completion: number;
        missingFields: string[];
        missingDocuments: string[];
    };
    documents: DocumentSlot[];
    options: PersonalOptions;
};

export default function ProfileEdit({
    profile,
    employment,
    summary,
    documents,
    options,
}: Props) {
    const form = useForm({
        full_name: (profile.full_name as string) ?? "",
        email: (profile.email as string) ?? "",
        phone: (profile.phone as string) ?? "",
        ...personalDataFrom(profile),
    });

    const setField = (field: string, value: string | number) =>
        form.setData(field as keyof typeof form.data, value as never);

    const complete = summary.completion >= 100;

    return (
        <AppLayout
            title="Data Diri"
            subtitle="Lengkapi data pribadi dan dokumen kelengkapan Anda"
        >
            <Head title="Data Diri" />

            <div className="grid gap-5 xl:grid-cols-3">
                <div className="space-y-5 xl:col-span-2">
                    <form
                        className="space-y-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.put("/data-diri", { preserveScroll: true });
                        }}
                    >
                        <Card title="Identitas">
                            <div className="space-y-4">
                                <Field
                                    label="Nama lengkap"
                                    error={form.errors.full_name}
                                    required
                                    hint="Sesuai KTP."
                                >
                                    <Input
                                        value={form.data.full_name}
                                        onChange={(event) =>
                                            form.setData(
                                                "full_name",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>

                                <IdentityFields
                                    data={form.data}
                                    setData={setField}
                                    errors={form.errors}
                                    options={options}
                                    required
                                />
                            </div>
                        </Card>

                        <Card title="Kontak">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="No WhatsApp"
                                    error={form.errors.phone}
                                    required
                                >
                                    <Input
                                        inputMode="tel"
                                        value={form.data.phone}
                                        onChange={(event) =>
                                            form.setData(
                                                "phone",
                                                event.target.value,
                                            )
                                        }
                                        placeholder="08xxxxxxxxxx"
                                    />
                                </Field>
                                <Field
                                    label="Email aktif"
                                    error={form.errors.email}
                                    required
                                    hint="Juga dipakai untuk login."
                                >
                                    <Input
                                        type="email"
                                        value={form.data.email}
                                        onChange={(event) =>
                                            form.setData(
                                                "email",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </Card>

                        <Card title="Kontak darurat">
                            <EmergencyContactFields
                                data={form.data}
                                setData={setField}
                                errors={form.errors}
                                required
                            />
                        </Card>

                        <div className="flex items-center gap-3">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? "Menyimpan…"
                                    : "Simpan data diri"}
                            </Button>
                            {form.isDirty && (
                                <span className="text-[11px] text-ink-muted">
                                    Ada perubahan yang belum disimpan.
                                </span>
                            )}
                        </div>
                    </form>

                    <Card
                        title="Dokumen kelengkapan"
                        subtitle="Setiap dokumen diunggah dalam bentuk PDF. Berkas langsung tersimpan begitu dipilih."
                    >
                        <EmployeeDocuments
                            slots={documents}
                            uploadUrl="/data-diri/dokumen"
                        />
                    </Card>
                </div>

                <div className="space-y-5">
                    <Card title="Kelengkapan">
                        <div className="flex items-baseline justify-between">
                            <p className="tabular text-2xl font-semibold text-ink">
                                {summary.completion}%
                            </p>
                            <p className="text-[11px] text-ink-muted">
                                {complete ? "Lengkap" : "Belum lengkap"}
                            </p>
                        </div>
                        <div className="mt-2 h-2 overflow-hidden rounded-full bg-surface-soft">
                            <div
                                className={`h-full rounded-full ${complete ? "bg-[#0ca30c]" : "bg-brand-500"}`}
                                style={{ width: `${summary.completion}%` }}
                            />
                        </div>

                        {!complete && (
                            <div className="mt-4 space-y-3 text-xs">
                                {summary.missingFields.length > 0 && (
                                    <div>
                                        <p className="font-medium text-ink">
                                            Isian yang masih kosong
                                        </p>
                                        <p className="mt-0.5 text-ink-soft">
                                            {summary.missingFields.join(", ")}
                                        </p>
                                    </div>
                                )}
                                {summary.missingDocuments.length > 0 && (
                                    <div>
                                        <p className="font-medium text-ink">
                                            Dokumen wajib yang belum diunggah
                                        </p>
                                        <p className="mt-0.5 text-ink-soft">
                                            {summary.missingDocuments.join(
                                                ", ",
                                            )}
                                        </p>
                                    </div>
                                )}
                            </div>
                        )}
                    </Card>

                    <Card
                        title="Kepegawaian"
                        subtitle="Diatur HR. Hubungi HR bila ada yang tidak sesuai."
                    >
                        <dl className="space-y-3">
                            <Detail label="NIK karyawan">
                                {employment.nik}
                            </Detail>
                            <Detail label="Departemen">
                                {employment.department}
                            </Detail>
                            <Detail label="Posisi">
                                {employment.position}
                            </Detail>
                            <Detail label="Tanggal bergabung">
                                {employment.joinDate}
                            </Detail>
                            <Detail label="Status kerja sama">
                                {employment.cooperation}
                                {employment.employmentType &&
                                    employment.employmentType !==
                                        employment.cooperation && (
                                        <span className="text-ink-muted">
                                            {" "}
                                            · {employment.employmentType}
                                        </span>
                                    )}
                            </Detail>
                        </dl>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

function Detail({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <dt className="text-[11px] text-ink-muted">{label}</dt>
            <dd className="mt-0.5 text-sm text-ink">{children || "-"}</dd>
        </div>
    );
}
