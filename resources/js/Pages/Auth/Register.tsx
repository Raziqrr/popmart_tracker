import { useForm } from '@inertiajs/react';
import { LoaderCircle, UserPlus } from 'lucide-react';
import { AppLayout } from '@/components/layout/AppLayout';
import { AuthField } from '@/components/auth/AuthField';

/** GET /register (Fortify registerView). Fortify's CreateNewUser action validates and creates the account. */
export default function Register() {
    const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

    return (
        <AppLayout title="Register">
            <form
                className="mx-auto flex w-full max-w-sm flex-col gap-4 border border-black/10 p-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') });
                }}
            >
                <h1 className="text-xl font-bold">Create an account</h1>

                <AuthField label="Name" autoComplete="name" value={form.data.name} onChange={(v) => form.setData('name', v)} error={form.errors.name} autoFocus />
                <AuthField label="Email" type="email" autoComplete="email" value={form.data.email} onChange={(v) => form.setData('email', v)} error={form.errors.email} />
                <AuthField label="Password" type="password" autoComplete="new-password" value={form.data.password} onChange={(v) => form.setData('password', v)} error={form.errors.password} />
                <AuthField
                    label="Confirm password"
                    type="password"
                    autoComplete="new-password"
                    value={form.data.password_confirmation}
                    onChange={(v) => form.setData('password_confirmation', v)}
                    error={form.errors.password_confirmation}
                />

                <button type="submit" disabled={form.processing} className="inline-flex items-center justify-center gap-1.5 bg-black px-4 py-2 text-sm font-bold text-white hover:bg-black/80 disabled:bg-black/30">
                    {form.processing ? <LoaderCircle aria-hidden="true" className="size-4 animate-spin" /> : <UserPlus aria-hidden="true" className="size-4" />}
                    Create account
                </button>

                <p className="text-center text-xs">
                    Already have one?{' '}
                    <a href="/login" className="font-bold underline">
                        Log in
                    </a>
                </p>
            </form>
        </AppLayout>
    );
}
