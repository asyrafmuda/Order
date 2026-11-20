import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus, Search, Users } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { ActiveBadge } from '@/components/admin/ActiveBadge';
import { RowActions } from '@/components/admin/RowActions';
import { useFilters } from '@/components/admin/useFilters';
import { Badge, type Tone } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { Input, Select, Toggle } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { Table, Td, Th } from '@/components/ui/Table';
import BackOfficeLayout from '@/layouts/BackOfficeLayout';
import { date } from '@/lib/format';
import type { Option, Paginated, Role } from '@/types';

interface UserRow {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    role: Role;
    role_label: string;
    is_active: boolean;
    outlet_id: number | null;
    outlet_name: string | null;
    orders_count: number;
    created_at: string | null;
}

interface UsersProps {
    users: Paginated<UserRow>;
    filters: { search: string; role: string };
    roles: Option<Role>[];
    outlets: { id: number; name: string; is_active: boolean; is_default: boolean }[];
    /** Multi-outlet is on: staff are assigned to an outlet. */
    multi: boolean;
}

const roleTones: Record<Role, Tone> = {
    admin: 'solid-brand',
    manager: 'solid-flame',
    cashier: 'flame',
    waiter: 'flame',
    kitchen: 'blue',
    rider: 'green',
    customer: 'neutral',
};

export default function UsersIndex({ users, filters, roles, outlets, multi }: UsersProps) {
    const { auth } = usePage().props;
    const { values, set, reset, active } = useFilters('admin.users.index', filters);
    const [editing, setEditing] = useState<UserRow | 'new' | null>(null);

    return (
        <BackOfficeLayout
            title="Users"
            description="Staff logins and customer accounts"
            actions={
                <Button size="sm" onClick={() => setEditing('new')} icon={<Plus className="size-4" aria-hidden />}>
                    New user
                </Button>
            }
        >
            <Head title="Users" />

            <Card className="mb-4 grid gap-3 p-4 sm:grid-cols-[minmax(0,2fr)_1fr_auto] sm:items-end">
                <Input
                    label="Search"
                    type="search"
                    placeholder="Name, email or phone"
                    prefix={<Search className="size-4" aria-hidden />}
                    value={values.search}
                    onChange={(event) => set('search', event.target.value)}
                />
                <Select label="Role" value={values.role} onChange={(event) => set('role', event.target.value)}>
                    <option value="">All roles</option>
                    {roles.map((role) => (
                        <option key={role.value} value={role.value}>
                            {role.label}
                        </option>
                    ))}
                </Select>
                <Button variant="ghost" onClick={reset} disabled={!active}>
                    Clear
                </Button>
            </Card>

            <Card>
                {users.data.length === 0 ? (
                    <EmptyState icon={<Users className="size-8" />} title="No users found">
                        Try a different search or role.
                    </EmptyState>
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <Th>User</Th>
                                <Th>Phone</Th>
                                <Th>Role</Th>
                                <Th>Orders</Th>
                                <Th>Joined</Th>
                                <Th>Status</Th>
                                <Th className="text-right">Actions</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => {
                                const self = user.id === auth.user?.id;

                                return (
                                    <tr key={user.id} className="hover:bg-ink-800/60">
                                        <Td>
                                            <span className="font-bold text-strong">
                                                {user.name}
                                                {self && <span className="ml-2 text-xs font-semibold text-flame-400">You</span>}
                                            </span>
                                            <span className="block text-xs text-ink-400">{user.email ?? 'Signs in with mobile number'}</span>
                                        </Td>
                                        <Td>{user.phone ? `+${user.phone}` : '—'}</Td>
                                        <Td>
                                            <Badge tone={roleTones[user.role]}>{user.role_label}</Badge>
                                            {multi && user.outlet_name && <span className="mt-1 block text-xs text-ink-400">{user.outlet_name}</span>}
                                        </Td>
                                        <Td>{user.orders_count}</Td>
                                        <Td className="text-xs text-ink-300">{date(user.created_at)}</Td>
                                        <Td>
                                            <ActiveBadge active={user.is_active} />
                                        </Td>
                                        <Td>
                                            <RowActions
                                                name={user.name}
                                                onEdit={() => setEditing(user)}
                                                onDelete={() => router.delete(route('admin.users.destroy', user.id), { preserveScroll: true })}
                                                deleteDisabled={self}
                                                deleteNote="Accounts with order or delivery history are deactivated instead of deleted, so past records stay intact."
                                            />
                                        </Td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </Table>
                )}
                <Pagination page={users} />
            </Card>

            {editing && (
                <UserForm
                    key={editing === 'new' ? 'new' : editing.id}
                    user={editing === 'new' ? null : editing}
                    roles={roles} outlets={outlets} multi={multi}
                    self={editing !== 'new' && editing.id === auth.user?.id}
                    onClose={() => setEditing(null)}
                />
            )}
        </BackOfficeLayout>
    );
}

function UserForm({ user, roles, outlets, multi, self, onClose }: { user: UserRow | null; roles: Option<Role>[]; outlets: UsersProps['outlets']; multi: boolean; self: boolean; onClose: () => void }) {
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        phone: user?.phone ?? '',
        role: user?.role ?? ('cashier' as Role),
        is_active: user?.is_active ?? true,
        // New staff start at the main outlet until another one is picked.
        outlet_id: (user?.outlet_id ?? outlets.find((outlet) => outlet.is_default)?.id ?? '') as number | '',
        password: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (user) {
            form.put(route('admin.users.update', user.id), options);
        } else {
            form.post(route('admin.users.store'), options);
        }
    };

    return (
        <Modal open onClose={onClose} title={user ? `Edit ${user.name}` : 'New user'}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid gap-3 sm:grid-cols-2">
                    <Input label="Full name" required autoFocus value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} error={form.errors.name} />
                    <Input label="Mobile number" type="tel" placeholder="012-345 6789" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} error={form.errors.phone} />
                    <Input label={form.data.role === 'customer' ? 'Email (optional)' : 'Email (login)'} type="email" required={form.data.role !== 'customer'} value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} error={form.errors.email} />
                    <Select
                        label="Role"
                        required
                        disabled={self}
                        hint={self ? 'You cannot change your own role.' : form.data.role === 'rider' ? 'A rider profile is created automatically.' : undefined}
                        value={form.data.role}
                        onChange={(event) => form.setData('role', event.target.value as Role)}
                        error={form.errors.role}
                    >
                        {roles.map((role) => (
                            <option key={role.value} value={role.value}>
                                {role.label}
                            </option>
                        ))}
                    </Select>
                    {multi && form.data.role !== 'admin' && form.data.role !== 'customer' && (
                        <Select
                            label="Works at"
                            wrapperClassName="sm:col-span-2"
                            hint="Staff only see and handle orders of their own outlet."
                            value={form.data.outlet_id}
                            onChange={(event) => form.setData('outlet_id', event.target.value ? Number(event.target.value) : '')}
                            error={form.errors.outlet_id}
                        >
                            {outlets.map((outlet) => (
                                <option key={outlet.id} value={outlet.id}>
                                    {outlet.name}
                                    {outlet.is_active ? '' : ' (inactive)'}
                                </option>
                            ))}
                        </Select>
                    )}
                </div>
                <Input
                    label={form.data.role === 'customer' ? (user ? 'New PIN' : 'PIN') : user ? 'New password' : 'Password'}
                    type="password"
                    autoComplete="new-password"
                    required={!user}
                    hint={`${user ? 'Leave empty to keep the current one. ' : ''}${form.data.role === 'customer' ? 'Customers: a 6-digit PIN (they sign in with their mobile number).' : 'At least 8 characters.'}`}
                    value={form.data.password}
                    onChange={(event) => form.setData('password', event.target.value)}
                    error={form.errors.password}
                />
                <Toggle
                    checked={form.data.is_active}
                    onChange={(checked) => form.setData('is_active', checked)}
                    disabled={self}
                    label="Account active"
                    description={self ? 'You cannot deactivate your own account.' : 'Inactive users cannot sign in.'}
                />
                {form.errors.is_active && <p className="text-xs font-medium text-danger-400">{form.errors.is_active}</p>}
                <div className="flex justify-end gap-2 pt-2">
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" loading={form.processing}>
                        {user ? 'Save changes' : 'Add user'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
