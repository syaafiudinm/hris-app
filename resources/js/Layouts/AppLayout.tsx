import { Link, router, usePage } from "@inertiajs/react";
import { useState, type ReactNode } from "react";
import FlashMessages from "@/Components/FlashMessages";
import {
    IconBook,
    IconClock,
    IconFunnel,
    IconGrid,
    IconUsers,
    IconWallet,
} from "@/Components/Icons";
import { ROLE_LABELS, type PageProps } from "@/types";

type NavItem = {
    label: string;
    href?: string;
    icon: (props: { className?: string }) => ReactNode;
    /** Role yang boleh melihat menu. Kosong = semua role. */
    roles?: string[];
    hint?: string;
};

type NavGroup = {
    heading: string;
    items: NavItem[];
};

const navigation: NavGroup[] = [
    {
        heading: "Ringkasan",
        items: [{ label: "Dashboard", href: "/dashboard", icon: IconGrid }],
    },
    {
        heading: "Portal Saya",
        items: [
            { label: "Absensi Saya", href: "/absensi-saya", icon: IconClock },
            { label: "Cuti & Izin Saya", href: "/cuti-saya", icon: IconBook },
            { label: "Slip Gaji Saya", href: "/slip-gaji-saya", icon: IconWallet },
            {
                label: "Knowledge Center",
                href: "/knowledge",
                icon: IconBook,
            },
        ],
    },
    {
        heading: "Manajemen",
        items: [
            {
                label: "Tenaga Kerja",
                href: "/employees",
                icon: IconUsers,
                roles: ["super_admin"],
            },
            {
                label: "Entitas Kerja",
                href: "/entitas-kerja",
                icon: IconGrid,
                roles: ["super_admin"],
            },
            {
                label: "Proses Keluar",
                href: "/proses-keluar",
                icon: IconUsers,
                roles: ["super_admin"],
            },
            {
                label: "Rekap Absensi",
                href: "/absensi",
                icon: IconClock,
                roles: ["super_admin", "manager"],
            },
            {
                label: "Approval Cuti",
                href: "/cuti",
                icon: IconBook,
                roles: ["super_admin", "manager"],
            },
            {
                label: "Payroll",
                href: "/payroll",
                icon: IconWallet,
                roles: ["super_admin"],
            },
            {
                label: "Skema Mitra",
                href: "/skema-mitra",
                icon: IconWallet,
                roles: ["super_admin"],
            },
            {
                label: "Lowongan",
                href: "/lowongan",
                icon: IconFunnel,
                roles: ["super_admin"],
            },
            {
                label: "Rekrutmen (ATS)",
                href: "/rekrutmen",
                icon: IconFunnel,
                roles: ["super_admin"],
            },
            {
                label: "Kelola Knowledge",
                href: "/knowledge/kelola",
                icon: IconBook,
                roles: ["super_admin"],
            },
        ],
    },
];

type Props = {
    title: string;
    subtitle?: string;
    actions?: ReactNode;
    children: ReactNode;
};

export default function AppLayout({
    title,
    subtitle,
    actions,
    children,
}: Props) {
    const { props, url: currentUrl } = usePage<PageProps>();
    const auth = props.auth;
    const [navOpen, setNavOpen] = useState(false);

    const role = auth?.user?.role ?? "employee";

    const visibleGroups = navigation
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => !item.roles || item.roles.includes(role),
            ),
        }))
        .filter((group) => group.items.length > 0);

    // Menu yang paling spesifik yang menang, supaya membuka
    // /knowledge/kelola tidak ikut menyorot /knowledge.
    const activeHref = visibleGroups
        .flatMap((group) => group.items)
        .map((item) => item.href)
        .filter((href): href is string => Boolean(href))
        .filter(
            (href) => currentUrl === href || currentUrl.startsWith(`${href}/`),
        )
        .sort((a, b) => b.length - a.length)[0];

    function isActive(href: string): boolean {
        return href === activeHref;
    }

    return (
        <div className="min-h-screen bg-plane">
            <aside
                className={`fixed inset-y-0 left-0 z-40 w-64 overflow-y-auto border-r border-hairline bg-surface transition-transform lg:translate-x-0 ${
                    navOpen ? "translate-x-0" : "-translate-x-full"
                }`}
            >
                <div className="flex h-16 items-center gap-2.5 px-5">
                    <span className="grid h-8 w-8 place-items-center rounded-lg bg-brand-500 text-sm font-semibold text-white">
                        H
                    </span>
                    <div className="leading-tight">
                        <p className="text-sm font-semibold text-ink">
                            HRIS &amp; ATS
                        </p>
                        <p className="text-[11px] text-ink-muted">
                            Workforce Platform
                        </p>
                    </div>
                </div>

                <nav className="px-3 pb-32">
                    {visibleGroups.map((group) => (
                        <div key={group.heading} className="mb-3">
                            <p className="px-2 pb-1.5 text-[11px] font-medium tracking-wider text-ink-muted uppercase">
                                {group.heading}
                            </p>
                            <ul className="space-y-0.5">
                                {group.items.map((item) => {
                                    const Icon = item.icon;

                                    if (!item.href) {
                                        return (
                                            <li key={item.label}>
                                                <span
                                                    title={item.hint}
                                                    aria-disabled="true"
                                                    className="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-ink-muted"
                                                >
                                                    <Icon className="h-[18px] w-[18px]" />
                                                    {item.label}
                                                </span>
                                            </li>
                                        );
                                    }

                                    const active = isActive(item.href);

                                    return (
                                        <li key={item.label}>
                                            <Link
                                                href={item.href}
                                                onClick={() => setNavOpen(false)}
                                                aria-current={
                                                    active ? "page" : undefined
                                                }
                                                className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition ${
                                                    active
                                                        ? "bg-brand-50 font-medium text-brand-700"
                                                        : "text-ink-soft hover:bg-surface-soft hover:text-ink"
                                                }`}
                                            >
                                                <Icon className="h-[18px] w-[18px]" />
                                                {item.label}
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    ))}
                </nav>

                <div className="absolute inset-x-3 bottom-4 rounded-xl bg-surface-soft px-4 py-3">
                    <p className="truncate text-xs font-medium text-ink">
                        {auth?.user?.name ?? "Tamu"}
                    </p>
                    <p className="text-[11px] text-ink-muted">
                        {auth?.user ? ROLE_LABELS[auth.user.role] : "-"}
                    </p>
                    <button
                        type="button"
                        onClick={() => router.post("/logout")}
                        className="mt-2 text-[11px] font-medium text-brand-600 transition hover:text-brand-700"
                    >
                        Keluar
                    </button>
                </div>
            </aside>

            {navOpen && (
                <button
                    type="button"
                    aria-label="Tutup navigasi"
                    onClick={() => setNavOpen(false)}
                    className="fixed inset-0 z-30 bg-ink/20 lg:hidden"
                />
            )}

            <div className="lg:pl-64">
                <header className="sticky top-0 z-20 border-b border-hairline bg-surface/85 backdrop-blur">
                    <div className="flex flex-wrap items-center gap-3 px-5 py-4 sm:px-8">
                        <button
                            type="button"
                            onClick={() => setNavOpen(true)}
                            aria-label="Buka navigasi"
                            className="grid h-9 w-9 place-items-center rounded-lg border border-hairline text-ink-soft lg:hidden"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                className="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth={1.8}
                                strokeLinecap="round"
                            >
                                <path d="M4 7h16M4 12h16M4 17h16" />
                            </svg>
                        </button>

                        <div className="mr-auto">
                            <h1 className="text-lg font-semibold tracking-tight text-ink">
                                {title}
                            </h1>
                            {subtitle && (
                                <p className="text-xs text-ink-soft">
                                    {subtitle}
                                </p>
                            )}
                        </div>

                        {actions}
                    </div>
                </header>

                <main className="px-5 py-6 sm:px-8">
                    <FlashMessages />
                    {children}
                </main>
            </div>
        </div>
    );
}
