import { useEffect, useRef, useState } from "react";
import { IconDownload } from "@/Components/Icons";

export type ExportTarget = {
    label: string;
    url: string;
    /** Beberapa laporan (mis. file transfer bank) hanya masuk akal satu format. */
    formats?: ("xlsx" | "csv" | "pdf")[];
};

const FORMAT_LABELS = {
    xlsx: "Excel (.xlsx)",
    csv: "CSV",
    pdf: "PDF",
} as const;

/**
 * Tombol Export Data standar. Filter aktif halaman ikut terkirim sehingga
 * berkas yang diunduh sama persis dengan yang tampil di layar.
 */
export default function ExportMenu({
    targets,
    params = {},
}: {
    targets: ExportTarget[];
    params?: Record<string, string | number | boolean | null | undefined>;
}) {
    const [open, setOpen] = useState(false);
    const container = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;

        function onPointerDown(event: MouseEvent) {
            if (!container.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        }

        function onKeyDown(event: KeyboardEvent) {
            if (event.key === "Escape") setOpen(false);
        }

        document.addEventListener("mousedown", onPointerDown);
        document.addEventListener("keydown", onKeyDown);

        return () => {
            document.removeEventListener("mousedown", onPointerDown);
            document.removeEventListener("keydown", onKeyDown);
        };
    }, [open]);

    function buildUrl(url: string, format: string): string {
        const query = new URLSearchParams({ format });

        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== "") {
                query.set(key, String(value));
            }
        });

        return `${url}?${query.toString()}`;
    }

    return (
        <div ref={container} className="relative">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-expanded={open}
                aria-haspopup="menu"
                className="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-3.5 py-2 text-xs font-medium text-white transition hover:bg-brand-600"
            >
                <IconDownload className="h-4 w-4" />
                Export data
            </button>

            {open && (
                <div
                    role="menu"
                    className="absolute right-0 z-30 mt-2 w-64 overflow-hidden rounded-xl border border-hairline bg-surface py-1 shadow-lg shadow-brand-700/5"
                >
                    {targets.map((target) => (
                        <div key={target.url} className="px-1 py-1">
                            <p className="px-3 py-1 text-[11px] font-medium text-ink-muted">
                                {target.label}
                            </p>
                            {(target.formats ?? ["xlsx", "csv", "pdf"]).map(
                                (format) => (
                                    <a
                                        key={format}
                                        role="menuitem"
                                        href={buildUrl(target.url, format)}
                                        onClick={() => setOpen(false)}
                                        className="block rounded-lg px-3 py-1.5 text-xs text-ink-soft transition hover:bg-surface-soft hover:text-ink"
                                    >
                                        {FORMAT_LABELS[format]}
                                    </a>
                                ),
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
