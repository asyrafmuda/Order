import { Badge } from '@/components/ui/Badge';

export function ActiveBadge({ active, on = 'Active', off = 'Inactive' }: { active: boolean; on?: string; off?: string }) {
    return <Badge tone={active ? 'green' : 'neutral'}>{active ? on : off}</Badge>;
}
