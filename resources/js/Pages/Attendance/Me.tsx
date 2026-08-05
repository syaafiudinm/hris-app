import { Head, router } from "@inertiajs/react";
import { useEffect, useRef, useState, type ReactNode } from "react";
import Card from "@/Components/Card";
import AppLayout from "@/Layouts/AppLayout";
import { Badge, Button, Field, Textarea, statusTone } from "@/Components/ui";
import {
    IconAlert,
    IconCamera,
    IconCheck,
    IconClock,
    IconShield,
    IconUpload,
} from "@/Components/Icons";

type Office = {
    name: string;
    latitude: number;
    longitude: number;
    radius_meters: number;
};

type Props = {
    employee: {
        name: string;
        nik: string;
        type: string | null;
        category: string | null;
    };
    today: {
        date: string;
        clockIn: string | null;
        clockOut: string | null;
        status: string | null;
        lateMinutes: number;
        isFakeGps: boolean;
        workHours: number;
        method: string | null;
        methodLabel: string | null;
        verification: string | null;
        verificationLabel: string | null;
        verificationNote: string | null;
    };
    offices: Office[];
    history: {
        id: number;
        date: string;
        clockIn: string | null;
        clockOut: string | null;
        status: string;
        workHours: number;
        method: string;
        verification: string;
    }[];
};

type Mode = "live" | "upload";

type Position = {
    latitude: number;
    longitude: number;
    accuracy: number;
};

export default function AttendanceMe({
    employee,
    today,
    offices,
    history,
}: Props) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const fileRef = useRef<HTMLInputElement>(null);

    const [mode, setMode] = useState<Mode>("live");
    const [position, setPosition] = useState<Position | null>(null);
    const [geoError, setGeoError] = useState<string | null>(null);
    const [cameraOn, setCameraOn] = useState(false);
    const [cameraError, setCameraError] = useState<string | null>(null);
    const [photo, setPhoto] = useState<string | null>(null);
    const [upload, setUpload] = useState<{ file: File; preview: string } | null>(
        null,
    );
    const [note, setNote] = useState("");
    const [submitting, setSubmitting] = useState(false);

    // Hentikan kamera saat komponen dilepas agar indikator perangkat mati.
    useEffect(() => stopCamera, []);

    const nearest = position ? nearestOffice(offices, position) : null;
    const insideRadius = nearest
        ? nearest.distance <= nearest.office.radius_meters
        : false;

    // Mode kamera wajib di dalam radius; mode unggah tidak diblokir jarak,
    // tetapi wajib menyertakan alasan karena akan diverifikasi HR.
    const canClockIn = today.clockIn
        ? false
        : mode === "live"
          ? Boolean(position && photo && insideRadius)
          : Boolean(position && upload && note.trim());

    function requestLocation() {
        setGeoError(null);

        if (!navigator.geolocation) {
            setGeoError("Perangkat ini tidak mendukung geolokasi.");
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (result) =>
                setPosition({
                    latitude: result.coords.latitude,
                    longitude: result.coords.longitude,
                    accuracy: result.coords.accuracy,
                }),
            (error) =>
                setGeoError(
                    error.code === error.PERMISSION_DENIED
                        ? "Izin lokasi ditolak. Aktifkan agar dapat absen."
                        : "Lokasi tidak terbaca. Coba lagi di area terbuka.",
                ),
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
        );
    }

    async function startCamera() {
        setCameraError(null);

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: "user" },
                audio: false,
            });

            streamRef.current = stream;
            setCameraOn(true);

            if (videoRef.current) {
                videoRef.current.srcObject = stream;
                await videoRef.current.play();
            }
        } catch {
            setCameraError(
                "Kamera tidak dapat diakses. Periksa izin browser Anda.",
            );
        }
    }

    function stopCamera() {
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
        setCameraOn(false);
    }

    function capture() {
        const video = videoRef.current;
        if (!video) return;

        const canvas = document.createElement("canvas");
        canvas.width = video.videoWidth || 480;
        canvas.height = video.videoHeight || 360;

        const context = canvas.getContext("2d");
        if (!context) return;

        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        setPhoto(canvas.toDataURL("image/jpeg", 0.75));
        stopCamera();
    }

    function pickFile(file: File | null) {
        if (!file) {
            setUpload(null);
            return;
        }

        setUpload({ file, preview: URL.createObjectURL(file) });
    }

    function submitClockIn() {
        if (!position) return;

        setSubmitting(true);

        const shared = {
            method: mode,
            latitude: position.latitude,
            longitude: position.longitude,
            accuracy: position.accuracy,
            // Browser tidak mengekspos mock provider; server tetap
            // menjalankan heuristik lain (akurasi, koordinat, kecepatan).
            is_mock_location: false,
        };

        router.post(
            "/absensi-saya/clock-in",
            mode === "live"
                ? { ...shared, photo }
                : { ...shared, photo_file: upload?.file, note },
            {
                // Berkas unggahan memaksa multipart; Inertia menanganinya
                // otomatis begitu ada File di payload.
                forceFormData: mode === "upload",
                onFinish: () => {
                    setSubmitting(false);
                    setPhoto(null);
                    setUpload(null);
                    setNote("");
                    if (fileRef.current) fileRef.current.value = "";
                },
            },
        );
    }

    return (
        <AppLayout
            title="Absensi Saya"
            subtitle={today.date}
            actions={
                today.clockIn && !today.clockOut ? (
                    <Button
                        onClick={() => router.post("/absensi-saya/clock-out")}
                    >
                        Clock out
                    </Button>
                ) : undefined
            }
        >
            <Head title="Absensi Saya" />

            <div className="grid gap-5 xl:grid-cols-3">
                <div className="space-y-5 xl:col-span-2">
                    <Card title="Status hari ini">
                        <div className="grid gap-4 sm:grid-cols-4">
                            <Metric
                                label="Clock in"
                                value={today.clockIn ?? "—"}
                            />
                            <Metric
                                label="Clock out"
                                value={today.clockOut ?? "—"}
                            />
                            <Metric
                                label="Jam kerja"
                                value={
                                    today.workHours > 0
                                        ? `${today.workHours} jam`
                                        : "—"
                                }
                            />
                            <div>
                                <p className="text-[11px] text-ink-muted">
                                    Status
                                </p>
                                <div className="mt-1">
                                    {today.status ? (
                                        <Badge
                                            tone={
                                                statusTone[today.status] ??
                                                "neutral"
                                            }
                                        >
                                            {today.status}
                                        </Badge>
                                    ) : (
                                        <span className="text-sm text-ink-muted">
                                            Belum absen
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>

                        {today.lateMinutes > 0 && (
                            <p className="mt-4 flex items-center gap-1.5 text-xs text-[#8a6100]">
                                <IconClock className="h-3.5 w-3.5" />
                                Terlambat {today.lateMinutes} menit dari jam
                                masuk 08:00.
                            </p>
                        )}

                        {today.isFakeGps && (
                            <p className="mt-2 flex items-center gap-1.5 text-xs text-[#b53232]">
                                <IconShield className="h-3.5 w-3.5" />
                                Absensi ini ditandai untuk verifikasi HR karena
                                indikasi lokasi palsu.
                            </p>
                        )}

                        {today.clockIn && today.methodLabel && (
                            <p className="mt-3 flex flex-wrap items-center gap-1.5 border-t border-hairline pt-3 text-[11px] text-ink-muted">
                                Metode:
                                <Badge tone="neutral">
                                    {today.methodLabel}
                                </Badge>
                                {today.verification &&
                                    today.verification !== "auto" && (
                                        <Badge
                                            tone={
                                                today.verification ===
                                                "approved"
                                                    ? "good"
                                                    : today.verification ===
                                                        "rejected"
                                                      ? "critical"
                                                      : "warning"
                                            }
                                        >
                                            {today.verificationLabel}
                                        </Badge>
                                    )}
                                {today.verificationNote && (
                                    <span className="basis-full text-ink-soft">
                                        Catatan HR: {today.verificationNote}
                                    </span>
                                )}
                            </p>
                        )}
                    </Card>

                    {!today.clockIn && (
                        <Card
                            title="Clock in"
                            subtitle="Pilih cara absen yang sesuai dengan kondisi Anda hari ini"
                        >
                            {/* Dua opsi absensi. */}
                            <div className="mb-5 grid gap-2 sm:grid-cols-2">
                                <ModeCard
                                    active={mode === "live"}
                                    icon={<IconCamera className="h-4 w-4" />}
                                    title="Kamera langsung"
                                    description="Selfie diambil saat itu juga. Wajib berada di dalam radius kantor, langsung sah tanpa persetujuan."
                                    onClick={() => setMode("live")}
                                />
                                <ModeCard
                                    active={mode === "upload"}
                                    icon={<IconUpload className="h-4 w-4" />}
                                    title="Unggah foto"
                                    description="Untuk kerja lapangan atau saat kamera tidak bisa dipakai. Boleh di luar radius, tetapi diverifikasi HR dulu."
                                    onClick={() => setMode("upload")}
                                />
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                {/* Langkah 1 — lokasi */}
                                <div>
                                    <p className="mb-2 text-xs font-medium text-ink">
                                        1. Verifikasi lokasi
                                    </p>

                                    {position ? (
                                        <div className="rounded-xl border border-hairline bg-surface-soft p-3">
                                            <p className="tabular text-xs text-ink">
                                                {position.latitude.toFixed(6)},{" "}
                                                {position.longitude.toFixed(6)}
                                            </p>
                                            <p className="mt-1 text-[11px] text-ink-muted">
                                                Akurasi ±
                                                {Math.round(position.accuracy)} m
                                            </p>
                                            {nearest && (
                                                <p
                                                    className="mt-2 flex items-center gap-1.5 text-[11px] font-medium"
                                                    style={{
                                                        color: insideRadius
                                                            ? "#0a7a0a"
                                                            : mode === "upload"
                                                              ? "#8a6100"
                                                              : "#b53232",
                                                    }}
                                                >
                                                    {insideRadius ? (
                                                        <IconCheck className="h-3.5 w-3.5" />
                                                    ) : (
                                                        <IconAlert className="h-3.5 w-3.5" />
                                                    )}
                                                    {Math.round(nearest.distance)} m
                                                    dari {nearest.office.name}
                                                    {insideRadius
                                                        ? " (dalam radius)"
                                                        : ` (radius ${nearest.office.radius_meters} m)`}
                                                </p>
                                            )}
                                        </div>
                                    ) : (
                                        <p className="text-xs text-ink-muted">
                                            Lokasi belum diambil.
                                        </p>
                                    )}

                                    {geoError && (
                                        <p className="mt-2 text-[11px] text-[#b53232]">
                                            {geoError}
                                        </p>
                                    )}

                                    <Button
                                        variant="secondary"
                                        onClick={requestLocation}
                                        className="mt-3"
                                    >
                                        {position
                                            ? "Perbarui lokasi"
                                            : "Ambil lokasi"}
                                    </Button>
                                </div>

                                {/* Langkah 2 — foto, sesuai opsi yang dipilih */}
                                <div>
                                    <p className="mb-2 text-xs font-medium text-ink">
                                        2.{" "}
                                        {mode === "live"
                                            ? "Foto selfie"
                                            : "Unggah foto"}
                                    </p>

                                    <div className="aspect-4/3 overflow-hidden rounded-xl border border-hairline bg-surface-soft">
                                        {mode === "live" ? (
                                            <>
                                                {photo ? (
                                                    <img
                                                        src={photo}
                                                        alt="Pratinjau selfie absensi"
                                                        className="h-full w-full object-cover"
                                                    />
                                                ) : (
                                                    <video
                                                        ref={videoRef}
                                                        playsInline
                                                        muted
                                                        className={`h-full w-full object-cover ${cameraOn ? "" : "hidden"}`}
                                                    />
                                                )}
                                                {!photo && !cameraOn && (
                                                    <div className="grid h-full place-items-center text-[11px] text-ink-muted">
                                                        Kamera belum aktif
                                                    </div>
                                                )}
                                            </>
                                        ) : upload ? (
                                            <img
                                                src={upload.preview}
                                                alt="Pratinjau foto yang diunggah"
                                                className="h-full w-full object-cover"
                                            />
                                        ) : (
                                            <div className="grid h-full place-items-center px-4 text-center text-[11px] text-ink-muted">
                                                Belum ada berkas dipilih
                                                <br />
                                                (JPG/PNG, maksimal 5 MB)
                                            </div>
                                        )}
                                    </div>

                                    {mode === "live" && cameraError && (
                                        <p className="mt-2 text-[11px] text-[#b53232]">
                                            {cameraError}
                                        </p>
                                    )}

                                    <div className="mt-3 flex gap-2">
                                        {mode === "live" ? (
                                            photo ? (
                                                <Button
                                                    variant="secondary"
                                                    onClick={() => {
                                                        setPhoto(null);
                                                        startCamera();
                                                    }}
                                                >
                                                    Ulangi foto
                                                </Button>
                                            ) : cameraOn ? (
                                                <Button onClick={capture}>
                                                    Ambil foto
                                                </Button>
                                            ) : (
                                                <Button
                                                    variant="secondary"
                                                    onClick={startCamera}
                                                >
                                                    Nyalakan kamera
                                                </Button>
                                            )
                                        ) : (
                                            <>
                                                <input
                                                    ref={fileRef}
                                                    type="file"
                                                    accept="image/*"
                                                    capture="user"
                                                    className="hidden"
                                                    onChange={(event) =>
                                                        pickFile(
                                                            event.target
                                                                .files?.[0] ??
                                                                null,
                                                        )
                                                    }
                                                />
                                                <Button
                                                    variant="secondary"
                                                    onClick={() =>
                                                        fileRef.current?.click()
                                                    }
                                                >
                                                    {upload
                                                        ? "Ganti berkas"
                                                        : "Pilih foto"}
                                                </Button>
                                                {upload && (
                                                    <span className="self-center truncate text-[11px] text-ink-muted">
                                                        {upload.file.name}
                                                    </span>
                                                )}
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {mode === "upload" && (
                                <div className="mt-5">
                                    <Field
                                        label="3. Alasan absen dari luar / unggah foto"
                                        required
                                        hint="Ditampilkan ke HR saat memverifikasi. Contoh: kunjungan klien di Gowa, kamera ponsel bermasalah."
                                    >
                                        <Textarea
                                            rows={2}
                                            value={note}
                                            onChange={(event) =>
                                                setNote(event.target.value)
                                            }
                                        />
                                    </Field>
                                </div>
                            )}

                            <div className="mt-5 border-t border-hairline pt-4">
                                <Button
                                    onClick={submitClockIn}
                                    disabled={!canClockIn || submitting}
                                >
                                    {submitting
                                        ? "Mengirim…"
                                        : mode === "live"
                                          ? "Kirim clock in"
                                          : "Kirim untuk verifikasi"}
                                </Button>
                                {!canClockIn && (
                                    <p className="mt-2 text-[11px] text-ink-muted">
                                        {!position
                                            ? "Ambil lokasi terlebih dahulu."
                                            : mode === "live"
                                              ? !insideRadius
                                                  ? "Anda di luar radius kantor — pindah ke opsi unggah foto bila memang bekerja di lapangan."
                                                  : !photo
                                                    ? "Foto selfie belum diambil."
                                                    : ""
                                              : !upload
                                                ? "Pilih berkas foto terlebih dahulu."
                                                : "Isi alasannya agar HR dapat memverifikasi."}
                                    </p>
                                )}
                                {mode === "upload" && canClockIn && (
                                    <p className="mt-2 text-[11px] text-ink-muted">
                                        Absensi tercatat hari ini, namun baru
                                        dihitung setelah HR menyetujuinya.
                                    </p>
                                )}
                            </div>
                        </Card>
                    )}
                </div>

                <div className="space-y-5">
                    <Card title="Identitas">
                        <dl className="space-y-3">
                            <div>
                                <dt className="text-[11px] text-ink-muted">
                                    Nama
                                </dt>
                                <dd className="text-sm text-ink">
                                    {employee.name}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-[11px] text-ink-muted">
                                    NIK
                                </dt>
                                <dd className="tabular text-sm text-ink">
                                    {employee.nik}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-[11px] text-ink-muted">
                                    Entitas kerja
                                </dt>
                                <dd className="mt-0.5">
                                    <Badge tone="brand">{employee.type}</Badge>
                                </dd>
                            </div>
                        </dl>

                        {employee.category === "mitra" && (
                            <p className="mt-4 border-t border-hairline pt-3 text-[11px] text-ink-soft">
                                Sebagai mitra, catatan jam kerja Anda menjadi
                                dasar perhitungan pembayaran hourly/daily rate.
                            </p>
                        )}
                    </Card>

                    <Card title="10 absensi terakhir">
                        {history.length === 0 ? (
                            <p className="py-4 text-center text-xs text-ink-muted">
                                Belum ada riwayat.
                            </p>
                        ) : (
                            <ul className="space-y-2.5">
                                {history.map((row) => (
                                    <li
                                        key={row.id}
                                        className="flex items-center justify-between gap-3 text-xs"
                                    >
                                        <span className="tabular text-ink-soft">
                                            {row.date}
                                        </span>
                                        <span className="tabular text-ink-muted">
                                            {row.clockIn ?? "—"} –{" "}
                                            {row.clockOut ?? "—"}
                                        </span>
                                        <span className="flex items-center gap-1">
                                            {row.verification === "pending" && (
                                                <Badge tone="warning">
                                                    verifikasi
                                                </Badge>
                                            )}
                                            <Badge
                                                tone={
                                                    statusTone[row.status] ??
                                                    "neutral"
                                                }
                                            >
                                                {row.status}
                                            </Badge>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

function ModeCard({
    active,
    icon,
    title,
    description,
    onClick,
}: {
    active: boolean;
    icon: ReactNode;
    title: string;
    description: string;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={`rounded-xl border p-3.5 text-left transition ${
                active
                    ? "border-brand-400 bg-brand-50"
                    : "border-hairline bg-surface hover:bg-surface-soft"
            }`}
        >
            <span
                className={`flex items-center gap-2 text-xs font-medium ${
                    active ? "text-brand-700" : "text-ink"
                }`}
            >
                {icon}
                {title}
            </span>
            <span className="mt-1.5 block text-[11px] leading-relaxed text-ink-muted">
                {description}
            </span>
        </button>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-[11px] text-ink-muted">{label}</p>
            <p className="tabular mt-1 text-lg font-semibold text-ink">
                {value}
            </p>
        </div>
    );
}

/** Jarak haversine (meter) — cerminan perhitungan geofence di server. */
function distanceMeters(
    lat1: number,
    long1: number,
    lat2: number,
    long2: number,
): number {
    const toRad = (value: number) => (value * Math.PI) / 180;
    const deltaLat = toRad(lat2 - lat1);
    const deltaLong = toRad(long2 - long1);

    const a =
        Math.sin(deltaLat / 2) ** 2 +
        Math.cos(toRad(lat1)) *
            Math.cos(toRad(lat2)) *
            Math.sin(deltaLong / 2) ** 2;

    return 6371000 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function nearestOffice(
    offices: Office[],
    position: Position,
): { office: Office; distance: number } | null {
    if (offices.length === 0) return null;

    return offices
        .map((office) => ({
            office,
            distance: distanceMeters(
                position.latitude,
                position.longitude,
                Number(office.latitude),
                Number(office.longitude),
            ),
        }))
        .sort((a, b) => a.distance - b.distance)[0];
}
