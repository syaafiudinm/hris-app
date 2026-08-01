import { usePage } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { IconAlert, IconCheck } from "@/Components/Icons";
import type { PageProps } from "@/types";

export default function FlashMessages() {
    const { flash } = usePage<PageProps>().props;
    const [dismissed, setDismissed] = useState<string | null>(null);

    const message = flash?.success ?? flash?.error ?? null;
    const isError = Boolean(flash?.error);

    useEffect(() => {
        setDismissed(null);
    }, [message]);

    if (!message || dismissed === message) return null;

    return (
        <div
            role="status"
            className="mb-5 flex items-start gap-2.5 rounded-xl border border-hairline bg-surface px-4 py-3"
            style={{
                borderLeftWidth: 3,
                borderLeftColor: isError ? "#d03b3b" : "#0ca30c",
            }}
        >
            <span
                className="mt-0.5 shrink-0"
                style={{ color: isError ? "#d03b3b" : "#0ca30c" }}
                aria-hidden
            >
                {isError ? (
                    <IconAlert className="h-4 w-4" />
                ) : (
                    <IconCheck className="h-4 w-4" />
                )}
            </span>
            <p className="flex-1 text-sm text-ink">{message}</p>
            <button
                type="button"
                onClick={() => setDismissed(message)}
                aria-label="Tutup pesan"
                className="text-ink-muted transition hover:text-ink"
            >
                <svg
                    viewBox="0 0 24 24"
                    className="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={1.8}
                    strokeLinecap="round"
                >
                    <path d="m6 6 12 12M18 6 6 18" />
                </svg>
            </button>
        </div>
    );
}
