import { useLayoutEffect, useRef, type TableHTMLAttributes } from "react";

/**
 * Tabel yang di layar HP (< 640px) tampil sebagai tumpukan kartu: tiap
 * baris jadi satu kartu dan tiap sel diberi label kolomnya, sehingga tidak
 * perlu digeser ke samping. Di layar lebih besar tetap tabel biasa.
 *
 * Label diambil otomatis dari <th> di <thead> (lihat .table-stack di
 * app.css), jadi cukup mengganti <table> dengan <StackedTable>.
 */
export default function StackedTable({
    className = "",
    ...props
}: TableHTMLAttributes<HTMLTableElement>) {
    const ref = useRef<HTMLTableElement>(null);

    // Tanpa dependency: isi tabel bisa berubah di tiap render (filter,
    // paginasi), jadi label disegarkan setiap kali.
    useLayoutEffect(() => {
        const table = ref.current;
        if (!table) return;

        const labels = Array.from(
            table.querySelectorAll("thead tr:last-child > th"),
            (th) => th.textContent?.trim() ?? "",
        );

        table.querySelectorAll("tbody > tr").forEach((row) => {
            let column = 0;

            Array.from(row.children).forEach((cell) => {
                if (!(cell instanceof HTMLTableCellElement)) return;

                // Sel gabungan (colSpan) tidak mewakili satu kolom tertentu.
                cell.dataset.label =
                    cell.colSpan === 1 ? (labels[column] ?? "") : "";
                column += cell.colSpan;
            });
        });
    });

    return (
        <table ref={ref} className={`table-stack ${className}`} {...props} />
    );
}
