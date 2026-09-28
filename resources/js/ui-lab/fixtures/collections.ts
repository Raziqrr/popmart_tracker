import type { CollectionSummary } from '@/types/catalog';
import { figurePlaceholder } from './placeholder';
import { daysAgo, hoursAgo, hoursFromNow, minutesAgo } from './time';

// Fictional collections (one theme in one area) with rolled-up product status.
// SYNC: follows CollectionSummary in types/catalog.ts (see app/Models/README.md).
export const sampleCollections: CollectionSummary[] = [
    {
        id: 'c0a1b2c3-0001-4d5e-8f90-000000000001', theme_id: 't-mochi', area: 'MY', name: 'Mochi Bun', slug: 'mochi-bun',
        theme_name: 'Mochi Bun', image_url: figurePlaceholder('#f7d9c4', '#e85d5d'), product_count: 14,
        status_counts: { in_stock: 6, low_stock: 2, coming_soon: 1, sold_out: 5 },
        next_sale_at: hoursFromNow(91), first_seen_at: daysAgo(120), last_changed_at: hoursAgo(6),
    },
    {
        id: 'c0a1b2c3-0001-4d5e-8f90-000000000002', theme_id: 't-lumi', area: 'MY', name: 'Lumi', slug: 'lumi',
        theme_name: 'Lumi', image_url: figurePlaceholder('#e8e1f5', '#6d5bd0'), product_count: 9,
        status_counts: { in_stock: 5, low_stock: 1, coming_soon: 0, sold_out: 3 },
        next_sale_at: null, first_seen_at: daysAgo(64), last_changed_at: minutesAgo(2),
    },
    {
        id: 'c0a1b2c3-0001-4d5e-8f90-000000000003', theme_id: 't-kip', area: 'MY', name: 'Kip the Fox', slug: 'kip-the-fox',
        theme_name: 'Kip the Fox', image_url: figurePlaceholder('#ffd7b0', '#ea6a1f'), product_count: 7,
        status_counts: { in_stock: 3, low_stock: 1, coming_soon: 0, sold_out: 3 },
        next_sale_at: null, first_seen_at: daysAgo(40), last_changed_at: hoursAgo(1),
    },
    {
        id: 'c0a1b2c3-0001-4d5e-8f90-000000000004', theme_id: 't-pebble', area: 'MY', name: 'Pebble Pals', slug: 'pebble-pals',
        theme_name: 'Pebble Pals', image_url: figurePlaceholder('#ffe39c', '#f59e0b'), product_count: 11,
        status_counts: { in_stock: 4, low_stock: 0, coming_soon: 2, sold_out: 5 },
        next_sale_at: hoursFromNow(5.3), first_seen_at: daysAgo(5), last_changed_at: hoursAgo(3),
    },
    {
        id: 'c0a1b2c3-0001-4d5e-8f90-000000000005', theme_id: 't-moss', area: 'MY', name: 'Moss & Mallow', slug: 'moss-mallow',
        theme_name: null, image_url: figurePlaceholder('#f3e9c6', '#7a9a3a'), product_count: 3,
        status_counts: { in_stock: 3, low_stock: 0, coming_soon: 0, sold_out: 0 },
        next_sale_at: null, first_seen_at: daysAgo(2), last_changed_at: daysAgo(2),
    },
];

export const samplePinnedCollectionIds = [sampleCollections[0].id, sampleCollections[1].id, sampleCollections[3].id];
