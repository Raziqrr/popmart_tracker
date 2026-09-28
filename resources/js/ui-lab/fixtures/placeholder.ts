/** Simple vinyl-figure silhouette as an SVG data URI, so the preview needs no real product photos. */
export function figurePlaceholder(body: string, accent: string): string {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">
  <ellipse cx="100" cy="186" rx="46" ry="7" fill="#000" opacity=".08"/>
  <rect x="62" y="112" width="76" height="70" rx="30" fill="${body}"/>
  <circle cx="100" cy="78" r="56" fill="${body}"/>
  <path d="M44 70a56 56 0 0 1 112 0c-18-14-38-20-56-20S62 56 44 70z" fill="${accent}"/>
  <circle cx="80" cy="88" r="7" fill="#1a1a1a"/><circle cx="120" cy="88" r="7" fill="#1a1a1a"/>
  <circle cx="82" cy="85" r="2.2" fill="#fff"/><circle cx="122" cy="85" r="2.2" fill="#fff"/>
  <ellipse cx="66" cy="104" rx="9" ry="5" fill="${accent}" opacity=".45"/>
  <ellipse cx="134" cy="104" rx="9" ry="5" fill="${accent}" opacity=".45"/>
  <path d="M92 108q8 7 16 0" stroke="#1a1a1a" stroke-width="3" fill="none" stroke-linecap="round"/>
</svg>`;

    return `data:image/svg+xml,${encodeURIComponent(svg)}`;
}
