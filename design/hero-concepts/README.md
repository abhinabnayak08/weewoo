# WeeWoo homepage hero — three concepts

Self-contained mockup of three hero directions for `weewoo.in`, each shown at
desktop and phone width. Open `index.html` in a browser — no build step, no
network calls, nothing to install.

## What's in here

| # | Name | Ground | Best for |
|---|------|--------|----------|
| 01 | Aurora | Light | Memorability — one number, huge, on air |
| 02 | The Ledger | Light | Conversion — does the savings arithmetic on screen |
| 03 | Midnight | Dark | Repositioning — reads premium rather than discount |

## The theme was not changed

Every colour is lifted verbatim from
`wordpress-plugin/weewoo-auth-pro/templates/login-page.php`:

```
--primary       #10B981   emerald, the brand primary
--primary-2     #059669   deep emerald, gradient partner
                #06B6D4   cyan accent
                #8B5CF6   violet accent
--ink           #0F172A   headings
--ink-soft      #475569   body
--ink-muted     #94A3B8   captions, struck-through prices
                #F8FAFC   paper
                #EEF2F7   paper, second stop
--bg-1/2/3      #060912 / #0A0F1C / #0E1629   night grounds
```

Concept 03 is dark, but it introduces nothing new — it is the same orb, glass
and gradient system already running on the live `/secure-login/` page, applied
to the homepage.

Typeface is **Plus Jakarta Sans**, the face already loaded site-wide. It is
inlined here as a base64 `@font-face` (27 KB variable, latin subset) so the file
renders identically offline. In production keep the existing Google Fonts link
instead.

## How the responsive previews work

Each hero is a CSS **container** (`container-type: inline-size`), not a
viewport-driven layout. The same markup is rendered twice — once inside a
1440px browser frame, once inside a 390px phone frame — and reflows off its own
width via `@container` queries. So what you see in the phone frame is the real
mobile composition, not a separate mockup.

Breakpoints per concept:

- **900px** — two-column splits collapse to stacked (02, 03); the 3D tilt on 03
  flattens to a straight stack.
- **640px** — nav links and the secondary CTA drop out, buttons go full-width,
  Aurora's centred type switches to left-aligned, the receipt total in 02
  stacks above its savings badge.

## Before shipping

- Brand marks are flat colour blocks with letter stand-ins. Drop in the real
  SVG logos.
- Prices are illustrative (₹649 → ₹199 etc.). Wire them to live plan data.
- The 02 receipt is the strongest candidate for personalisation: swap the four
  static rows for the signed-in visitor's actual cart.
- `prefers-reduced-motion` is honoured — the marquee, orbs, floating tiles and
  the strike-through all stop.
