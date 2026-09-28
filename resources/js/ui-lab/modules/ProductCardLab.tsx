import { ProductCard } from '@/components/product/ProductCard';
import { StatusBadge } from '@/components/product/StatusBadge';
import { STATUS_ORDER } from '@/lib/productStatus';
import type { ProductCardData } from '@/types/catalog';
import { productBySlug } from '../fixtures/products';
import { useProductActions } from '../fixtures/useProductActions';
import { LabHeader, Specimen, SpecimenGrid } from '../Specimen';

const comingSoon = productBySlug('mochi-bun-sweet-dreams');
const lowStock = productBySlug('lumi-starlight-voyage');
const priceDrop = productBySlug('kip-rainy-day');
const soldOut = productBySlug('mochi-bun-life-is-a-show');
const popNow = productBySlug('pebble-pals-midnight-snack');
const restocked = productBySlug('lumi-frosted-orchard');
const priceUp = productBySlug('kip-400-collector');

const edgeCases: { label: string; note: string; product: ProductCardData }[] = [
    { label: 'No image', note: 'image_url is null', product: { ...priceDrop, id: 'edge-no-image', image_url: null } },
    { label: 'No theme, no history', note: 'theme_name and stock_history missing', product: { ...productBySlug('moss-mallow-picnic-club'), stock_history: undefined } },
    {
        label: 'Long name',
        note: 'Clamped to two lines',
        product: {
            ...priceDrop,
            id: 'edge-long-name',
            name: 'Kip the Fox Rainy Day Series Figures Limited Anniversary Edition with Exclusive Umbrella Accessory Set',
        },
    },
    { label: 'Hidden stock', note: 'remain_stock null (masked SPU)', product: popNow },
];

export function ProductCardLab() {
    const actions = useProductActions();
    const card = (product: ProductCardData, opts: { showSaleTime?: boolean; pinned?: boolean; watching?: boolean } = {}) => {
        const forced = opts.pinned !== undefined || opts.watching !== undefined;

        return (
            <ProductCard
                product={product}
                href={`#${product.slug}`}
                pinned={opts.pinned ?? actions.pinnedIds.has(product.id)}
                watching={opts.watching ?? actions.watchedIds.has(product.id)}
                onTogglePin={forced ? undefined : actions.onTogglePin}
                onToggleWatch={forced ? undefined : actions.onToggleWatch}
                showSaleTime={opts.showSaleTime}
            />
        );
    };

    return (
        <div className="flex flex-col gap-12">
            <LabHeader
                title="Product card"
                description="Monitoring tile: status pill, stock/countdown, price change, stock trend and last-checked time. Pin keeps it on the dashboard (pinned_items); the bell turns on alerts (wishlist_items)."
                source="components/product/ProductCard.tsx"
            />

            <section className="flex flex-col gap-4">
                <h2 className="text-lg font-bold">Status badge</h2>
                <div className="flex flex-wrap gap-6">
                    {STATUS_ORDER.map((status) => (
                        <div key={status} className="flex flex-col gap-2">
                            <StatusBadge status={status} />
                            <StatusBadge status={status} size="xs" />
                        </div>
                    ))}
                </div>
            </section>

            <section className="flex flex-col gap-6">
                <h2 className="text-lg font-bold">States</h2>
                <SpecimenGrid>
                    <Specimen label="Coming soon" note="Countdown, sale time shown">
                        {card(comingSoon, { showSaleTime: true })}
                    </Specimen>
                    <Specimen label="Low stock" note="remain_stock ≤ 20, falling trend">
                        {card(lowStock)}
                    </Specimen>
                    <Specimen label="Price drop" note="previous_price > price">
                        {card(priceDrop)}
                    </Specimen>
                    <Specimen label="Price up" note="previous_price < price">
                        {card(priceUp)}
                    </Specimen>
                    <Specimen label="Just restocked" note="Trend jumps from 0">
                        {card(restocked)}
                    </Specimen>
                    <Specimen label="Sold out">{card(soldOut)}</Specimen>
                    <Specimen label="Pinned + alerts on" note="Both toggles active">
                        {card(lowStock, { pinned: true, watching: true })}
                    </Specimen>
                    <Specimen label="POP NOW" note="business_type = draw">
                        {card(popNow)}
                    </Specimen>
                </SpecimenGrid>
            </section>

            <section className="flex flex-col gap-6">
                <h2 className="text-lg font-bold">Edge cases</h2>
                <SpecimenGrid>
                    {edgeCases.map((edge) => (
                        <Specimen key={edge.label} label={edge.label} note={edge.note}>
                            {card(edge.product)}
                        </Specimen>
                    ))}
                </SpecimenGrid>
            </section>
        </div>
    );
}
