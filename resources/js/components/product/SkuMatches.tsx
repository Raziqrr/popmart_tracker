import { Crown, ScanBarcode } from 'lucide-react';
import type { SkuSummary } from '@/types/catalog';

/** "Has: Night Owl · PM-PMS-05" line shown under a product when the SKU filter matched it. */
export function SkuMatches({ skus }: { skus?: SkuSummary[] }) {
    if (!skus?.length) return null;

    const [first, ...rest] = skus;

    return (
        <p className="flex items-center gap-1 text-[11px] text-black/70" title={skus.map((s) => `${s.name} (${s.sku_code})`).join('\n')}>
            <ScanBarcode aria-hidden="true" className="size-3.5 shrink-0 text-brand" />
            <span className="truncate">
                Has: <strong className="font-bold">{first.name}</strong>
                {first.is_secret && <Crown aria-label="secret" className="ml-0.5 inline size-3 align-[-1px]" fill="currentColor" />}
                {first.sku_code && <span className="text-black/50"> · {first.sku_code}</span>}
                {rest.length > 0 && <span className="text-black/50"> +{rest.length} more</span>}
            </span>
        </p>
    );
}
