import { Head, Link, router } from "@inertiajs/react";
import { useState } from "react";

type Vacancy = {
    id: number;
    title: string;
    category: string;
    categoryLabel: string;
    location: string | null;
    department: string | null;
    quota: number;
    description: string | null;
    publishedAt: string | null;
    applicantCount: number;
};

type Props = {
    vacancies: Vacancy[];
    filters: { category: string | null };
};

const CATEGORY_COLORS: Record<string, string> = {
    probation: "bg-amber-100 text-amber-800",
    pkwt: "bg-blue-100 text-blue-800",
    mitra: "bg-emerald-100 text-emerald-800",
};

export default function CareerIndex({ vacancies, filters }: Props) {
    const [category, setCategory] = useState(filters.category ?? "");

    function filterByCategory(cat: string) {
        const value = cat === category ? "" : cat;
        setCategory(value);
        router.get("/karier", { category: value || undefined }, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title="Karier — Lowongan Terbuka" />

            {/* Kolom flex agar footer tetap menempel di dasar layar
                walau daftar lowongan kosong. */}
            <div className="flex min-h-screen flex-col bg-[#f4f8fd]">
                {/* Header */}
                <header className="border-b border-[#e2ecf8] bg-white">
                    <div className="mx-auto flex max-w-5xl items-center justify-between px-5 py-5 sm:px-8">
                        <div className="flex items-center gap-2.5">
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
                        </div>
                        <Link
                            href="/login"
                            className="rounded-lg border border-[#e2ecf8] px-3.5 py-2 text-xs font-medium text-[#55677d] transition hover:bg-[#f0f6fe]"
                        >
                            Login
                        </Link>
                    </div>
                </header>

                {/* Hero */}
                <section className="bg-gradient-to-br from-[#184f95] via-[#2a78d6] to-[#3987e5] py-16 sm:py-20">
                    <div className="mx-auto max-w-5xl px-5 text-center sm:px-8">
                        <h1 className="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                            Bergabung dengan Tim Kami
                        </h1>
                        <p className="mx-auto mt-4 max-w-2xl text-base text-blue-100">
                            Temukan peluang karier yang sesuai dengan keahlian
                            dan minat Anda. Kami membuka kesempatan untuk
                            karyawan, mitra, dan freelancer.
                        </p>
                    </div>
                </section>

                {/* Filter & Content */}
                <main className="mx-auto w-full max-w-5xl flex-1 px-5 py-8 sm:px-8">
                    {/* Category filter */}
                    <div className="mb-6 flex flex-wrap gap-2">
                        {[
                            { value: "", label: "Semua" },
                            { value: "pkwt", label: "Full-time PKWT" },
                            { value: "probation", label: "Probation Track" },
                            { value: "mitra", label: "Mitra / Freelance" },
                        ].map((opt) => (
                            <button
                                key={opt.value}
                                type="button"
                                onClick={() => filterByCategory(opt.value)}
                                className={`rounded-full px-4 py-2 text-xs font-medium transition ${
                                    (category || "") === opt.value
                                        ? "bg-[#2a78d6] text-white"
                                        : "bg-white text-[#55677d] border border-[#e2ecf8] hover:bg-[#f0f6fe]"
                                }`}
                            >
                                {opt.label}
                            </button>
                        ))}
                    </div>

                    {/* Vacancy listing */}
                    {vacancies.length === 0 ? (
                        <div className="rounded-2xl border border-[#e2ecf8] bg-white py-16 text-center">
                            <p className="text-sm text-[#8fa1b6]">
                                Belum ada lowongan terbuka untuk kategori ini.
                            </p>
                        </div>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2">
                            {vacancies.map((vacancy) => (
                                <Link
                                    key={vacancy.id}
                                    href={`/karier/${vacancy.id}`}
                                    className="group rounded-2xl border border-[#e2ecf8] bg-white p-6 transition hover:border-[#3987e5] hover:shadow-lg hover:shadow-blue-500/5"
                                >
                                    <div className="mb-3 flex items-start justify-between gap-3">
                                        <h2 className="text-[15px] font-semibold text-[#0d1b2a] group-hover:text-[#2a78d6] transition">
                                            {vacancy.title}
                                        </h2>
                                        <span
                                            className={`shrink-0 rounded-md px-2 py-0.5 text-[11px] font-medium whitespace-nowrap ${
                                                CATEGORY_COLORS[
                                                    vacancy.category
                                                ] ?? "bg-gray-100 text-gray-700"
                                            }`}
                                        >
                                            {vacancy.categoryLabel}
                                        </span>
                                    </div>

                                    <div className="space-y-1.5 text-xs text-[#55677d]">
                                        {vacancy.department && (
                                            <p className="flex items-center gap-1.5">
                                                <svg className="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"><rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" /></svg>
                                                {vacancy.department}
                                            </p>
                                        )}
                                        {vacancy.location && (
                                            <p className="flex items-center gap-1.5">
                                                <svg className="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" /><circle cx="12" cy="9" r="2.5" /></svg>
                                                {vacancy.location}
                                            </p>
                                        )}
                                        <p className="flex items-center gap-1.5">
                                            <svg className="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"><path d="M16 19v-1.5a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4V19" /><circle cx="9" cy="7" r="3.2" /><path d="M22 19v-1.5a4 4 0 0 0-3-3.87" /><path d="M16 4.13a4 4 0 0 1 0 5.74" /></svg>
                                            {vacancy.applicantCount} pelamar · {vacancy.quota} posisi
                                        </p>
                                    </div>

                                    {vacancy.publishedAt && (
                                        <p className="mt-3 text-[11px] text-[#8fa1b6]">
                                            Dipublikasi {vacancy.publishedAt}
                                        </p>
                                    )}
                                </Link>
                            ))}
                        </div>
                    )}
                </main>

                {/* Footer */}
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
