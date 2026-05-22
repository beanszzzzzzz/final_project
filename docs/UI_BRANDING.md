# UI & Branding Guide

Purpose: provide a compact design system so the Web Admin Panel and Mobile App stay visually consistent and adapt well across screen sizes.

1) Core tokens

- Colors (CSS variables in `templates/base.html.twig`):
  - `--brown-dark`: primary brand (dark)
  - `--brown-mid`, `--brown-warm`, `--brown-light`: supportive palette
  - `--accent`: call-to-action accent
  - `--cream-*`: backgrounds and neutrals
  - `--text-dark`, `--text-mid`, `--text-light`

- Typography:
  - Brand serif: `Playfair Display` — headings, logo.
  - UI sans: `Lato` — body, forms, nav.
  - Headings scale: H1 2.4rem, H2 1.6rem, H3 1.2rem (adjust in CSS).

- Spacing scale (use multiples): 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64 px.

2) Breakpoints

- Mobile-first responsive system:
  - small: up to 480px (mobile)
  - medium: up to 768px (tablet)
  - large: 769–1200px (desktop)
  - xlarge: 1200px+ (wide)

3) Navigation & Layout

- Header (public): sticky top nav with compact brand on mobile. Use `.nav-toggle` to show/hide `.nav-links` on small screens.
- Sidebar (admin): fixed 250px on desktop, collapses to top area on mobile (current `templates/base.html.twig` behavior). Keep primary actions in the sidebar; move user-specific and secondary links to a `.../menu` component.
- Main content: use `.container` centered, max-width 1200px. Cards use light backgrounds, rounded corners, and subtle shadows as in existing `.main-content.with-sidebar.admin-area` rules.

4) Components

- Buttons: primary (`.btn-primary`), accent (`.btn-accent`), outline (`.btn-outline`) — consistent padding and 8px border-radius for forms, 10px for admin cards.
- Forms: inputs use 10px radius, 12–16px padding, clear focus ring `box-shadow: 0 0 0 3px rgba(189,127,80,0.16)`.
- Tables: compact spacing, header uppercase small caps, zebra row hover with soft background.

5) Mobile-specific guidance

- Use large touch targets (44–48px minimum) for primary actions.
- Use stacked layouts for forms and reorder priorities: title, primary action, secondary links.
- Avoid dense tables on mobile — provide card alternatives or horizontal scrolling.

6) Accessibility

- Color contrast: ensure text on brand backgrounds meets AA contrast (use `--cream-light` on `--brown-dark`).
- Keyboard focus: all interactive elements must show visible focus styles.
- ARIA: use `aria-label` on icon buttons (existing nav toggle has it).

7) Asset & Implementation notes

- Centralize tokens in `templates/base.html.twig` `:root` variables so server-rendered pages and built assets share the same values.
- Mobile app should reference the same hex tokens for color/spacing/typography. If the mobile app supports CSS-like variables (React Native web, Flutter theming), map these tokens to the native theme constants.

8) Quick checklist for designers/developers

- Reuse `--accent` for CTAs and success highlights.
- Use `Playfair Display` for hero headings only; use `Lato` elsewhere for legibility.
- Test interactive flows on small screens with a 320px width simulator.

If you want, I can also:
- extract the CSS token block into `assets/styles/_tokens.css` and import it into the build,
- add a small React/Vue style guide component (Storybook) scaffold,
- or add sample Postman/HTTP UI mocks.
