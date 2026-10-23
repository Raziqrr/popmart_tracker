import { describe, it, expect } from 'vitest';
import { formatMoney } from './format';

describe('formatMoney', () => {
    it('formats MYR correctly', () => {
        expect(formatMoney(1680, 'MYR')).toBe('RM16.80');
    });

    it('formats USD correctly', () => {
        expect(formatMoney(1680, 'USD')).toBe('$16.80');
    });

    it('formats JPY correctly (no minor units)', () => {
        // JPY does not have minor units, so 1680 minor units are 1680 JPY
        expect(formatMoney(1680, 'JPY')).toBe('¥1,680');
    });

    it('formats EUR correctly', () => {
        expect(formatMoney(1680, 'EUR')).toBe('€16.80');
    });

    it('formats 0 values correctly', () => {
        expect(formatMoney(0, 'USD')).toBe('$0.00');
        expect(formatMoney(0, 'JPY')).toBe('¥0');
    });

    it('formats large numbers with correct grouping', () => {
        expect(formatMoney(123456789, 'USD')).toBe('$1,234,567.89');
        expect(formatMoney(123456789, 'JPY')).toBe('¥123,456,789');
    });

    it('formats negative numbers correctly', () => {
        expect(formatMoney(-1680, 'USD')).toBe('-$16.80');
    });
});
