import { useId, useState, type PointerEvent } from 'react';
import { formatDuration, formatTimeAgo } from '@/lib/format';

// Direction colours come from the fixed status palette: up = restocking, down = selling down.
const UP = '#0ca30c';
const DOWN = '#d03b3b';
const FLAT = '#8f8f8a';

const directionColor = (delta: number) => (delta > 0 ? UP : delta < 0 ? DOWN : FLAT);

interface SparklineProps {
    values: number[];
    /** ISO time of each value (same length as values); enables "how long ago" and "over what time" readouts. */
    times?: string[];
    /** Accessible description of what the values are, e.g. "Stock". */
    label: string;
    width?: number;
    height?: number;
    className?: string;
}

/**
 * Single-series trend line. Each segment is coloured by direction (green up,
 * red down, grey flat) and the colours blend into each other at the turns;
 * a faded fill under the line carries the same colours. The hover readout
 * states the value, when it was read and its change, so direction never
 * relies on colour alone.
 */
export function Sparkline({ values, times, label, width = 96, height = 28, className = '' }: SparklineProps) {
    const [hover, setHover] = useState<number | null>(null);
    // useId can contain characters that break url(#...) references.
    const uid = useId().replace(/[^a-zA-Z0-9_-]/g, '');
    const strokeId = `spark-stroke-${uid}`;
    const fadeId = `spark-fade-${uid}`;
    const maskId = `spark-mask-${uid}`;

    if (values.length < 2) return null;

    const pad = 3;
    const max = Math.max(...values, 1);
    const last = values.length - 1;
    const x = (i: number) => pad + (i / last) * (width - pad * 2);
    const y = (v: number) => height - pad - (v / max) * (height - pad * 2);
    const points = values.map((v, i) => `${x(i).toFixed(1)},${y(v).toFixed(1)}`);
    const baseline = height - pad;
    const area = `M${x(0).toFixed(1)},${baseline} L${points.join(' L')} L${x(last).toFixed(1)},${baseline} Z`;

    // One stop per segment midpoint in that segment's colour, so neighbours blend at each turn.
    const segmentColors = values.slice(1).map((v, i) => directionColor(v - values[i]));
    const stops = [
        { offset: 0, color: segmentColors[0] },
        ...segmentColors.map((color, i) => ({ offset: (i + 0.5) / last, color })),
        { offset: 1, color: segmentColors[segmentColors.length - 1] },
    ];

    const timeAt = (i: number) => (times?.[i] ? new Date(times[i]).getTime() : null);
    const firstTime = timeAt(0);
    const lastTime = timeAt(last);
    const span = firstTime !== null && lastTime !== null ? formatDuration(lastTime - firstTime) : null;

    const active = hover ?? last;
    const activeColor = active === 0 ? segmentColors[0] : segmentColors[active - 1];
    const delta = active === 0 ? 0 : values[active] - values[active - 1];
    const activeTime = timeAt(active);
    const previousTime = active > 0 ? timeAt(active - 1) : null;
    const gap = activeTime !== null && previousTime !== null ? formatDuration(activeTime - previousTime) : null;

    const onPointerMove = (event: PointerEvent<SVGSVGElement>) => {
        const rect = event.currentTarget.getBoundingClientRect();
        const ratio = (event.clientX - rect.left) / rect.width;
        setHover(Math.min(last, Math.max(0, Math.round(ratio * last))));
    };

    const when = (i: number) => (times?.[i] ? formatTimeAgo(times[i]) : i === last ? 'now' : `${last - i} checks ago`);
    const change = delta === 0 ? '' : ` · ${delta > 0 ? '+' : '−'}${Math.abs(delta)}${gap ? ` in ${gap}` : ''}`;

    return (
        <span className={`relative inline-flex items-center ${className}`}>
            <svg
                role="img"
                aria-label={`${label} trend: ${values[0]} → ${values[last]} over ${span ?? `${values.length} checks`}`}
                viewBox={`0 0 ${width} ${height}`}
                width={width}
                height={height}
                className="overflow-visible"
                onPointerMove={onPointerMove}
                onPointerLeave={() => setHover(null)}
            >
                <defs>
                    <linearGradient id={strokeId} gradientUnits="userSpaceOnUse" x1={x(0)} x2={x(last)} y1={0} y2={0}>
                        {stops.map((stop, i) => (
                            <stop key={i} offset={stop.offset} stopColor={stop.color} />
                        ))}
                    </linearGradient>
                    <linearGradient id={fadeId} x1={0} x2={0} y1={0} y2={1}>
                        <stop offset={0} stopColor="white" stopOpacity={0.45} />
                        <stop offset={1} stopColor="white" stopOpacity={0} />
                    </linearGradient>
                    <mask id={maskId}>
                        <rect x={0} y={0} width={width} height={height} fill={`url(#${fadeId})`} />
                    </mask>
                </defs>

                <path d={area} fill={`url(#${strokeId})`} mask={`url(#${maskId})`} />
                <line x1={pad} x2={width - pad} y1={baseline} y2={baseline} stroke="#c3c2b7" strokeWidth={1} />
                <polyline
                    points={points.join(' ')}
                    fill="none"
                    stroke={`url(#${strokeId})`}
                    strokeWidth={2}
                    strokeLinejoin="round"
                    strokeLinecap="round"
                />
                {hover !== null && <line x1={x(hover)} x2={x(hover)} y1={pad} y2={baseline} stroke="#c3c2b7" strokeWidth={1} />}
                <circle cx={x(active)} cy={y(values[active])} r={3} fill={activeColor} stroke="white" strokeWidth={1.5} />
            </svg>
            {hover !== null && (
                <span className="pointer-events-none absolute bottom-full left-1/2 z-20 mb-1 -translate-x-1/2 bg-black px-1.5 py-0.5 text-[10px] whitespace-nowrap text-white">
                    {label} {values[hover]} · {when(hover)}
                    {change}
                </span>
            )}
        </span>
    );
}
