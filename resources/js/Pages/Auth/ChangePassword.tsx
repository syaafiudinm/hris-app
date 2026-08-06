import { Head, useForm, usePage } from "@inertiajs/react";
import AppLayout from "@/Layouts/AppLayout";
import Card from "@/Components/Card";
import { Button, Field, Input } from "@/Components/ui";

type PageProps = {
    mustChange: boolean;
    auth: {
        user: { name: string; email: string; role: string; mustChangePassword: boolean };
    };
};

export default function ChangePassword() {
    const { mustChange } = usePage<PageProps>().props;

    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: "",
        new_password: "",
        new_password_confirmation: "",
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        put("/ganti-password", {
            onSuccess: () => reset(),
        });
    }

    return (
        <AppLayout
            title="Ganti Password"
            subtitle={
                mustChange
                    ? "Anda wajib mengubah password sebelum melanjutkan"
                    : "Perbarui password akun Anda"
            }
        >
            <Head title="Ganti Password" />

            <div className="mx-auto max-w-lg">
                {mustChange && (
                    <div className="mb-5 rounded-xl border border-[#e8c34a]/30 bg-[#fdf6e6] px-5 py-4">
                        <p className="text-sm font-medium text-[#8a6100]">
                            ⚠️ Password Anda masih default
                        </p>
                        <p className="mt-1 text-xs text-[#8a6100]/80">
                            Demi keamanan akun, Anda wajib mengubah password
                            sebelum dapat mengakses fitur lain. Anda tidak perlu
                            memasukkan password lama.
                        </p>
                    </div>
                )}

                <Card title="Formulir Ganti Password">
                    <form onSubmit={submit} className="space-y-4">
                        {!mustChange && (
                            <Field
                                label="Password saat ini"
                                error={errors.current_password}
                                required
                            >
                                <Input
                                    type="password"
                                    autoComplete="current-password"
                                    value={data.current_password}
                                    onChange={(e) =>
                                        setData(
                                            "current_password",
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                        )}

                        <Field
                            label="Password baru"
                            error={errors.new_password}
                            required
                            hint="Minimal 8 karakter"
                        >
                            <Input
                                type="password"
                                autoComplete="new-password"
                                value={data.new_password}
                                onChange={(e) =>
                                    setData("new_password", e.target.value)
                                }
                            />
                        </Field>

                        <Field
                            label="Konfirmasi password baru"
                            error={errors.new_password_confirmation}
                            required
                        >
                            <Input
                                type="password"
                                autoComplete="new-password"
                                value={data.new_password_confirmation}
                                onChange={(e) =>
                                    setData(
                                        "new_password_confirmation",
                                        e.target.value,
                                    )
                                }
                            />
                        </Field>

                        <div className="pt-2">
                            <Button type="submit" disabled={processing}>
                                {processing
                                    ? "Menyimpan…"
                                    : "Simpan Password Baru"}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
