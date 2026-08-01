import { Head, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import Card from "@/Components/Card";
import AppLayout from "@/Layouts/AppLayout";
import { Badge, Button, Field, Input, Select } from "@/Components/ui";
import { angka } from "@/lib/format";

type EmploymentType = {
    id: number;
    code: string;
    name: string;
    category: string;
    durationMonths: number | null;
    isLeaveEligible: boolean;
    isBpjsEligible: boolean;
    annualLeaveQuota: number;
    activeCount: number;
};

const CATEGORY_TONE: Record<string, "brand" | "warning" | "neutral"> = {
    pkwt: "brand",
    probation: "warning",
    mitra: "neutral",
};

export default function EmploymentTypesIndex({
    types,
}: {
    types: EmploymentType[];
}) {
    const [editing, setEditing] = useState<EmploymentType | null>(null);

    return (
        <AppLayout
            title="Definisi Entitas Kerja"
            subtitle="Aturan di sini dibaca langsung oleh mesin payroll dan portal cuti"
            actions={
                <Button
                    onClick={() =>
                        setEditing({
                            id: 0,
                            code: "",
                            name: "",
                            category: "pkwt",
                            durationMonths: 12,
                            isLeaveEligible: true,
                            isBpjsEligible: true,
                            annualLeaveQuota: 12,
                            activeCount: 0,
                        })
                    }
                >
                    Tambah entitas
                </Button>
            }
        >
            <Head title="Entitas Kerja" />

            <div className="grid gap-5 xl:grid-cols-3">
                <div className="xl:col-span-2">
                    <Card
                        title="Daftar entitas"
                        subtitle="Mengubah aturan berlaku untuk perhitungan periode berikutnya"
                    >
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[700px] text-sm">
                                <thead>
                                    <tr className="border-b border-hairline text-left text-xs text-ink-muted">
                                        <th className="pb-2 font-medium">
                                            Entitas
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Durasi
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Hak cuti
                                        </th>
                                        <th className="pb-2 font-medium">
                                            BPJS
                                        </th>
                                        <th className="pb-2 text-right font-medium">
                                            Aktif
                                        </th>
                                        <th className="pb-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {types.map((type) => (
                                        <tr
                                            key={type.id}
                                            className="border-b border-hairline last:border-0"
                                        >
                                            <td className="py-2.5">
                                                <p className="font-medium text-ink">
                                                    {type.name}
                                                </p>
                                                <div className="mt-1">
                                                    <Badge
                                                        tone={
                                                            CATEGORY_TONE[
                                                                type.category
                                                            ] ?? "neutral"
                                                        }
                                                    >
                                                        {type.code}
                                                    </Badge>
                                                </div>
                                            </td>
                                            <td className="tabular py-2.5 text-ink-soft">
                                                {type.durationMonths
                                                    ? `${type.durationMonths} bln`
                                                    : "—"}
                                            </td>
                                            <td className="py-2.5">
                                                {type.isLeaveEligible ? (
                                                    <span className="text-xs font-medium text-[#0a7a0a]">
                                                        Ya · {type.annualLeaveQuota} hari
                                                    </span>
                                                ) : (
                                                    <span className="text-xs text-ink-muted">
                                                        Diblokir
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2.5">
                                                {type.isBpjsEligible ? (
                                                    <span className="text-xs font-medium text-[#0a7a0a]">
                                                        Terdaftar
                                                    </span>
                                                ) : (
                                                    <span className="text-xs text-ink-muted">
                                                        Dikecualikan
                                                    </span>
                                                )}
                                            </td>
                                            <td className="tabular py-2.5 text-right text-ink">
                                                {angka(type.activeCount)}
                                            </td>
                                            <td className="py-2.5 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setEditing(type)
                                                    }
                                                    className="text-[11px] font-medium text-brand-600 transition hover:text-brand-700"
                                                >
                                                    Ubah
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </div>

                {editing ? (
                    <EntityForm
                        key={editing.id}
                        type={editing}
                        onClose={() => setEditing(null)}
                    />
                ) : (
                    <Card
                        title="Cara kerja aturan"
                        subtitle="Ditegakkan di server, bukan hanya di tampilan"
                    >
                        <ul className="space-y-3 text-xs text-ink-soft">
                            <li>
                                <span className="font-medium text-ink">
                                    Hak cuti nonaktif
                                </span>{" "}
                                membuat API pengajuan cuti tahunan membalas 403
                                Forbidden. Izin sakit dan tanpa gaji tetap bisa
                                diajukan.
                            </li>
                            <li>
                                <span className="font-medium text-ink">
                                    BPJS dikecualikan
                                </span>{" "}
                                membuat mesin payroll menetapkan potongan pekerja
                                dan kontribusi perusahaan ke nol.
                            </li>
                            <li>
                                <span className="font-medium text-ink">
                                    Kategori mitra
                                </span>{" "}
                                melewati skema gaji karyawan sepenuhnya dan
                                membaca tabel skema pembayaran custom.
                            </li>
                        </ul>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}

function EntityForm({
    type,
    onClose,
}: {
    type: EmploymentType;
    onClose: () => void;
}) {
    const isNew = type.id === 0;

    const { data, setData, post, patch, processing, errors } = useForm({
        code: type.code,
        name: type.name,
        category: type.category,
        duration_months: type.durationMonths ?? "",
        is_leave_eligible: type.isLeaveEligible,
        is_bpjs_eligible: type.isBpjsEligible,
        annual_leave_quota: type.annualLeaveQuota,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (isNew) {
            post("/entitas-kerja", { onSuccess: onClose });
        } else {
            patch(`/entitas-kerja/${type.id}`, { onSuccess: onClose });
        }
    }

    return (
        <Card
            title={isNew ? "Entitas baru" : `Ubah ${type.name}`}
            subtitle={
                isNew
                    ? undefined
                    : `${type.activeCount} tenaga kerja aktif memakai entitas ini`
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <Field label="Kode" error={errors.code} required>
                    <Input
                        value={data.code}
                        onChange={(event) => setData("code", event.target.value)}
                        placeholder="PKWT12"
                    />
                </Field>

                <Field label="Nama" error={errors.name} required>
                    <Input
                        value={data.name}
                        onChange={(event) => setData("name", event.target.value)}
                    />
                </Field>

                <Field label="Kategori" error={errors.category} required>
                    <Select
                        value={data.category}
                        onChange={(event) =>
                            setData("category", event.target.value)
                        }
                    >
                        <option value="probation">Probation</option>
                        <option value="pkwt">PKWT</option>
                        <option value="mitra">Mitra</option>
                    </Select>
                </Field>

                <Field
                    label="Durasi kontrak (bulan)"
                    error={errors.duration_months}
                    hint="Kosongkan untuk mitra tanpa durasi tetap."
                >
                    <Input
                        type="number"
                        min={1}
                        max={60}
                        value={data.duration_months}
                        onChange={(event) =>
                            setData(
                                "duration_months",
                                event.target.value === ""
                                    ? ""
                                    : Number(event.target.value),
                            )
                        }
                    />
                </Field>

                <label className="flex items-start gap-2.5">
                    <input
                        type="checkbox"
                        checked={data.is_leave_eligible}
                        onChange={(event) =>
                            setData("is_leave_eligible", event.target.checked)
                        }
                        className="mt-0.5 h-3.5 w-3.5 rounded border-hairline-strong text-brand-500 focus:ring-brand-100"
                    />
                    <span className="text-xs text-ink-soft">
                        <span className="font-medium text-ink">
                            Berhak cuti tahunan
                        </span>
                        <br />
                        Jika dimatikan, pengajuan cuti tahunan ditolak sistem.
                    </span>
                </label>

                <label className="flex items-start gap-2.5">
                    <input
                        type="checkbox"
                        checked={data.is_bpjs_eligible}
                        onChange={(event) =>
                            setData("is_bpjs_eligible", event.target.checked)
                        }
                        className="mt-0.5 h-3.5 w-3.5 rounded border-hairline-strong text-brand-500 focus:ring-brand-100"
                    />
                    <span className="text-xs text-ink-soft">
                        <span className="font-medium text-ink">
                            Didaftarkan BPJS
                        </span>
                        <br />
                        Jika dimatikan, potongan & kontribusi BPJS jadi nol.
                    </span>
                </label>

                <Field
                    label="Kuota cuti tahunan (hari)"
                    error={errors.annual_leave_quota}
                    required
                >
                    <Input
                        type="number"
                        min={0}
                        max={60}
                        value={data.annual_leave_quota}
                        onChange={(event) =>
                            setData(
                                "annual_leave_quota",
                                Number(event.target.value),
                            )
                        }
                    />
                </Field>

                <div className="flex flex-wrap items-center gap-2 pt-1">
                    <Button type="submit" disabled={processing}>
                        {processing ? "Menyimpan…" : "Simpan"}
                    </Button>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    {!isNew && type.activeCount === 0 && (
                        <Button
                            type="button"
                            variant="danger"
                            onClick={() => {
                                if (confirm(`Hapus entitas ${type.name}?`)) {
                                    router.delete(`/entitas-kerja/${type.id}`, {
                                        onSuccess: onClose,
                                    });
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
