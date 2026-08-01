const numberFormatter = new Intl.NumberFormat("id-ID");

/** Rp 1.250.000 */
export function rupiah(value: number): string {
    return `Rp ${numberFormatter.format(Math.round(value))}`;
}

/** Bentuk ringkas untuk stat tile & sumbu: Rp 538,4 jt / Rp 1,2 M */
export function rupiahCompact(value: number): string {
    const abs = Math.abs(value);

    if (abs >= 1_000_000_000) {
        return `Rp ${trim(value / 1_000_000_000)} M`;
    }
    if (abs >= 1_000_000) {
        return `Rp ${trim(value / 1_000_000)} jt`;
    }
    if (abs >= 1_000) {
        return `Rp ${trim(value / 1_000)} rb`;
    }

    return `Rp ${numberFormatter.format(value)}`;
}

/** Ringkas tanpa prefix mata uang — dipakai pada tick sumbu. */
export function compact(value: number): string {
    const abs = Math.abs(value);

    if (abs >= 1_000_000_000) return `${trim(value / 1_000_000_000)} M`;
    if (abs >= 1_000_000) return `${trim(value / 1_000_000)} jt`;
    if (abs >= 1_000) return `${trim(value / 1_000)} rb`;

    return numberFormatter.format(value);
}

export function angka(value: number): string {
    return numberFormatter.format(value);
}

function trim(value: number): string {
    return value
        .toFixed(1)
        .replace(/\.0$/, "")
        .replace(".", ",");
}
