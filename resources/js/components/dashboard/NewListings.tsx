import { Layers, Sparkles } from 'lucide-react';
import { useState } from 'react';
import { LockToggle } from '@/components/lock/LockToggle';
import { SaleTypeBadge } from '@/components/product/SaleTypeBadge';
import { StatusBadge } from '@/components/product/StatusBadge';
import { PinToggle } from '@/components/product/WatchToggle';
import type { ProductActions } from '@/components/product/actions';
import { formatMoney, formatTimeAgo } from '@/lib/format';
import { productStatus } from '@/lib/productStatus';
import type { CollectionSummary, ProductCardData } from '@/types/catalog';

interface NewListingsProps extends Pick<ProductActions, 'pinnedIds' | 'onTogglePin' | 'productHref'> {
    products: ProductCardData[];
    collections: CollectionSummary[];
    collectionHref?: (collection: CollectionSummary) => string;
    limit?: number;
}

/** Newest products and collections, by when the scraper first saw them. */
export function NewListings({ products, collections, collectionHref, limit = 6, ...actions }: NewListingsProps) {
    const [tab, setTab] = useState<'products' | 'collections'>('products');

    const newestProducts = [...products]
        .filter((p) => p.first_seen_at)
        .sort((a, b) => b.first_seen_at!.localeCompare(a.first_seen_at!))
        .slice(0, limit);
    const newestCollections = [...collections].sort((a, b) => b.first_seen_at.localeCompare(a.first_seen_at)).slice(0, limit);

    const tabs = [
        { key: 'products' as const, label: 'Products', icon: Sparkles, count: newestProducts.length },
        { key: 'collections' as const, label: 'Collections', icon: Layers, count: newestCollections.length },
    ];

    return (
        <div className="flex flex-col gap-3">
            <div role="tablist" aria-label="New listings" className="flex gap-1">
                {tabs.map(({ key, label, icon: Icon, count }) => (
                    <button
                        key={key}
                        role="tab"
                        type="button"
                        aria-selected={tab === key}
                        onClick={() => setTab(key)}
                        className="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium text-black/60 hover:text-black aria-selected:bg-black aria-selected:text-white"
                    >
                        <Icon aria-hidden="true" className="size-3.5" />
                        {label}
                        <span className="opacity-60">{count}</span>
                    </button>
                ))}
            </div>

            <ol role="tabpanel" className="divide-y divide-black/10 border-y border-black/10">
                {tab === 'products'
                    ? newestProducts.map((product) => {
                          const href = actions.productHref?.(product);
                          return (
                              <li key={product.id} className="flex items-center gap-3 py-2">
                                  <Thumb src={product.image_url} />
                                  <div className="min-w-0 grow">
                                      <Name href={href}>{product.name}</Name>
                                      <p className="flex flex-wrap items-center gap-x-2 text-[11px] text-black/60">
                                          <StatusBadge status={productStatus(product)} size="xs" />
                                          <SaleTypeBadge product={product} />
                                          <span className="text-brand">{formatMoney(product.price, product.currency)}</span>
                                      </p>
                                  </div>
                                  <span className="shrink-0 text-[11px] text-black/50">{formatTimeAgo(product.first_seen_at!)}</span>
                                  <LockToggle product={product} />
                                  <PinToggle
                                      product={product}
                                      active={actions.pinnedIds?.has(product.id) ?? false}
                                      onToggle={actions.onTogglePin}
                                  />
                              </li>
                          );
                      })
                    : newestCollections.map((collection) => (
                          <li key={collection.id} className="flex items-center gap-3 py-2">
                              <Thumb src={collection.image_url} />
                              <div className="min-w-0 grow">
                                  <Name href={collectionHref?.(collection)}>{collection.name}</Name>
                                  <p className="text-[11px] text-black/60">
                                      {collection.theme_name ?? 'Collection'} · {collection.product_count} products
                                  </p>
                              </div>
                              <span className="shrink-0 text-[11px] text-black/50">{formatTimeAgo(collection.first_seen_at)}</span>
                          </li>
                      ))}
            </ol>
        </div>
    );
}

function Thumb({ src }: { src: string | null }) {
    return <div className="size-10 shrink-0 bg-tile">{src && <img src={src} alt="" loading="lazy" className="size-full object-contain p-1" />}</div>;
}

function Name({ href, children }: { href?: string; children: string }) {
    return href ? (
        <a href={href} className="line-clamp-1 text-xs font-medium hover:underline">
            {children}
        </a>
    ) : (
        <p className="line-clamp-1 text-xs font-medium">{children}</p>
    );
}
