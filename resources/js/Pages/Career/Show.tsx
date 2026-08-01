import { Head, Link, useForm } from "@inertiajs/react";

type Vacancy = {
    id: number;
    title: string;
    category: string;
    categoryLabel: string;
    location: string | null;
    department: string | null;
    quota: number;
    description: string | null;
    requirements: string | null;
    publishedAt: string | null;
};

type Props = {
    vacancy: Vacancy;
};

export default function CareerShow({ vacancy }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        full_name: string;
        email: string;
        phone: string;
        cv: File | null;
        website: string; // Honeypot
    }>({
        full_name: "",
        email: "",
        phone: "",
        cv: null,
        website: "", // Honeypot — harus tetap kosong
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post(`/karier/${vacancy.id}/apply`, {
            forceFormData: true,
            onSuccess: () => reset(),
        });
    }

    return (
        <>
            <Head title={`${vacancy.title} — Karier`} />

            {/* Kolom flex agar footer tetap menempel di dasar layar. */}
            <div className="flex min-h-screen flex-col bg-[#f4f8fd]">
                {/* Header */}
                <header className="border-b border-[#e2ecf8] bg-white">
                    <div className="mx-auto flex max-w-5xl items-center justify-between px-5 py-5 sm:px-8">
                        <Link
                            href="/karier"
                            className="flex items-center gap-2.5"
                        >
                            <span className="grid h-8 w-8 place-items-center rounded-lg bg-[#2a78d6] text-sm font-semibold text-white">
                                H
                            </span>
                            <div className="leading-tight">
                                <p className="text-sm font-semibold text-[#0d1b2a]">
                                    HRIS &amp; ATS
                                </p>
                                <p className="text-[11px] text-[#8fa1b6]">
                                    Portal Karier
                                </p>
                            </div>
                        </Link>
                        <Link
                            href="/karier"
                            className="rounded-lg border border-[#e2ecf8] px-3.5 py-2 text-xs font-medium text-[#55677d] transition hover:bg-[#f0f6fe]"
                        >
                            ← Semua Lowongan
                        </Link>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-5xl flex-1 px-5 py-8 sm:px-8">
                    <div className="grid gap-6 lg:grid-cols-5">
                        {/* Detail lowongan */}
                        <div className="lg:col-span-3">
                            <div className="rounded-2xl border border-[#e2ecf8] bg-white p-6 sm:p-8">
                                <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                                    <h1 className="text-xl font-bold tracking-tight text-[#0d1b2a]">
                                        {vacancy.title}
                                    </h1>
                                    <span className="rounded-md bg-[#eff6ff] px-2.5 py-1 text-[11px] font-medium text-[#2a78d6]">
                                        {vacancy.categoryLabel}
                                    </span>
                                </div>

                                <dl className="mb-6 grid grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <dt className="text-[#8fa1b6]">
                                            Divisi
                                        </dt>
                                        <dd className="font-medium text-[#0d1b2a]">
                                            {vacancy.department ?? "-"}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-[#8fa1b6]">
                                            Lokasi
                                        </dt>
                                        <dd className="font-medium text-[#0d1b2a]">
                                            {vacancy.location ?? "-"}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-[#8fa1b6]">
                                            Kuota
                                        </dt>
                                        <dd className="font-medium text-[#0d1b2a]">
                                            {vacancy.quota} posisi
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-[#8fa1b6]">
                                            Dipublikasi
                                        </dt>
                                        <dd className="font-medium text-[#0d1b2a]">
                                            {vacancy.publishedAt ?? "-"}
                                        </dd>
                                    </div>
                                </dl>

                                {vacancy.description && (
                                    <div className="mb-5">
                                        <h2 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[#184f95]">
                                            Deskripsi
                                        </h2>
                                        <p className="whitespace-pre-line text-sm leading-relaxed text-[#55677d]">
                                            {vacancy.description}
                                        </p>
                                    </div>
                                )}

                                {vacancy.requirements && (
                                    <div>
                                        <h2 className="mb-2 text-xs font-semibold uppercase tracking-wider text-[#184f95]">
                                            Persyaratan
                                        </h2>
                                        <p className="whitespace-pre-line text-sm leading-relaxed text-[#55677d]">
                                            {vacancy.requirements}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Form lamaran */}
                        <div className="lg:col-span-2">
                            <div className="sticky top-6 rounded-2xl border border-[#e2ecf8] bg-white p-6">
                                <h2 className="mb-1 text-[15px] font-semibold text-[#0d1b2a]">
                                    Kirim Lamaran
                                </h2>
                                <p className="mb-5 text-xs text-[#8fa1b6]">
                                    Isi data diri dan unggah CV Anda
                                </p>

                                <form
                                    onSubmit={submit}
                                    className="space-y-4"
                                    encType="multipart/form-data"
                                >
                                    {/* Honeypot — hidden from users */}
                                    <div className="absolute -left-[9999px]" aria-hidden="true">
                                        <input
                                            type="text"
                                            name="website"
                                            tabIndex={-1}
                                            autoComplete="off"
                                            value={data.website}
                                            onChange={(e) => setData("website", e.target.value)}
                                        />
                                    </div>

                                    <label className="block">
                                        <span className="mb-1.5 block text-xs font-medium text-[#55677d]">
                                            Nama Lengkap{" "}
                                            <span className="text-[#d03b3b]">
                                                *
                                            </span>
                                        </span>
                                        <input
                                            type="text"
                                            value={data.full_name}
                                            onChange={(e) =>
                                                setData(
                                                    "full_name",
                                                    e.target.value,
                                                )
                                            }
                                            className="w-full rounded-lg border border-[#e2ecf8] bg-white px-3 py-2 text-sm text-[#0d1b2a] outline-none transition placeholder:text-[#8fa1b6] focus:border-[#3987e5] focus:ring-2 focus:ring-[#cde2fb]"
                                            required
                                        />
                                        {errors.full_name && (
                                            <span className="mt-1 block text-[11px] text-[#d03b3b]">
                                                {errors.full_name}
                                            </span>
                                        )}
                                    </label>

                                    <label className="block">
                                        <span className="mb-1.5 block text-xs font-medium text-[#55677d]">
                                            Email{" "}
                                            <span className="text-[#d03b3b]">
                                                *
                                            </span>
                                        </span>
                                        <input
                                            type="email"
                                            value={data.email}
                                            onChange={(e) =>
                                                setData(
                                                    "email",
                                                    e.target.value,
                                                )
                                            }
                                            className="w-full rounded-lg border border-[#e2ecf8] bg-white px-3 py-2 text-sm text-[#0d1b2a] outline-none transition placeholder:text-[#8fa1b6] focus:border-[#3987e5] focus:ring-2 focus:ring-[#cde2fb]"
                                            required
                                        />
                                        {errors.email && (
                                            <span className="mt-1 block text-[11px] text-[#d03b3b]">
                                                {errors.email}
                                            </span>
                                        )}
                                    </label>

                                    <label className="block">
                                        <span className="mb-1.5 block text-xs font-medium text-[#55677d]">
                                            Telepon
                                        </span>
                                        <input
                                            type="text"
                                            value={data.phone}
                                            onChange={(e) =>
                                                setData(
                                                    "phone",
                                                    e.target.value,
                                                )
                                            }
                                            className="w-full rounded-lg border border-[#e2ecf8] bg-white px-3 py-2 text-sm text-[#0d1b2a] outline-none transition placeholder:text-[#8fa1b6] focus:border-[#3987e5] focus:ring-2 focus:ring-[#cde2fb]"
                                        />
                                    </label>

                                    <label className="block">
                                        <span className="mb-1.5 block text-xs font-medium text-[#55677d]">
                                            Upload CV (PDF/DOC/DOCX, maks 5MB){" "}
                                            <span className="text-[#d03b3b]">
                                                *
                                            </span>
                                        </span>
                                        <input
                                            type="file"
                                            accept=".pdf,.doc,.docx"
                                            onChange={(e) =>
                                                setData(
                                                    "cv",
                                                    e.target.files?.[0] ?? null,
                                                )
                                            }
                                            className="w-full rounded-lg border border-[#e2ecf8] bg-white px-3 py-2 text-sm text-[#55677d] file:mr-3 file:rounded-md file:border-0 file:bg-[#f0f6fe] file:px-3 file:py-1 file:text-xs file:font-medium file:text-[#2a78d6]"
                                            required
                                        />
                                        {errors.cv && (
                                            <span className="mt-1 block text-[11px] text-[#d03b3b]">
                                                {errors.cv}
                                            </span>
                                        )}
                                    </label>

                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="w-full rounded-lg bg-[#2a78d6] px-4 py-2.5 text-sm font-medium text-white transition hover:bg-[#256abf] disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        {processing
                                            ? "Mengirim…"
                                            : "Kirim Lamaran"}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </main>

                <footer className="border-t border-[#e2ecf8] bg-white py-6">
                    <p className="text-center text-[11px] text-[#8fa1b6]">
                        © {new Date().getFullYear()} HRIS & ATS — Workforce
                        Platform
                    </p>
                </footer>
            </div>
        </>
    );
}
