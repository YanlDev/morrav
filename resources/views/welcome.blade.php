@php
/**
 * Cache-busting: agrega ?v=mtime cuando el archivo existe en public/.
 * Si el admin sube una imagen nueva desde /homepage, la URL cambia
 * y los navegadores la recargan en lugar de servir la versión cacheada.
 */
$asset = function (string $path): string {
    $clean = ltrim($path, '/');
    $full = public_path($clean);

    return '/'.$clean.(file_exists($full) ? '?v='.filemtime($full) : '');
};

// Configuración editable desde /homepage. Si no hay archivo guardado, devuelve defaults.
$homepage = \App\Services\HomepageSettings::all();
$stores = $homepage['stores'];
$whatsappMain = $homepage['whatsapp_main'];
$whatsappMainDisplay = $homepage['whatsapp_main_display'];
$emailSales = $homepage['email_sales'];
$emailContracts = $homepage['email_contracts'] ?? '';
$primaryStore = $stores[0];
@endphp
<!DOCTYPE html>
<html lang="es-PE">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>MORRAV OFFICE — Mobiliario y soluciones para tu negocio · Juliaca</title>
<meta name="description" content="Morrav Office S.A.C. · Sillas, mesas y mobiliario para hogar, oficina, barberías, salones, exterior y comercios. Contratos a volumen. 3 tiendas en Juliaca, Puno." />

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
  :root {
    --color-wine: #8E1E3A;
    --color-wine-dark: #7A1832;
    --color-charcoal: #1C1412;
    --color-cream: #FAF8F3;
    --color-cream-dark: #F0EAD9;
    --color-stone: #4A4540;
    --color-stone-light: #6B6660;
    --color-border: #E8E2D3;
    --color-disabled: #C9C2B5;

    --color-success: #2F5233;
    --color-warning: #D4A017;
    --color-danger: #A8201A;
    --color-info: #3A4A5E;

    --font-display: 'Anton', Impact, sans-serif;
    --font-ui: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    --font-mono: 'JetBrains Mono', 'SF Mono', monospace;

    --space-1: 4px; --space-2: 8px; --space-3: 12px; --space-4: 16px;
    --space-5: 20px; --space-6: 24px; --space-8: 32px; --space-12: 48px;

    --radius-sm: 6px; --radius-md: 8px; --radius-lg: 10px;
    --radius-xl: 12px; --radius-pill: 100px;

    --shadow-sm: 0 1px 3px rgba(28, 20, 18, 0.06), 0 1px 2px rgba(28, 20, 18, 0.04);
    --shadow-md: 0 4px 12px rgba(28, 20, 18, 0.08), 0 2px 4px rgba(28, 20, 18, 0.05);
    --shadow-lg: 0 12px 32px rgba(28, 20, 18, 0.12), 0 4px 8px rgba(28, 20, 18, 0.06);
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; scroll-padding-top: 88px; }
  body {
    font-family: var(--font-ui);
    color: var(--color-charcoal);
    background: var(--color-cream);
    font-size: 14px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
  }
  a { color: inherit; text-decoration: none; }
  img, svg { display: block; max-width: 100%; }

  .container {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 var(--space-8);
  }

  .display { font-family: var(--font-display); font-weight: 400; line-height: 1.0; letter-spacing: 0.5px; }
  .mono    { font-family: var(--font-mono); }
  .label   {
    font-size: 11px; font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--color-stone);
  }

  /* ============== NAVBAR ============== */
  .nav {
    position: fixed; top: 0; left: 0; right: 0; z-index: 90;
    background: transparent;
    border-bottom: 1px solid transparent;
    transition: background-color 0.35s ease, border-color 0.35s ease, backdrop-filter 0.35s ease, box-shadow 0.35s ease;
  }
  .nav.scrolled {
    background: rgba(250, 248, 243, 0.88);
    -webkit-backdrop-filter: saturate(180%) blur(14px);
    backdrop-filter: saturate(180%) blur(14px);
    border-bottom-color: rgba(28, 20, 18, 0.06);
    box-shadow: 0 1px 3px rgba(28, 20, 18, 0.04);
  }
  .nav-inner {
    display: flex; align-items: center; justify-content: space-between;
    height: 76px;
  }
  .brand {
    display: flex; align-items: center; gap: var(--space-3);
  }
  .brand-mark {
    width: 40px; height: 40px;
    background: var(--color-wine);
    display: flex; align-items: center; justify-content: center;
    position: relative;
    border-radius: var(--radius-sm);
    box-shadow: 0 4px 12px rgba(142, 30, 58, 0.25);
  }
  .brand-mark::before {
    content: "";
    position: absolute;
    inset: 4px;
    border: 1.5px solid var(--color-cream);
    border-radius: 3px;
  }
  .brand-mark span {
    font-family: var(--font-display);
    color: var(--color-cream);
    font-size: 22px;
    line-height: 1;
    letter-spacing: 1px;
    position: relative;
    z-index: 1;
  }
  .brand-text {
    display: flex; flex-direction: column; line-height: 1;
  }
  .brand-text .name {
    font-family: var(--font-display);
    font-size: 22px;
    letter-spacing: 3px;
    color: var(--color-cream);
    transition: color 0.35s ease;
  }
  .nav.scrolled .brand-text .name { color: var(--color-charcoal); }
  .brand-text .sub {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 2.5px;
    color: var(--color-wine);
    margin-top: 4px;
  }

  .nav-links {
    display: flex; gap: var(--space-8);
    list-style: none;
  }
  .nav-links a {
    font-size: 13px; font-weight: 500;
    color: rgba(250, 248, 243, 0.85);
    transition: color 0.2s ease;
    position: relative;
    padding: 6px 0;
  }
  .nav-links a::after {
    content: ""; position: absolute;
    left: 0; bottom: 0; width: 0; height: 1.5px;
    background: var(--color-wine);
    transition: width 0.25s ease;
  }
  .nav-links a:hover { color: var(--color-cream); }
  .nav-links a:hover::after { width: 100%; }
  .nav.scrolled .nav-links a { color: var(--color-stone); }
  .nav.scrolled .nav-links a:hover { color: var(--color-wine); }

  .nav-actions { display: flex; gap: var(--space-2); align-items: center; }

  /* ============== BOTONES ============== */
  .btn {
    display: inline-flex; align-items: center; gap: var(--space-2);
    font-family: var(--font-ui);
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.2px;
    padding: 11px 22px;
    border-radius: var(--radius-md);
    border: 1px solid transparent;
    cursor: pointer;
    transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, transform 0.15s ease;
    text-decoration: none;
    white-space: nowrap;
    line-height: 1;
  }
  .btn svg { transition: transform 0.2s ease; }
  .btn:hover svg { transform: translateX(2px); }

  .btn-primary {
    background: var(--color-wine);
    color: var(--color-cream);
    box-shadow: 0 1px 2px rgba(28, 20, 18, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.08);
  }
  .btn-primary:hover {
    background: var(--color-wine-dark);
    box-shadow: 0 6px 16px rgba(142, 30, 58, 0.32), inset 0 1px 0 rgba(255, 255, 255, 0.1);
    transform: translateY(-1px);
  }
  .btn-primary:active { transform: translateY(0); }

  .btn-secondary {
    background: var(--color-cream);
    color: var(--color-charcoal);
    border-color: var(--color-cream);
    box-shadow: 0 1px 2px rgba(28, 20, 18, 0.06);
  }
  .btn-secondary:hover {
    background: var(--color-cream-dark);
    border-color: var(--color-cream-dark);
    box-shadow: 0 4px 12px rgba(28, 20, 18, 0.1);
    transform: translateY(-1px);
  }

  .btn-ghost {
    background: transparent;
    color: var(--color-stone);
    padding: 9px 14px;
    font-size: 12px;
    font-weight: 500;
  }
  .btn-ghost:hover {
    color: var(--color-wine);
    background: rgba(142, 30, 58, 0.08);
  }
  .btn-ghost svg { opacity: 0.65; }

  /* Estados especiales del nav cuando es transparente (sobre hero oscuro) */
  .nav:not(.scrolled) .btn-secondary {
    background: rgba(250, 248, 243, 0.12);
    color: var(--color-cream);
    border-color: rgba(250, 248, 243, 0.25);
    -webkit-backdrop-filter: blur(8px);
    backdrop-filter: blur(8px);
    box-shadow: none;
  }
  .nav:not(.scrolled) .btn-secondary:hover {
    background: rgba(250, 248, 243, 0.22);
    border-color: rgba(250, 248, 243, 0.5);
  }
  .nav:not(.scrolled) .btn-ghost {
    color: rgba(250, 248, 243, 0.75);
  }
  .nav:not(.scrolled) .btn-ghost:hover {
    color: var(--color-cream);
    background: rgba(250, 248, 243, 0.1);
  }

  .menu-toggle {
    display: none;
    background: transparent;
    border: 1px solid rgba(250, 248, 243, 0.25);
    border-radius: var(--radius-sm);
    width: 40px; height: 40px;
    color: var(--color-cream);
    cursor: pointer;
    align-items: center; justify-content: center;
    transition: all 0.2s ease;
  }
  .nav.scrolled .menu-toggle {
    color: var(--color-charcoal);
    border-color: var(--color-border);
  }
  .menu-toggle svg { width: 20px; height: 20px; }

  @media (max-width: 880px) {
    .nav-links { display: none; }
    .menu-toggle { display: inline-flex; }
    .nav-actions .btn-secondary,
    .nav-actions .btn-ghost { display: none; }
  }

  /* ============== HERO ============== */
  .hero {
    position: relative;
    padding: 140px 0 100px;
    min-height: 88vh;
    overflow: hidden;
    isolation: isolate;
    background: var(--color-charcoal);
    display: flex;
    align-items: center;
  }
  .hero-bg {
    position: absolute;
    top: -10%; left: 0; right: 0;
    height: 130%;
    z-index: -2;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    will-change: transform;
    transform: translate3d(0, 0, 0);
  }
  .hero::after {
    content: "";
    position: absolute;
    inset: 0;
    z-index: -1;
    background:
      linear-gradient(90deg, rgba(28,20,18,0.92) 0%, rgba(28,20,18,0.7) 45%, rgba(28,20,18,0.25) 100%),
      linear-gradient(180deg, rgba(28,20,18,0.4) 0%, rgba(28,20,18,0) 30%, rgba(28,20,18,0.5) 100%);
  }
  .hero-grid {
    position: relative; z-index: 1;
    width: 100%;
  }
  .hero-content { max-width: 720px; }
  .hero-meta {
    display: flex; gap: var(--space-4); align-items: center;
    margin-bottom: var(--space-6);
  }
  .hero-meta .label { color: var(--color-cream); }
  .meta-line {
    width: 32px; height: 2px; background: var(--color-wine);
  }
  .hero h1 {
    font-family: var(--font-display);
    font-size: clamp(54px, 8vw, 104px);
    line-height: 0.92;
    letter-spacing: 1px;
    color: var(--color-cream);
    margin-bottom: var(--space-6);
    text-shadow: 0 2px 24px rgba(0,0,0,0.3);
  }
  .hero h1 .accent {
    color: var(--color-wine);
    display: inline-block;
    position: relative;
  }
  .hero-lead {
    font-size: 16px;
    color: rgba(250, 248, 243, 0.85);
    line-height: 1.6;
    max-width: 520px;
    margin-bottom: var(--space-8);
    text-shadow: 0 1px 12px rgba(0,0,0,0.4);
  }
  .hero-actions {
    display: flex; gap: var(--space-3);
    flex-wrap: wrap;
    margin-bottom: var(--space-12);
  }
  .hero-actions .btn {
    padding: 14px 26px;
    font-size: 14px;
  }
  .hero .btn-secondary {
    background: var(--color-cream);
    color: var(--color-charcoal);
    border: 1px solid var(--color-cream);
  }
  .hero .btn-secondary:hover { background: rgba(250, 248, 243, 0.85); }

  .hero-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-6);
    padding-top: var(--space-6);
    border-top: 1px solid rgba(250, 248, 243, 0.15);
    max-width: 600px;
  }
  .hero-stats .stat .num {
    font-family: var(--font-display);
    font-size: 42px;
    color: var(--color-cream);
    line-height: 1;
  }
  .hero-stats .stat .num .unit { color: var(--color-wine); font-size: 36px; }
  .hero-stats .stat .lbl {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: rgba(250, 248, 243, 0.65);
    margin-top: var(--space-2);
    font-weight: 600;
  }

  @keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 4px rgba(47, 82, 51, 0.18); }
    50% { box-shadow: 0 0 0 8px rgba(47, 82, 51, 0.08); }
  }

  @media (max-width: 880px) {
    .hero { padding: 120px 0 60px; min-height: 70vh; }
    .hero::after {
      background:
        linear-gradient(180deg, rgba(28,20,18,0.85) 0%, rgba(28,20,18,0.7) 60%, rgba(28,20,18,0.85) 100%);
    }
    .hero-stats { grid-template-columns: 1fr 1fr; gap: var(--space-4); }
  }
  @media (prefers-reduced-motion: reduce) {
    .hero-bg { transform: none !important; }
  }

  /* ============== STRIP ============== */
  .strip {
    background: var(--color-charcoal);
    padding: var(--space-6) 0;
    color: var(--color-disabled);
  }
  .strip-row {
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: var(--space-6);
  }
  .strip-item {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 2px;
    font-weight: 500;
    display: flex; align-items: center; gap: var(--space-2);
  }
  .strip-item .dot { width: 4px; height: 4px; background: var(--color-wine); border-radius: 50%; }

  /* ============== SECTIONS ============== */
  .section { padding: 80px 0; }
  .section-head {
    margin-bottom: var(--space-12);
    max-width: 760px;
  }
  .section-head.center { margin-left: auto; margin-right: auto; text-align: center; }
  .section-head .eyebrow {
    display: inline-flex; align-items: center; gap: var(--space-2);
    font-size: 11px; text-transform: uppercase; letter-spacing: 2px;
    color: var(--color-wine); font-weight: 600;
    margin-bottom: var(--space-4);
  }
  .section-head .eyebrow::before {
    content: ""; width: 24px; height: 1.5px; background: var(--color-wine);
  }
  .section-head h2 {
    font-family: var(--font-display);
    font-size: clamp(36px, 5vw, 56px);
    line-height: 1.0;
    letter-spacing: 0.5px;
    color: var(--color-charcoal);
    margin-bottom: var(--space-4);
  }
  .section-head p {
    font-size: 15px;
    color: var(--color-stone);
    max-width: 600px;
  }
  .section-head.center p { margin-left: auto; margin-right: auto; }

  /* ============== QUE VENDEMOS ============== */
  .lines-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-5);
  }
  .line {
    background: var(--color-cream);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: box-shadow 0.25s ease, border-color 0.25s ease;
    position: relative;
  }
  .line:hover {
    box-shadow: var(--shadow-lg);
    border-color: var(--color-wine);
  }
  .line-image {
    position: relative;
    aspect-ratio: 4 / 3;
    overflow: hidden;
    background: linear-gradient(135deg, var(--color-cream-dark) 0%, var(--color-border) 100%);
  }
  .line-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
    display: block;
  }
  .line:hover .line-image img { transform: scale(1.06); }
  .line-image::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(28,20,18,0) 50%, rgba(28,20,18,0.55) 100%);
    pointer-events: none;
  }
  .line-icon {
    position: absolute;
    top: var(--space-4); right: var(--space-4);
    width: 42px; height: 42px;
    background: var(--color-wine);
    display: flex; align-items: center; justify-content: center;
    border-radius: var(--radius-sm);
    z-index: 1;
    box-shadow: var(--shadow-md);
  }
  .line-icon svg { width: 20px; height: 20px; stroke: var(--color-cream); fill: none; stroke-width: 1.8; }
  .line-num {
    position: absolute;
    bottom: var(--space-3); left: var(--space-4);
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--color-cream);
    letter-spacing: 1px;
    z-index: 1;
    text-shadow: 0 1px 4px rgba(0,0,0,0.4);
  }
  .line-body {
    padding: var(--space-6);
    flex: 1;
    display: flex;
    flex-direction: column;
  }
  .line h3 {
    font-family: var(--font-display);
    font-size: 22px;
    letter-spacing: 1px;
    color: var(--color-charcoal);
    margin-bottom: var(--space-3);
  }
  .line p {
    font-size: 13px;
    color: var(--color-stone);
    line-height: 1.6;
    margin-bottom: var(--space-4);
    flex: 1;
  }
  .line-tags {
    display: flex; flex-wrap: wrap; gap: 6px;
  }
  .line-tag {
    display: inline-block;
    padding: 4px 10px;
    border-radius: var(--radius-pill);
    background: var(--color-cream-dark);
    color: var(--color-stone);
    font-size: 11px;
    font-weight: 500;
    border: 1px solid var(--color-border);
  }

  @media (max-width: 880px) {
    .lines-grid { grid-template-columns: 1fr; }
  }
  @media (min-width: 600px) and (max-width: 880px) {
    .lines-grid { grid-template-columns: repeat(2, 1fr); }
  }

  /* ============== CONTRATOS / VOLUMEN ============== */
  .contracts {
    background: var(--color-charcoal);
    color: var(--color-cream);
    padding: 100px 0;
    position: relative;
    overflow: hidden;
  }
  .contracts::before {
    content: "";
    position: absolute;
    top: 0; right: 0;
    width: 4px; height: 100%;
    background: var(--color-wine);
  }
  .contracts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-12);
    align-items: center;
  }
  .contracts h2 {
    font-family: var(--font-display);
    font-size: clamp(40px, 5.5vw, 64px);
    line-height: 1.0;
    color: var(--color-cream);
    letter-spacing: 1px;
    margin-bottom: var(--space-5);
  }
  .contracts h2 .yellow { color: var(--color-warning); }
  .contracts .eyebrow {
    color: var(--color-warning);
  }
  .contracts .eyebrow::before { background: var(--color-warning); }
  .contracts p {
    color: rgba(250, 248, 243, 0.75);
    font-size: 15px;
    line-height: 1.7;
    margin-bottom: var(--space-4);
  }
  .contracts-features {
    list-style: none;
    margin: var(--space-6) 0 var(--space-8);
    display: grid; gap: var(--space-3);
  }
  .contracts-features li {
    display: flex; align-items: flex-start; gap: var(--space-3);
    padding: var(--space-3) 0;
    border-bottom: 1px solid rgba(250, 248, 243, 0.08);
    font-size: 14px;
    color: var(--color-cream);
  }
  .contracts-features li::before {
    content: "→";
    color: var(--color-wine);
    font-weight: 700;
    font-size: 16px;
    flex-shrink: 0;
  }
  .contracts .btn-primary { background: var(--color-cream); color: var(--color-charcoal); }
  .contracts .btn-primary:hover { background: var(--color-warning); color: var(--color-charcoal); }

  .contracts-card {
    background: rgba(250, 248, 243, 0.04);
    border: 1px solid rgba(250, 248, 243, 0.12);
    padding: var(--space-8);
    border-radius: var(--radius-lg);
    backdrop-filter: blur(10px);
  }
  .contracts-card .label { color: var(--color-warning); margin-bottom: var(--space-5); }
  .contracts-numbers {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1px;
    background: rgba(250, 248, 243, 0.12);
    border-radius: var(--radius-md);
    overflow: hidden;
  }
  .num-block {
    background: var(--color-charcoal);
    padding: var(--space-6) var(--space-5);
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
    transition: background 0.25s ease;
  }
  .num-block:hover {
    background: #25180f;
  }
  .num-block .big {
    font-family: var(--font-display);
    font-size: 48px;
    color: var(--color-warning);
    line-height: 1;
    letter-spacing: 1px;
    display: flex;
    align-items: baseline;
    gap: 6px;
  }
  .num-block .desc {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: rgba(250, 248, 243, 0.6);
    font-weight: 600;
  }

  /* Stagger reveals */
  .lines-grid .line.reveal { transition-delay: calc(var(--i, 0) * 80ms); }
  .contracts-features li.reveal { transition-delay: calc(var(--i, 0) * 60ms); }
  .contracts-numbers .num-block.reveal { transition-delay: calc(var(--i, 0) * 100ms); }
  @media (max-width: 880px) {
    .contracts-grid { grid-template-columns: 1fr; gap: var(--space-8); }
    .contracts-card { padding: var(--space-5); }
  }

  /* ============== POR QUE NOSOTROS ============== */
  .why-bg { background: var(--color-cream-dark); }
  .why-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--space-4);
  }
  .why-card {
    background: var(--color-cream);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
    transition: all 0.2s ease;
  }
  .why-card:hover {
    box-shadow: var(--shadow-sm);
    transform: translateY(-2px);
  }
  .why-num {
    font-family: var(--font-display);
    font-size: 38px;
    color: var(--color-wine);
    line-height: 1;
    margin-bottom: var(--space-3);
    letter-spacing: 1px;
  }
  .why-card h4 {
    font-family: var(--font-ui);
    font-size: 15px;
    font-weight: 600;
    color: var(--color-charcoal);
    margin-bottom: var(--space-2);
  }
  .why-card p {
    font-size: 13px;
    color: var(--color-stone);
    line-height: 1.55;
  }
  @media (max-width: 880px) {
    .why-grid { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 540px) {
    .why-grid { grid-template-columns: 1fr; }
  }

  /* ============== TIENDAS ============== */
  .stores-section { padding: 80px 0; background: var(--color-cream); }
  .stores-grid {
    display: grid;
    grid-template-columns: 1fr 1.2fr;
    gap: var(--space-8);
    align-items: stretch;
  }
  .stores-list {
    display: grid;
    gap: var(--space-4);
    align-self: start;
  }
  .store-card {
    background: var(--color-cream);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: var(--space-5);
    transition: all 0.2s ease;
    cursor: pointer;
    position: relative;
  }
  .store-card:hover {
    border-color: var(--color-wine);
    box-shadow: var(--shadow-sm);
  }
  .store-card.active {
    border-color: var(--color-wine);
    background: var(--color-cream);
    box-shadow: var(--shadow-md);
  }
  .store-card.active::before {
    content: "";
    position: absolute;
    top: 0; left: 0; bottom: 0;
    width: 4px;
    background: var(--color-wine);
    border-radius: var(--radius-md) 0 0 var(--radius-md);
  }
  .store-head {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: var(--space-3);
  }
  .store-name {
    font-family: var(--font-display);
    font-size: 22px;
    letter-spacing: 1px;
    color: var(--color-charcoal);
  }
  .store-badge {
    background: rgba(47, 82, 51, 0.12);
    color: var(--color-success);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 4px 10px;
    border-radius: var(--radius-pill);
  }
  .store-info {
    display: grid; gap: var(--space-2);
    margin: var(--space-3) 0;
    font-size: 13px;
    color: var(--color-stone);
  }
  .store-info-row {
    display: flex; align-items: flex-start; gap: var(--space-3);
  }
  .store-info-row svg {
    width: 14px; height: 14px;
    stroke: var(--color-wine);
    fill: none; stroke-width: 1.8;
    margin-top: 2px;
    flex-shrink: 0;
  }
  .store-info-row .mono { color: var(--color-charcoal); font-weight: 500; }
  .store-actions {
    display: flex; gap: var(--space-2);
    margin-top: var(--space-4);
    padding-top: var(--space-4);
    border-top: 1px solid var(--color-border);
  }
  .store-actions .btn {
    flex: 1;
    padding: 8px 12px;
    font-size: 12px;
    justify-content: center;
  }

  .map-wrap {
    background: var(--color-charcoal);
    border-radius: var(--radius-lg);
    overflow: hidden;
    border: 1px solid var(--color-border);
    position: relative;
    min-height: 480px;
    display: flex;
    flex-direction: column;
  }
  .map-head {
    background: var(--color-charcoal);
    color: var(--color-cream);
    padding: var(--space-5);
    display: flex; justify-content: space-between; align-items: center;
    border-bottom: 1px solid rgba(250,248,243,0.08);
  }
  .map-head .label { color: var(--color-warning); margin-bottom: 2px; }
  .map-head h3 {
    font-family: var(--font-display);
    font-size: 20px;
    letter-spacing: 1.5px;
    color: var(--color-cream);
  }
  .map-head .pin-count {
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--color-cream);
    background: rgba(142, 30, 58, 0.4);
    padding: 4px 10px;
    border-radius: var(--radius-pill);
    border: 1px solid var(--color-wine);
  }
  .map-iframe {
    width: 100%;
    flex: 1;
    border: none;
    min-height: 420px;
  }

  @media (max-width: 880px) {
    .stores-grid { grid-template-columns: 1fr; }
    .map-wrap { min-height: 400px; }
  }

  /* ============== CONTACTO ============== */
  .contact-bg { background: var(--color-cream-dark); }
  .contact-grid {
    display: grid;
    grid-template-columns: 1.1fr 1fr;
    gap: var(--space-12);
    align-items: stretch;
  }
  .contact-form {
    background: var(--color-cream);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: var(--space-8);
  }
  .form-row {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: var(--space-4);
    margin-bottom: var(--space-4);
  }
  .form-group { display: flex; flex-direction: column; }
  .form-group.full { grid-column: 1/-1; }
  .form-label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--color-stone);
    margin-bottom: 6px;
  }
  .input, .select, .textarea {
    background: var(--color-cream);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    padding: 10px 12px;
    font-size: 14px;
    font-family: var(--font-ui);
    color: var(--color-charcoal);
    width: 100%;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
  }
  .input:focus, .select:focus, .textarea:focus {
    outline: none;
    border-color: var(--color-wine);
    box-shadow: 0 0 0 3px rgba(142, 30, 58, 0.1);
  }
  .input::placeholder, .textarea::placeholder { color: var(--color-stone-light); }
  .textarea { min-height: 110px; resize: vertical; }

  .form-foot {
    display: flex; gap: var(--space-3); align-items: center;
    margin-top: var(--space-5);
    flex-wrap: wrap;
  }
  .form-foot .btn-primary {
    flex: 1;
    padding: 14px 20px;
    justify-content: center;
    font-size: 14px;
  }
  .form-foot .small {
    font-size: 12px;
    color: var(--color-stone-light);
  }

  .contact-info {
    display: flex; flex-direction: column; gap: var(--space-4);
  }
  .contact-card {
    background: var(--color-cream);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
    transition: all 0.2s ease;
  }
  .contact-card.dark {
    background: var(--color-charcoal);
    border-color: var(--color-charcoal);
    color: var(--color-cream);
  }
  .contact-card.dark .label { color: var(--color-warning); }
  .contact-card.dark p { color: rgba(250,248,243,0.7); }
  .contact-card .label { margin-bottom: var(--space-3); }
  .contact-card h3 {
    font-family: var(--font-display);
    font-size: 26px;
    line-height: 1.1;
    letter-spacing: 1px;
    margin-bottom: var(--space-3);
    color: inherit;
  }
  .contact-card p {
    font-size: 13px;
    line-height: 1.6;
    color: var(--color-stone);
    margin-bottom: var(--space-4);
  }
  .contact-rows {
    display: grid; gap: var(--space-2);
    font-family: var(--font-mono);
    font-size: 13px;
  }
  .contact-rows a {
    display: flex; align-items: center; gap: var(--space-3);
    padding: var(--space-2) 0;
    color: inherit;
    transition: color 0.15s ease;
  }
  .contact-rows a:hover { color: var(--color-wine); }
  .contact-card.dark .contact-rows a:hover { color: var(--color-warning); }
  .contact-rows svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 1.8; flex-shrink: 0; }

  .wpp-card {
    background: #25D366;
    color: white;
    border-color: #25D366;
    padding: var(--space-6);
    border-radius: var(--radius-lg);
    display: flex; align-items: center; gap: var(--space-5);
    transition: all 0.2s ease;
  }
  .wpp-card:hover { background: #1eb858; transform: translateY(-2px); box-shadow: var(--shadow-md); }
  .wpp-icon {
    width: 56px; height: 56px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }
  .wpp-card h3 {
    font-family: var(--font-display);
    font-size: 24px;
    letter-spacing: 1px;
    color: white;
    margin-bottom: 2px;
  }
  .wpp-card p { color: rgba(255,255,255,0.9); font-size: 13px; }

  @media (max-width: 880px) {
    .contact-grid { grid-template-columns: 1fr; gap: var(--space-8); }
    .form-row { grid-template-columns: 1fr; }
    .contact-form { padding: var(--space-5); }
  }

  /* ============== FAQ ============== */
  .faq { max-width: 820px; margin: 0 auto; }
  .faq-item {
    border-bottom: 1px solid var(--color-border);
  }
  .faq-q {
    display: flex; justify-content: space-between; align-items: center;
    padding: var(--space-5) 0;
    cursor: pointer;
    font-family: var(--font-ui);
    font-size: 16px;
    font-weight: 600;
    color: var(--color-charcoal);
  }
  .faq-q::after {
    content: "+";
    font-size: 24px;
    color: var(--color-wine);
    font-weight: 300;
    transition: transform 0.25s ease;
    width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    background: var(--color-cream-dark);
    border-radius: 50%;
  }
  .faq-item.open .faq-q::after { transform: rotate(45deg); }
  .faq-a {
    max-height: 0; overflow: hidden;
    transition: max-height 0.4s ease, padding 0.4s ease;
    color: var(--color-stone);
    font-size: 14px;
    line-height: 1.7;
  }
  .faq-item.open .faq-a {
    max-height: 280px;
    padding-bottom: var(--space-5);
  }

  /* ============== CTA STRIP ============== */
  .cta-strip {
    background: var(--color-wine);
    color: var(--color-cream);
    padding: 60px 0;
  }
  .cta-strip-inner {
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: var(--space-6);
  }
  .cta-strip h2 {
    font-family: var(--font-display);
    font-size: clamp(32px, 5vw, 48px);
    line-height: 1;
    letter-spacing: 1px;
    color: var(--color-cream);
    max-width: 700px;
  }
  .cta-strip .btn {
    background: var(--color-cream);
    color: var(--color-charcoal);
    padding: 16px 28px;
    font-size: 14px;
  }
  .cta-strip .btn:hover { background: var(--color-charcoal); color: var(--color-cream); }

  /* ============== FOOTER ============== */
  footer {
    background: var(--color-charcoal);
    color: var(--color-cream);
    padding: 60px 0 var(--space-6);
  }
  .footer-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr 1fr 1fr;
    gap: var(--space-8);
    margin-bottom: var(--space-12);
  }
  .footer-brand .brand-text .name { color: var(--color-cream); }
  .footer-brand p {
    font-size: 13px;
    color: rgba(250,248,243,0.65);
    margin-top: var(--space-4);
    line-height: 1.7;
    max-width: 320px;
  }
  .footer-ruc {
    margin-top: var(--space-4);
    padding: var(--space-3) var(--space-4);
    background: rgba(250,248,243,0.05);
    border-left: 2px solid var(--color-wine);
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--color-cream);
    display: inline-block;
  }
  .footer-ruc span { color: rgba(250,248,243,0.55); display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 2px; font-family: var(--font-ui); font-weight: 600; }

  .footer-col h5 {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: var(--color-warning);
    margin-bottom: var(--space-5);
  }
  .footer-col ul { list-style: none; display: grid; gap: var(--space-3); }
  .footer-col a {
    font-size: 13px;
    color: rgba(250,248,243,0.7);
    transition: color 0.15s ease;
  }
  .footer-col a:hover { color: var(--color-cream); }

  .footer-bottom {
    border-top: 1px solid rgba(250,248,243,0.1);
    padding-top: var(--space-5);
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: var(--space-4);
    font-size: 12px;
    color: rgba(250,248,243,0.5);
  }
  .footer-bottom .mono { color: rgba(250,248,243,0.7); }
  .footer-staff {
    font-size: 11px;
    color: rgba(250,248,243,0.5);
    text-transform: uppercase;
    letter-spacing: 1.5px;
    transition: color 0.15s ease;
  }
  .footer-staff:hover { color: var(--color-warning); }
  .socials { display: flex; gap: var(--space-2); }
  .socials a {
    width: 36px; height: 36px;
    border-radius: var(--radius-sm);
    background: rgba(250,248,243,0.06);
    display: flex; align-items: center; justify-content: center;
    transition: all 0.15s ease;
    color: var(--color-cream);
  }
  .socials a:hover { background: var(--color-wine); }

  @media (max-width: 880px) {
    .footer-grid { grid-template-columns: 1fr 1fr; }
  }

  /* ============== FLOATING WHATSAPP ============== */
  .float-wpp {
    position: fixed;
    bottom: 24px; right: 24px;
    width: 60px; height: 60px;
    background: #25D366;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 8px 24px rgba(37, 211, 102, 0.4);
    z-index: 80;
    transition: all 0.2s ease;
    color: white;
  }
  .float-wpp:hover { transform: scale(1.1); }

  /* Reveal animation */
  .reveal { opacity: 0; transform: translateY(16px); transition: opacity 0.6s ease, transform 0.6s ease; }
  .reveal.in { opacity: 1; transform: translateY(0); }
</style>
</head>
<body>

<!-- ============== NAV ============== -->
<header class="nav">
  <div class="container nav-inner">
    <a href="#" class="brand">
      <div class="brand-mark"><span>M</span></div>
      <div class="brand-text">
        <div class="name">MORRAV</div>
        <div class="sub">OFFICE · S.A.C.</div>
      </div>
    </a>
    <nav>
      <ul class="nav-links">
        <li><a href="#lineas">Líneas</a></li>
        <li><a href="#contratos">Contratos</a></li>
        <li><a href="#tiendas">Tiendas</a></li>
        <li><a href="#contacto">Contacto</a></li>
      </ul>
    </nav>
    <div class="nav-actions">
      @auth
        <a href="{{ route('dashboard') }}" class="btn btn-ghost" title="Ir al panel interno">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
          Mi panel
        </a>
      @else
        <a href="{{ route('login') }}" class="btn btn-ghost" title="Acceso para personal de Morrav Office">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
          Iniciar sesión
        </a>
      @endauth
      <button class="menu-toggle" aria-label="Menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
    </div>
  </div>
</header>

<!-- ============== HERO ============== -->
<section class="hero">
  <div class="hero-bg" aria-hidden="true" style="background-image: url('{{ $asset('Hero-parallax.png') }}');"></div>
  <div class="container hero-grid">
    <div class="hero-content">
      <div class="hero-meta">
        <span class="meta-line"></span>
        <span class="label">Juliaca · Puno · Perú</span>
      </div>
      <h1>
        MOBILIARIO<br>
        QUE <span class="accent">SOSTIENE</span><br>
        TU NEGOCIO.
      </h1>
      <p class="hero-lead">
        Sillas, mesas y muebles para hogar, oficina, barberías, salones y exteriores.
        Trabajamos contigo en proyectos a la medida y contratos a volumen.
      </p>
      <div class="hero-actions">
        <a href="#contacto" class="btn btn-primary">
          Solicitar cotización
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
        </a>
        <a href="#tiendas" class="btn btn-secondary">
          Ver tiendas
        </a>
      </div>
      <div class="hero-stats">
        <div class="stat">
          <div class="num"><span class="unit">+</span>15</div>
          <div class="lbl">Años en Juliaca</div>
        </div>
        <div class="stat">
          <div class="num">3</div>
          <div class="lbl">Tiendas físicas</div>
        </div>
        <div class="stat">
          <div class="num"><span class="unit">+</span>500</div>
          <div class="lbl">Negocios atendidos</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============== STRIP ============== -->
<div class="strip">
  <div class="container strip-row">
    <div class="strip-item"><span class="dot"></span>Hogar</div>
    <div class="strip-item"><span class="dot"></span>Oficina</div>
    <div class="strip-item"><span class="dot"></span>Barberías</div>
    <div class="strip-item"><span class="dot"></span>Salones</div>
    <div class="strip-item"><span class="dot"></span>Exterior</div>
    <div class="strip-item"><span class="dot"></span>Comercios</div>
    <div class="strip-item"><span class="dot"></span>Hotelería</div>
  </div>
</div>

<!-- ============== LINEAS ============== -->
<section class="section" id="lineas">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">Líneas de trabajo</div>
      <h2>LO QUE FABRICAMOS<br>Y SUMINISTRAMOS</h2>
      <p>Atendemos seis líneas con criterio profesional. Cada espacio tiene una función distinta y nosotros entendemos las particularidades de cada uno.</p>
    </div>

    <div class="lines-grid">

      <div class="line">
        <div class="line-image">
          <img src="{{ $asset('lineas/hogar.jpg') }}"
               onerror="this.onerror=null; this.src='https://loremflickr.com/600/450/livingroom,furniture,interior?lock=1';"
               alt="Mobiliario para hogar" loading="lazy">
          <div class="line-icon">
            <svg viewBox="0 0 24 24"><path d="M3 9.5L12 3l9 6.5V21H3z"/><path d="M9 21V12h6v9"/></svg>
          </div>
          <div class="line-num">01 / 06</div>
        </div>
        <div class="line-body">
          <h3>HOGAR</h3>
          <p>Mobiliario residencial pensado para uso diario y descanso prolongado.</p>
          <div class="line-tags">
            <span class="line-tag">Comedores</span>
            <span class="line-tag">Salas</span>
            <span class="line-tag">Dormitorio</span>
          </div>
        </div>
      </div>

      <div class="line">
        <div class="line-image">
          <img src="{{ $asset('lineas/oficina.jpg') }}"
               onerror="this.onerror=null; this.src='https://loremflickr.com/600/450/office,desk,workplace?lock=2';"
               alt="Mobiliario de oficina" loading="lazy">
          <div class="line-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="14" rx="1"/><path d="M8 21h8M12 18v3"/></svg>
          </div>
          <div class="line-num">02 / 06</div>
        </div>
        <div class="line-body">
          <h3>OFICINA</h3>
          <p>Estaciones de trabajo ergonómicas, salas de reunión y áreas de recepción.</p>
          <div class="line-tags">
            <span class="line-tag">Escritorios</span>
            <span class="line-tag">Sillas ejecutivas</span>
            <span class="line-tag">Archiveros</span>
          </div>
        </div>
      </div>

      <div class="line">
        <div class="line-image">
          <img src="{{ $asset('lineas/barberias.jpg') }}"
               onerror="this.onerror=null; this.src='https://loremflickr.com/600/450/barbershop,barber?lock=3';"
               alt="Mobiliario para barberías" loading="lazy">
          <div class="line-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="M5 21l3-7M19 21l-3-7M9 13h6"/></svg>
          </div>
          <div class="line-num">03 / 06</div>
        </div>
        <div class="line-body">
          <h3>BARBERÍAS</h3>
          <p>Sillones hidráulicos, lavacabezas y mobiliario para experiencia premium.</p>
          <div class="line-tags">
            <span class="line-tag">Sillones</span>
            <span class="line-tag">Lavacabezas</span>
            <span class="line-tag">Espejos</span>
          </div>
        </div>
      </div>

      <div class="line">
        <div class="line-image">
          <img src="{{ $asset('lineas/salones.jpg') }}"
               onerror="this.onerror=null; this.src='https://loremflickr.com/600/450/beautysalon,makeup,vanity?lock=4';"
               alt="Mobiliario para salones de belleza" loading="lazy">
          <div class="line-icon">
            <svg viewBox="0 0 24 24"><path d="M12 2a4 4 0 014 4c0 4-4 8-4 8s-4-4-4-8a4 4 0 014-4z"/><path d="M5 22h14M8 18l4-4 4 4"/></svg>
          </div>
          <div class="line-num">04 / 06</div>
        </div>
        <div class="line-body">
          <h3>SALONES</h3>
          <p>Tocadores con iluminación, sillas de manicure, mobiliario para spa y estética.</p>
          <div class="line-tags">
            <span class="line-tag">Tocadores</span>
            <span class="line-tag">Manicure</span>
            <span class="line-tag">Pedicure</span>
          </div>
        </div>
      </div>

      <div class="line">
        <div class="line-image">
          <img src="{{ $asset('lineas/exterior.jpg') }}"
               onerror="this.onerror=null; this.src='https://loremflickr.com/600/450/patio,outdoor,terrace?lock=5';"
               alt="Mobiliario para exterior" loading="lazy">
          <div class="line-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M5 19l1.5-1.5M17.5 6.5L19 5"/></svg>
          </div>
          <div class="line-num">05 / 06</div>
        </div>
        <div class="line-body">
          <h3>EXTERIOR</h3>
          <p>Mobiliario tratado para resistir frío, lluvia y radiación. Hecho para durar.</p>
          <div class="line-tags">
            <span class="line-tag">Terraza</span>
            <span class="line-tag">Jardín</span>
            <span class="line-tag">Patios</span>
          </div>
        </div>
      </div>

      <div class="line">
        <div class="line-image">
          <img src="{{ $asset('lineas/comercios.jpg') }}"
               onerror="this.onerror=null; this.src='https://loremflickr.com/600/450/restaurant,hotel,interior?lock=6';"
               alt="Mobiliario para comercios" loading="lazy">
          <div class="line-icon">
            <svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V8l7-5 7 5v13"/><path d="M9 12h6M9 16h6"/></svg>
          </div>
          <div class="line-num">06 / 06</div>
        </div>
        <div class="line-body">
          <h3>COMERCIOS</h3>
          <p>Restaurantes, hoteles, retail y co-working: piezas robustas para tráfico intenso.</p>
          <div class="line-tags">
            <span class="line-tag">Restaurantes</span>
            <span class="line-tag">Hoteles</span>
            <span class="line-tag">Retail</span>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ============== CONTRATOS / VOLUMEN ============== -->
<section class="contracts" id="contratos">
  <div class="container contracts-grid">
    <div>
      <div class="eyebrow">Contratos · Volumen · Negocios</div>
      <h2>TE AYUDAMOS A<br><span class="yellow">CUMPLIR CONTRATOS</span></h2>
      <p>¿Ganaste una licitación o tienes un proyecto grande para abastecer? Trabajamos con empresas que necesitan suministro a volumen, con precios diferenciados y cronogramas de entrega comprometidos.</p>
      <ul class="contracts-features">
        <li>Precios especiales por volumen y proyectos repetitivos</li>
        <li>Capacidad de fabricación escalonada con cronograma de entrega</li>
        <li>Boletas y facturas electrónicas, condiciones de crédito acordadas</li>
        <li>Atención dedicada para hoteles, restaurantes, instituciones y cadenas</li>
      </ul>
      <a href="#contacto" class="btn btn-primary">
        Conversemos tu contrato
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
      </a>
    </div>

    <div class="contracts-card">
      <div class="label">Lo que respaldamos</div>
      <div class="contracts-numbers">
        <div class="num-block">
          <div class="big">
            <span class="value" data-target="100" data-suffix="%">0</span>
          </div>
          <div class="desc">Primera calidad</div>
        </div>
        <div class="num-block">
          <div class="big">
            <span class="value" data-target="5" data-prefix="+">0</span>
          </div>
          <div class="desc">Regiones del sur</div>
        </div>
        <div class="num-block">
          <div class="big">
            <span class="value" data-target="3">0</span>
          </div>
          <div class="desc">Tiendas físicas</div>
        </div>
        <div class="num-block">
          <div class="big">
            <span class="value" data-target="15" data-suffix="+">0</span>
          </div>
          <div class="desc">Años en Juliaca</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============== POR QUE NOSOTROS ============== -->
<section class="section why-bg">
  <div class="container">
    <div class="section-head center">
      <div class="eyebrow">Por qué Morrav</div>
      <h2>SOMOS DE JULIACA.<br>TRABAJAMOS COMO TAL.</h2>
      <p>Empresa local con respaldo, atención directa con el dueño, y respeto por el dinero del cliente.</p>
    </div>
    <div class="why-grid">
      <div class="why-card">
        <div class="why-num">01</div>
        <h4>Atención local</h4>
        <p>Tres tiendas en la ciudad. Te recibimos personalmente, no eres un número.</p>
      </div>
      <div class="why-card">
        <div class="why-num">02</div>
        <h4>Garantía real</h4>
        <p>Si algo falla, lo arreglamos. Servicio postventa con repuestos siempre disponibles.</p>
      </div>
      <div class="why-card">
        <div class="why-num">03</div>
        <h4>Precio según uso</h4>
        <p>Cotización ajustada a tu proyecto. Precios diferenciados por volumen.</p>
      </div>
      <div class="why-card">
        <div class="why-num">04</div>
        <h4>Asesoría sin costo</h4>
        <p>Te ayudamos a elegir el mobiliario correcto sin compromiso de compra.</p>
      </div>
    </div>
  </div>
</section>

<!-- ============== TIENDAS + MAPA ============== -->
<section class="stores-section" id="tiendas">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">Nuestras tiendas</div>
      <h2>TRES PUNTOS DE ATENCIÓN<br>EN JULIACA</h2>
      <p>Ven a nuestras tiendas: ahí puedes ver, tocar y conversar el mobiliario que te interesa. Atención de lunes a sábado.</p>
    </div>

    <div class="stores-grid">

      <div class="stores-list">
        @foreach ($stores as $i => $store)
          <div class="store-card{{ $i === 0 ? ' active' : '' }}" data-coords="{{ $store['lat'] }},{{ $store['lng'] }}">
            <div class="store-head">
              <div class="store-name">{{ $store['name'] }}</div>
              <span class="store-badge">{{ $store['badge'] }}</span>
            </div>
            <div class="store-info">
              <div class="store-info-row">
                <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <span class="mono">{{ $store['address'] }}</span>
              </div>
              <div class="store-info-row">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                <span>{{ $store['hours'] }}</span>
              </div>
              @if (! empty($store['phone']))
                <div class="store-info-row">
                  <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
                  <span class="mono">{{ $store['phone'] }}</span>
                </div>
              @endif
            </div>
            <div class="store-actions">
              @if (($store['show_whatsapp'] ?? false) && filled($store['whatsapp']))
                <a href="https://wa.me/{{ $store['whatsapp'] }}" target="_blank" rel="noopener" class="btn btn-primary">WhatsApp</a>
              @endif
              <a href="https://maps.google.com/?q={{ $store['lat'] }},{{ $store['lng'] }}" target="_blank" rel="noopener" class="btn btn-secondary">Cómo llegar</a>
            </div>
          </div>
        @endforeach
      </div>

      @php
        $count = count($stores);
        $puntosLabel = match ($count) {
            1 => '1 PUNTO EN LA CIUDAD',
            2 => '2 PUNTOS EN LA CIUDAD',
            default => '3 PUNTOS EN LA CIUDAD',
        };
        $tiendasLabel = $count === 1 ? '1 TIENDA' : "{$count} TIENDAS";
        $bboxLat = $primaryStore['lat'];
        $bboxLng = $primaryStore['lng'];
        $bbox = ($bboxLng - 0.030).'%2C'.($bboxLat - 0.018).'%2C'.($bboxLng + 0.030).'%2C'.($bboxLat + 0.017);
      @endphp
      <div class="map-wrap">
        <div class="map-head">
          <div>
            <div class="label">Ubicación · Juliaca</div>
            <h3>{{ $puntosLabel }}</h3>
          </div>
          <div class="pin-count mono">{{ $tiendasLabel }}</div>
        </div>
        <iframe
          class="map-iframe"
          src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bbox }}&amp;layer=mapnik&amp;marker={{ $bboxLat }}%2C{{ $bboxLng }}"
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          title="Mapa de tiendas Morrav Office en Juliaca">
        </iframe>
      </div>

    </div>
  </div>
</section>

<!-- ============== CONTACTO ============== -->
<section class="section contact-bg" id="contacto">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">Contacto · Cotización</div>
      <h2>CONVERSEMOS<br>SOBRE TU PROYECTO</h2>
      <p>Mientras más detalles compartas, más exacta será la cotización. Toda la información es confidencial y se usa solo para tu propuesta.</p>
    </div>

    <div class="contact-grid">

      <form class="contact-form" onsubmit="handleQuote(event)">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="name">Nombre completo</label>
            <input class="input" type="text" id="name" required placeholder="Tu nombre y apellido" />
          </div>
          <div class="form-group">
            <label class="form-label" for="company">Empresa o negocio</label>
            <input class="input" type="text" id="company" placeholder="Opcional" />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="phone">Teléfono / WhatsApp</label>
            <input class="input" type="tel" id="phone" required placeholder="+51 9XX XXX XXX" />
          </div>
          <div class="form-group">
            <label class="form-label" for="email">Correo electrónico</label>
            <input class="input" type="email" id="email" required placeholder="tu@correo.com" />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="line">Línea de interés</label>
            <select class="select" id="line" required>
              <option value="">Selecciona...</option>
              <option>Hogar</option>
              <option>Oficina</option>
              <option>Barbería</option>
              <option>Salón de belleza</option>
              <option>Exterior</option>
              <option>Comercios / Hotelería / Restaurantes</option>
              <option>Contrato a volumen</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="qty">Volumen aproximado</label>
            <select class="select" id="qty">
              <option value="">Selecciona...</option>
              <option>1 a 5 piezas</option>
              <option>6 a 20 piezas</option>
              <option>21 a 100 piezas</option>
              <option>+100 piezas</option>
              <option>Aún por definir</option>
            </select>
          </div>
        </div>
        <div class="form-group full" style="margin-bottom: var(--space-3);">
          <label class="form-label" for="msg">Cuéntanos tu proyecto</label>
          <textarea class="textarea" id="msg" required placeholder="Describe lo que necesitas, plazos estimados, lugar de entrega..."></textarea>
        </div>
        <div class="form-foot">
          <button type="submit" class="btn btn-primary">
            Enviar solicitud
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
          </button>
          <span class="small">Respuesta en menos de 24h hábiles</span>
        </div>
      </form>

      <div class="contact-info">

        <a href="https://wa.me/{{ $whatsappMain }}?text=Hola%2C%20quiero%20cotizar%20muebles%20en%20Morrav%20Office" target="_blank" rel="noopener" class="wpp-card">
          <div class="wpp-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="white">
              <path d="M17.5 14.4c-.3-.1-1.7-.8-2-.9-.3-.1-.4-.1-.6.1-.2.3-.7.9-.9 1.1-.2.2-.3.2-.6.1-.3-.1-1.2-.5-2.3-1.4-.9-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6.1-.1.3-.3.4-.5.1-.2.2-.3.3-.5.1-.2.1-.4 0-.5-.1-.1-.6-1.5-.9-2-.2-.5-.5-.5-.6-.5h-.5c-.2 0-.5.1-.7.3-.3.3-1 1-1 2.4s1 2.8 1.2 3c.1.2 2 3.2 5 4.5 2.5 1 3 .8 3.6.8.6-.1 1.7-.7 2-1.4.3-.7.3-1.3.2-1.4 0-.1-.2-.1-.5-.3zM12 2C6.5 2 2 6.5 2 12c0 1.7.4 3.4 1.3 4.9L2 22l5.3-1.4c1.4.8 3 1.2 4.7 1.2 5.5 0 10-4.5 10-10S17.5 2 12 2zm0 18.3c-1.5 0-3-.4-4.3-1.2l-.3-.2-3.2.8.9-3.1-.2-.3C4 15 3.5 13.5 3.5 12c0-4.7 3.8-8.5 8.5-8.5s8.5 3.8 8.5 8.5-3.8 8.3-8.5 8.3z"/>
            </svg>
          </div>
          <div>
            <h3>WHATSAPP DIRECTO</h3>
            <p>Atención inmediata · {{ $whatsappMainDisplay }}</p>
          </div>
        </a>

        <div class="contact-card dark">
          <div class="label">Correo comercial</div>
          <h3>ESCRÍBENOS</h3>
          <div class="contact-rows">
            <a href="mailto:{{ $emailSales }}">
              <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="M22 6l-10 7L2 6"/></svg>
              {{ $emailSales }}
            </a>
            @if (filled($emailContracts))
              <a href="mailto:{{ $emailContracts }}">
                <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="M22 6l-10 7L2 6"/></svg>
                {{ $emailContracts }}
              </a>
            @endif
          </div>
        </div>

        <div class="contact-card">
          <div class="label">Datos de empresa</div>
          <h3>MORRAV OFFICE S.A.C.</h3>
          <div class="contact-rows" style="color: var(--color-stone);">
            <div style="display: flex; align-items: center; gap: var(--space-3); padding: var(--space-2) 0;">
              <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:var(--color-wine);fill:none;stroke-width:1.8;flex-shrink:0;"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6v6H9z"/></svg>
              <span class="mono" style="color:var(--color-charcoal); font-weight: 500;">RUC 20601188661</span>
            </div>
            <div style="display: flex; align-items: center; gap: var(--space-3); padding: var(--space-2) 0;">
              <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:var(--color-wine);fill:none;stroke-width:1.8;flex-shrink:0;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <span style="color:var(--color-charcoal);">Juliaca · Puno · Perú</span>
            </div>
            <div style="display: flex; align-items: center; gap: var(--space-3); padding: var(--space-2) 0;">
              <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:var(--color-wine);fill:none;stroke-width:1.8;flex-shrink:0;"><path d="M3 12h18M12 3v18"/></svg>
              <span style="color:var(--color-charcoal);">Boletas y facturas electrónicas</span>
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>
</section>

<!-- ============== FAQ ============== -->
<section class="section">
  <div class="container">
    <div class="section-head center">
      <div class="eyebrow">Preguntas frecuentes</div>
      <h2>LO QUE NOS PREGUNTAN<br>CASI SIEMPRE</h2>
    </div>
    <div class="faq">
      <div class="faq-item">
        <div class="faq-q">¿Por qué no muestran precios en la web?</div>
        <div class="faq-a"><p>Porque cada proyecto es distinto: cantidad, materiales, acabados, tiempos de entrega y volumen cambian el precio. Cotizamos uno por uno para darte el precio más justo y competitivo, sin precios inflados de catálogo.</p></div>
      </div>
      <div class="faq-item">
        <div class="faq-q">¿Atienden solo en Juliaca o entregan a otras ciudades?</div>
        <div class="faq-a"><p>Tenemos las tres tiendas físicas en Juliaca, pero entregamos a todo el sur del Perú: Puno, Arequipa, Cusco, Tacna y Moquegua. Para envíos a otras zonas coordinamos por agencia.</p></div>
      </div>
      <div class="faq-item">
        <div class="faq-q">¿Manejan precios diferenciados para volumen?</div>
        <div class="faq-a"><p>Sí. Si tienes un proyecto de hotel, restaurante, oficina corporativa, o necesitas abastecer un contrato con condiciones específicas, te damos precios especiales. Conversa con nuestro ejecutivo.</p></div>
      </div>
      <div class="faq-item">
        <div class="faq-q">¿Emiten factura electrónica?</div>
        <div class="faq-a"><p>Sí. Emitimos boletas y facturas electrónicas conforme a SUNAT. Para clientes con condiciones de crédito empresarial, evaluamos cada caso.</p></div>
      </div>
      <div class="faq-item">
        <div class="faq-q">¿Cuánto demora una entrega?</div>
        <div class="faq-a"><p>Para mobiliario en stock: entre 1 y 5 días en Juliaca. Para fabricación a medida o pedidos grandes: entre 2 y 8 semanas según volumen. La cotización siempre incluye fecha comprometida.</p></div>
      </div>
    </div>
  </div>
</section>

<!-- ============== CTA STRIP ============== -->
<section class="cta-strip">
  <div class="container cta-strip-inner">
    <h2>¿LISTO PARA<br>CONVERSAR TU PROYECTO?</h2>
    <a href="#contacto" class="btn">
      Solicitar cotización
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
    </a>
  </div>
</section>

<!-- ============== FOOTER ============== -->
<footer>
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="#" class="brand">
          <div class="brand-mark"><span>M</span></div>
          <div class="brand-text">
            <div class="name">MORRAV</div>
            <div class="sub" style="color: var(--color-warning);">OFFICE · S.A.C.</div>
          </div>
        </a>
        <p>Mobiliario para hogar, oficina, comercios y proyectos a volumen. Empresa de Juliaca con 15 años de respaldo.</p>
        <div class="footer-ruc">
          <span>RUC</span>
          20601188661
        </div>
      </div>

      <div class="footer-col">
        <h5>Líneas</h5>
        <ul>
          <li><a href="#lineas">Hogar</a></li>
          <li><a href="#lineas">Oficina</a></li>
          <li><a href="#lineas">Barberías</a></li>
          <li><a href="#lineas">Salones</a></li>
          <li><a href="#lineas">Exterior</a></li>
          <li><a href="#lineas">Comercios</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h5>Empresa</h5>
        <ul>
          <li><a href="#contratos">Contratos a volumen</a></li>
          <li><a href="#tiendas">Tiendas en Juliaca</a></li>
          <li><a href="#contacto">Cotizar proyecto</a></li>
          <li><a href="https://wa.me/{{ $whatsappMain }}" target="_blank" rel="noopener">WhatsApp directo</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h5>Contacto</h5>
        <ul>
          <li><a href="mailto:{{ $emailSales }}">{{ $emailSales }}</a></li>
          <li><a href="tel:+{{ $whatsappMain }}">{{ $whatsappMainDisplay }}</a></li>
          <li><a href="#tiendas">{{ $primaryStore['address'] }}</a></li>
          <li><a href="#tiendas">Juliaca · Puno · Perú</a></li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <div>© <span class="mono">{{ date('Y') }}</span> Morrav Office S.A.C. · Todos los derechos reservados.</div>
      <div style="display:flex; align-items:center; gap: var(--space-5); flex-wrap: wrap;">
        @auth
          <a href="{{ route('dashboard') }}" class="footer-staff">Panel interno →</a>
        @else
          <a href="{{ route('login') }}" class="footer-staff">Acceso personal →</a>
        @endauth
        <div class="socials">
          <a href="#" aria-label="Facebook">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 10-11.5 9.9V14.9H8v-2.9h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.7l-.4 2.9h-2.3v7C18 21 22 17 22 12z"/></svg>
          </a>
          <a href="#" aria-label="Instagram">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="18" cy="6" r="1" fill="currentColor"/></svg>
          </a>
          <a href="#" aria-label="TikTok">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19 8.5a6.7 6.7 0 01-3.9-1.2v6.7a5.6 5.6 0 11-5.6-5.6c.3 0 .6 0 .9.1v3a2.7 2.7 0 102 2.6V2h2.9a4 4 0 003.7 3.6V8.5z"/></svg>
          </a>
        </div>
      </div>
    </div>
  </div>
</footer>

<!-- Floating WhatsApp -->
<a class="float-wpp" href="https://wa.me/{{ $whatsappMain }}?text=Hola%20Morrav%20Office%2C%20quiero%20cotizar" target="_blank" rel="noopener" aria-label="WhatsApp">
  <svg width="28" height="28" viewBox="0 0 24 24" fill="white">
    <path d="M17.5 14.4c-.3-.1-1.7-.8-2-.9-.3-.1-.4-.1-.6.1-.2.3-.7.9-.9 1.1-.2.2-.3.2-.6.1-.3-.1-1.2-.5-2.3-1.4-.9-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6.1-.1.3-.3.4-.5.1-.2.2-.3.3-.5.1-.2.1-.4 0-.5-.1-.1-.6-1.5-.9-2-.2-.5-.5-.5-.6-.5h-.5c-.2 0-.5.1-.7.3-.3.3-1 1-1 2.4s1 2.8 1.2 3c.1.2 2 3.2 5 4.5 2.5 1 3 .8 3.6.8.6-.1 1.7-.7 2-1.4.3-.7.3-1.3.2-1.4 0-.1-.2-.1-.5-.3zM12 2C6.5 2 2 6.5 2 12c0 1.7.4 3.4 1.3 4.9L2 22l5.3-1.4c1.4.8 3 1.2 4.7 1.2 5.5 0 10-4.5 10-10S17.5 2 12 2z"/>
  </svg>
</a>

<script>
  // Nav: cambia a estado "scrolled" cuando el usuario pasa el hero
  (() => {
    const nav = document.querySelector('.nav');
    if (!nav) return;
    const threshold = 60;
    let ticking = false;
    function check() {
      nav.classList.toggle('scrolled', window.scrollY > threshold);
      ticking = false;
    }
    check();
    window.addEventListener('scroll', () => {
      if (!ticking) { requestAnimationFrame(check); ticking = true; }
    }, { passive: true });
  })();

  // Hero parallax (rAF + transform — funciona en iOS, sin background-attachment fixed)
  (() => {
    const heroBg = document.querySelector('.hero-bg');
    if (!heroBg || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const hero = document.querySelector('.hero');
    let lastY = 0;
    let ticking = false;

    function update() {
      const rect = hero.getBoundingClientRect();
      // Solo animar mientras el hero esté visible
      if (rect.bottom > 0 && rect.top < window.innerHeight) {
        const offset = lastY * 0.35;
        heroBg.style.transform = `translate3d(0, ${offset}px, 0)`;
      }
      ticking = false;
    }

    window.addEventListener('scroll', () => {
      lastY = window.scrollY;
      if (!ticking) {
        requestAnimationFrame(update);
        ticking = true;
      }
    }, { passive: true });
  })();

  // FAQ accordion
  document.querySelectorAll('.faq-item').forEach(item => {
    item.querySelector('.faq-q').addEventListener('click', () => {
      const wasOpen = item.classList.contains('open');
      document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
      if (!wasOpen) item.classList.add('open');
    });
  });

  // Store cards interaction (highlight + map sync)
  const storeCards = document.querySelectorAll('.store-card');
  const mapIframe = document.querySelector('.map-iframe');
  storeCards.forEach(card => {
    card.addEventListener('click', (e) => {
      if (e.target.closest('a, button')) return;
      storeCards.forEach(c => c.classList.remove('active'));
      card.classList.add('active');
      const coords = card.dataset.coords;
      if (coords && mapIframe) {
        const [lat, lon] = coords.split(',');
        const lonNum = parseFloat(lon);
        const latNum = parseFloat(lat);
        const bbox = `${lonNum - 0.012},${latNum - 0.008},${lonNum + 0.012},${latNum + 0.008}`;
        mapIframe.src = `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${lat},${lon}`;
      }
    });
  });

  // Stagger index para grupos (CSS lee --i para calcular transition-delay)
  document.querySelectorAll('.lines-grid .line').forEach((el, i) => el.style.setProperty('--i', i));
  document.querySelectorAll('.contracts-features li').forEach((el, i) => el.style.setProperty('--i', i));
  document.querySelectorAll('.contracts-numbers .num-block').forEach((el, i) => el.style.setProperty('--i', i));

  // Reveal on scroll
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) entry.target.classList.add('in');
    });
  }, { threshold: 0.12 });
  document.querySelectorAll('.line, .why-card, .store-card, .stat, .section-head, .num-block, .contracts-features li, .contracts-card, .faq-item').forEach(el => {
    el.classList.add('reveal');
    observer.observe(el);
  });

  // Counter animado para los números de "contratos" — anima 0 → target con ease-out cubic
  (() => {
    function animate(el) {
      const target = parseInt(el.dataset.target, 10);
      const prefix = el.dataset.prefix || '';
      const suffix = el.dataset.suffix || '';
      const duration = 1200;
      const start = performance.now();
      function tick(now) {
        const t = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - t, 3);
        const value = Math.floor(eased * target);
        el.textContent = `${prefix}${value}${suffix}`;
        if (t < 1) requestAnimationFrame(tick);
        else el.textContent = `${prefix}${target}${suffix}`;
      }
      requestAnimationFrame(tick);
    }
    const counterIO = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting && !entry.target.dataset.animated) {
          entry.target.dataset.animated = '1';
          animate(entry.target);
        }
      });
    }, { threshold: 0.5 });
    document.querySelectorAll('.num-block .value[data-target]').forEach(el => counterIO.observe(el));
  })();

  // Form handler (demo): hasta que conectes endpoint real
  function handleQuote(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.innerHTML = 'Enviando...';
    btn.disabled = true;
    setTimeout(() => {
      btn.innerHTML = '✓ Solicitud enviada';
      btn.style.background = 'var(--color-success)';
      setTimeout(() => {
        e.target.reset();
        btn.innerHTML = original;
        btn.style.background = '';
        btn.disabled = false;
      }, 2500);
    }, 900);
  }

  // Mobile menu (basic)
  const menuToggle = document.querySelector('.menu-toggle');
  if (menuToggle) {
    menuToggle.addEventListener('click', () => {
      const links = document.querySelector('.nav-links');
      const isOpen = links.style.display === 'flex';
      Object.assign(links.style, isOpen ? { display: 'none' } : {
        display: 'flex',
        position: 'absolute',
        top: '76px', left: 0, right: 0,
        flexDirection: 'column',
        background: 'var(--color-cream)',
        padding: 'var(--space-5)',
        borderBottom: '1px solid var(--color-border)',
        gap: 'var(--space-4)'
      });
    });
  }
</script>

</body>
</html>
