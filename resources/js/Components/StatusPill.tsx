import type { ReactNode } from 'react';

import Badge from '@/Components/Badge';
import type { BadgeProps, BadgeTone } from '@/Components/Badge';
import { useI18n } from '@/lib/i18n';

export type StatusColorMap<Status extends string> = Readonly<
    Partial<Record<Status, BadgeTone>>
>;

export interface StatusPillProps<Status extends string>
    extends Omit<BadgeProps, 'children' | 'tone'> {
    colorMap: StatusColorMap<Status>;
    label?: ReactNode;
    status: Status;
}

export default function StatusPill<Status extends string>({
    colorMap,
    label,
    status,
    ...badgeProps
}: StatusPillProps<Status>) {
    const t = useI18n();

    return (
        <Badge {...badgeProps} tone={colorMap[status] ?? 'neutral'}>
            {label ?? t(`statuses.${status}`)}
        </Badge>
    );
}
