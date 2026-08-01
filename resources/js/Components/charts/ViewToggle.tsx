type Props = {
    view: "chart" | "table";
    onChange: (view: "chart" | "table") => void;
};

/**
 * Setiap grafik punya kembaran tabel — nilai tidak pernah hanya bisa
 * dibaca lewat warna atau tooltip.
 */
export default function ViewToggle({ view, onChange }: Props) {
    return (
        <div
            role="group"
            aria-label="Tampilan data"
            className="inline-flex rounded-lg border border-hairline p-0.5"
        >
            {(["chart", "table"] as const).map((option) => (
                <button
                    key={option}
                    type="button"
                    aria-pressed={view === option}
                    onClick={() => onChange(option)}
                    className={`rounded-md px-2.5 py-1 text-[11px] font-medium transition ${
                        view === option
                            ? "bg-brand-50 text-brand-700"
                            : "text-ink-muted hover:text-ink-soft"
                    }`}
                >
                    {option === "chart" ? "Grafik" : "Tabel"}
                </button>
            ))}
        </div>
    );
}
