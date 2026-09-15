import { router } from "@inertiajs/react";
import { useState } from "react";
import { Badge, Button } from "@/Components/ui";
import { IconCheck, IconUpload } from "@/Components/Icons";

export type DocumentSlot = {
    type: string;
    label: string;
    required: boolean;
    hint: string | null;
    accept: string;
    formats: string;
    maxSize: string;
    maxKb: number;
    document: {
        id: number;
        name: string;
        size: string;
        uploadedAt: string | null;
        url: string;
    } | null;
};

type Props = {
    slots: DocumentSlot[];
    /** Endpoint unggah — milik sendiri atau atas nama karyawan (HR). */
    uploadUrl: string;
};

/**
 * Daftar slot dokumen kelengkapan. Setiap slot diunggah sendiri-sendiri
 * begitu berkas dipilih, supaya satu berkas yang ditolak tidak
 * menggagalkan unggahan lain.
 */
export default function EmployeeDocuments({ slots, uploadUrl }: Props) {
    const [uploading, setUploading] = useState<string | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});

    function upload(slot: DocumentSlot, file: File) {
        const type = slot.type;

        // Cek ukuran lebih dulu supaya berkas besar tidak sempat dikirim.
        if (file.size > slot.maxKb * 1024) {
            setErrors((current) => ({
                ...current,
                [type]: `Ukuran berkas maksimal ${slot.maxSize}.`,
            }));
            return;
        }

        setUploading(type);
        setErrors((current) => ({ ...current, [type]: "" }));

        router.post(
            uploadUrl,
            { type, file },
            {
                forceFormData: true,
                preserveScroll: true,
                // Jangan buang isian form lain di halaman yang belum disimpan.
                preserveState: true,
                onError: (bag) =>
                    setErrors((current) => ({
                        ...current,
                        [type]: bag.file ?? bag.type ?? "Gagal mengunggah.",
                    })),
                onFinish: () => setUploading(null),
            },
        );
    }

    function remove(slot: DocumentSlot) {
        if (!slot.document) return;
        if (!confirm(`Hapus dokumen ${slot.label}?`)) return;

        router.delete(`/dokumen-karyawan/${slot.document.id}`, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    return (
        <ul className="divide-y divide-hairline">
            {slots.map((slot) => {
                const inputId = `document-${slot.type}`;
                const busy = uploading === slot.type;

                return (
                    <li
                        key={slot.type}
                        className="flex flex-wrap items-start gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <span
                            className={`mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full ${
                                slot.document
                                    ? "bg-[#effaef] text-[#0a7a0a]"
                                    : "bg-surface-soft text-ink-muted"
                            }`}
                            aria-hidden
                        >
                            {slot.document ? (
                                <IconCheck className="h-3.5 w-3.5" />
                            ) : (
                                <IconUpload className="h-3.5 w-3.5" />
                            )}
                        </span>

                        <div className="min-w-0 flex-1">
                            <p className="flex flex-wrap items-center gap-1.5 text-sm font-medium text-ink">
                                {slot.label}
                                {slot.required ? (
                                    <span className="text-[#d03b3b]">*</span>
                                ) : (
                                    <Badge>opsional</Badge>
                                )}
                            </p>
                            <p className="mt-0.5 text-[11px] text-ink-muted">
                                {slot.hint ? `${slot.hint} ` : ""}
                                {slot.formats}, maks. {slot.maxSize}.
                            </p>

                            {slot.document && (
                                <p className="mt-1 truncate text-xs text-ink-soft">
                                    <a
                                        href={slot.document.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="font-medium text-brand-600 hover:text-brand-700"
                                    >
                                        {slot.document.name}
                                    </a>
                                    <span className="text-ink-muted">
                                        {" "}
                                        · {slot.document.size}
                                        {slot.document.uploadedAt
                                            ? ` · ${slot.document.uploadedAt}`
                                            : ""}
                                    </span>
                                </p>
                            )}

                            {errors[slot.type] && (
                                <p className="mt-1 text-[11px] text-[#d03b3b]">
                                    {errors[slot.type]}
                                </p>
                            )}
                        </div>

                        <div className="flex shrink-0 items-center gap-1.5">
                            <input
                                id={inputId}
                                type="file"
                                accept={slot.accept}
                                className="hidden"
                                onChange={(event) => {
                                    const file = event.target.files?.[0];
                                    event.target.value = "";
                                    if (file) upload(slot, file);
                                }}
                            />
                            <Button
                                type="button"
                                size="sm"
                                variant={slot.document ? "ghost" : "secondary"}
                                disabled={uploading !== null}
                                onClick={() =>
                                    document.getElementById(inputId)?.click()
                                }
                            >
                                {busy
                                    ? "Mengunggah…"
                                    : slot.document
                                      ? "Ganti"
                                      : "Unggah"}
                            </Button>
                            {slot.document && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    className="text-[#d03b3b]"
                                    disabled={uploading !== null}
                                    onClick={() => remove(slot)}
                                >
                                    Hapus
                                </Button>
                            )}
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
