import { Pencil, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/Button';
import { ConfirmButton } from '@/components/ui/ConfirmButton';

interface RowActionsProps {
    name: string;
    onEdit: () => void;
    onDelete: () => void;
    /** Extra explanation shown in the delete confirmation. */
    deleteNote?: ReactNode;
    deleteDisabled?: boolean;
}

/** Edit / delete buttons for a table row, with a confirmation before deleting. */
export function RowActions({ name, onEdit, onDelete, deleteNote, deleteDisabled }: RowActionsProps) {
    return (
        <div className="flex justify-end gap-1.5">
            <Button variant="secondary" size="xs" onClick={onEdit} icon={<Pencil className="size-3.5" aria-hidden />} aria-label={`Edit ${name}`}>
                Edit
            </Button>
            <ConfirmButton
                size="xs"
                title={`Delete ${name}?`}
                message={deleteNote ?? 'This cannot be undone.'}
                confirmLabel="Delete"
                onConfirm={onDelete}
                disabled={deleteDisabled}
                icon={<Trash2 className="size-3.5" aria-hidden />}
            >
                Delete
            </ConfirmButton>
        </div>
    );
}
