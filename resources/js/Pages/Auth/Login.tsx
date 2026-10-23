import { useForm } from '@inertiajs/react';
import { LoaderCircle, LogIn } from 'lucide-react';
import { AppLayout } from '@/components/layout/AppLayout';
import { AuthField } from '@/components/auth/AuthField';

/** GET /login (Fortify loginView). Posting /login is handled by Fortify, which redirects home. */
export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });

    return (
        <AppLayout title="Log in">
            <form
                className="mx-auto flex w-full max-w-sm flex-col gap-4 border border-black/10 p-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/login', { onFinish: () => form.reset('password') });
                }}
            >
                <h1 className="text-xl font-bold">Log in</h1>
                <p className="text-xs text-black/60">Your Popmart Tracker account, not your Pop Mart one. You connect Pop Mart after logging in.</p>

                <AuthField label="Email" type="email" autoComplete="username" value={form.data.email} onChange={(v) => form.setData('email', v)} error={form.errors.email} autoFocus />
                <AuthField label="Password" type="password" autoComplete="current-password" value={form.data.password} onChange={(v) => form.setData('password', v)} error={form.errors.password} />

                <label className="flex items-center gap-2 text-xs">
                    <input type="checkbox" checked={form.data.remember} onChange={(e) => form.setData('remember', e.target.checked)} className="accent-black" />
                    Keep me logged in
                </label>

                <button type="submit" disabled={form.processing} className="inline-flex items-center justify-center gap-1.5 bg-black px-4 py-2 text-sm font-bold text-white hover:bg-black/80 disabled:bg-black/30">
                    {form.processing ? <LoaderCircle aria-hidden="true" className="size-4 animate-spin" /> : <LogIn aria-hidden="true" className="size-4" />}
                    Log in
                </button>

                <p className="text-center text-xs">
                    No account?{' '}
                    <a href="/register" className="font-bold underline">
                        Register
                    </a>
                </p>
            </form>
        </AppLayout>
    );
}
