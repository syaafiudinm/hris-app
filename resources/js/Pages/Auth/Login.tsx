import { Head, useForm } from "@inertiajs/react";
import { Button, Field, Input } from "@/Components/ui";

const DEMO_ACCOUNTS = [
    { role: "Super Admin / HR", email: "hr@perusahaan.co.id" },
    { role: "Manager / Atasan", email: "manager@perusahaan.co.id" },
    { role: "Karyawan", email: "karyawan@perusahaan.co.id" },
    { role: "Mitra", email: "mitra@perusahaan.co.id" },
];

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: "",
        password: "",
        remember: false,
    });

    return (
        <>
            <Head title="Masuk" />

            <div className="grid min-h-screen bg-plane lg:grid-cols-2">
                {/* Panel merek */}
                <div className="hidden flex-col justify-between bg-brand-700 p-10 text-white lg:flex">
                    <div className="flex items-center gap-2.5">
                        <span className="grid h-8 w-8 place-items-center rounded-lg bg-white/15 text-sm font-semibold">
                            H
                        </span>
                        <span className="text-sm font-medium">HRIS &amp; ATS</span>
                    </div>

                    <div>
                        <h2 className="text-3xl leading-tight font-semibold tracking-tight">
                            Satu platform untuk seluruh
                            <br />
                            siklus tenaga kerja.
                        </h2>
                        <p className="mt-4 max-w-md text-sm text-white/70">
                            Karyawan PKWT, masa percobaan, hingga mitra dengan
                            skema pembayaran custom — aturan cuti dan BPJS
                            ditegakkan otomatis oleh sistem.
                        </p>
                    </div>

                    <p className="text-xs text-white/50">
                        Absensi GPS &amp; anti-fake GPS · Payroll PPh 21 TER ·
                        Ekspor Excel/CSV/PDF
                    </p>
                </div>

                {/* Form */}
                <div className="flex items-center justify-center px-5 py-12">
                    <div className="w-full max-w-sm">
                        <div className="mb-8">
                            <h1 className="text-2xl font-semibold tracking-tight text-ink">
                                Masuk ke akun Anda
                            </h1>
                            <p className="mt-1.5 text-sm text-ink-soft">
                                Gunakan email perusahaan yang terdaftar.
                            </p>
                        </div>

                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                post("/login");
                            }}
                            className="space-y-4"
                        >
                            <Field label="Email" error={errors.email} required>
                                <Input
                                    type="email"
                                    value={data.email}
                                    autoComplete="username"
                                    autoFocus
                                    onChange={(event) =>
                                        setData("email", event.target.value)
                                    }
                                    placeholder="nama@perusahaan.co.id"
                                />
                            </Field>

                            <Field
                                label="Kata sandi"
                                error={errors.password}
                                required
                            >
                                <Input
                                    type="password"
                                    value={data.password}
                                    autoComplete="current-password"
                                    onChange={(event) =>
                                        setData("password", event.target.value)
                                    }
                                    placeholder="••••••••"
                                />
                            </Field>

                            <label className="flex items-center gap-2 text-xs text-ink-soft">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(event) =>
                                        setData("remember", event.target.checked)
                                    }
                                    className="h-3.5 w-3.5 rounded border-hairline-strong text-brand-500 focus:ring-brand-100"
                                />
                                Ingat saya di perangkat ini
                            </label>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full py-2.5"
                            >
                                {processing ? "Memproses…" : "Masuk"}
                            </Button>
                        </form>

                        <div className="mt-8 rounded-xl border border-hairline bg-surface p-4">
                            <p className="text-[11px] font-medium text-ink-soft">
                                Akun demo (kata sandi:{" "}
                                <code className="text-brand-700">password</code>)
                            </p>
                            <ul className="mt-2 space-y-1">
                                {DEMO_ACCOUNTS.map((account) => (
                                    <li
                                        key={account.email}
                                        className="flex items-center justify-between gap-3 text-[11px]"
                                    >
                                        <span className="text-ink-muted">
                                            {account.role}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setData("email", account.email);
                                                setData("password", "password");
                                            }}
                                            className="font-medium text-brand-600 transition hover:text-brand-700"
                                        >
                                            {account.email}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
