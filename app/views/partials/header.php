<?php require_once dirname(__FILE__, 3) . '/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?><?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preload" as="script" href="https://cdn.jsdelivr.net/npm/sheetjs@0.18.5/dist/xlsx.full.min.js">
    <style>
        :root {
            --primary: #F98B2A;
            --primary-dark: #E7751B;
            --primary-light: #FFB545;
            --primary-soft: #FFF3E6;
            --primary-gradient: #F98B2A;
            --btn-primary-solid: #F98B2A;
            --btn-primary-solid-hover: #E7751B;
            --btn-black: #000000;
            --btn-black-hover: #1a1a1a;
            --accent: #F98B2A;
            --accent-light: #FFB545;
            --sidebar: #0b0b0f;
            --sidebar-hover: #1a1a22;
            --sidebar-item: #ffffff;
            --sidebar-muted: #8b8ba3;
            --bg: #f4f5f9;
            --card-bg: #ffffff;
            --text: #1a1d2e;
            --text-muted: #757a91;
            --border: #eaecf3;
            --success: #10b981;
            --success-light: #d1fae5;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --info: #3b82f6;
            --info-light: #dbeafe;
            --shadow-sm: 0 1px 3px rgba(17, 20, 43, 0.04), 0 1px 2px rgba(17, 20, 43, 0.03);
            --shadow: 0 10px 30px rgba(17, 20, 43, 0.06);
            --shadow-lg: 0 20px 60px rgba(249, 139, 42, 0.18);
            --radius: 20px;
            --radius-sm: 14px;
        }
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); overflow-x: hidden; }
        .wrapper { display: flex; min-height: 100vh; position: relative; }

        /* ===== SIDEBAR (Icon + Text Labels, Orange Accent) ===== */
        .sidebar {
            width: 252px; background: var(--sidebar);
            min-height: 100vh; position: fixed; left: 0; top: 0; z-index: 1000;
            display: flex; flex-direction: column; align-items: stretch;
            padding: 22px 16px 18px 16px; gap: 6px;
            transition: width 0.3s ease, transform 0.3s ease;
        }
        .sidebar-brand {
            display: flex; align-items: center; gap: 12px;
            padding: 0 8px 18px 8px; border-bottom: 1px solid rgba(255,255,255,0.06);
            margin-bottom: 10px;
        }
        .sidebar-brand-logo {
            width: 44px; height: 44px; border-radius: 14px;
            background: var(--btn-primary-solid);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1.25rem; font-weight: 900;
            box-shadow: 0 6px 16px rgba(249, 139, 42, 0.28);
        }
        .sidebar-brand-text { display: flex; flex-direction: column; line-height: 1.1; }
        .sidebar-brand-text .bname {
            font-weight: 800; color: #fff; font-size: 1.05rem; letter-spacing: -0.02em;
        }
        .sidebar-brand-text .bsub {
            font-weight: 500; color: var(--sidebar-muted); font-size: 0.68rem; letter-spacing: 0.12em; text-transform: uppercase;
        }

        .sidebar-section-label {
            font-size: 0.68rem; color: var(--sidebar-muted); font-weight: 700;
            letter-spacing: 0.14em; text-transform: uppercase;
            padding: 12px 12px 6px 12px;
        }

        .sidebar-nav { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 2px; width: 100%; align-items: stretch; }
        .sidebar-nav .nav-link {
            width: 100%; min-height: 48px; border-radius: 14px;
            display: flex; align-items: center; gap: 14px;
            padding: 10px 14px;
            color: var(--sidebar-muted); text-decoration: none;
            font-size: 0.9rem; font-weight: 500; position: relative;
            transition: all 0.2s ease;
        }
        .sidebar-nav .nav-link .nav-icon {
            width: 28px; display: flex; justify-content: center; align-items: center; font-size: 1.02rem; flex-shrink: 0;
        }
        .sidebar-nav .nav-link .nav-label {
            flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .sidebar-nav .nav-link .nav-badge {
            background: var(--danger); color: #fff; border-radius: 50px;
            font-size: 0.62rem; font-weight: 700; padding: 2px 7px; min-width: 18px;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .sidebar-nav .nav-link:hover {
            background: var(--sidebar-hover); color: #fff;
        }
        .sidebar-nav .nav-link.active {
            background: var(--btn-primary-solid);
            color: #fff;
            box-shadow: 0 6px 18px rgba(249, 139, 42, 0.28);
        }
        .sidebar-nav .nav-link.active::before {
            content: ''; position: absolute; left: -16px; top: 50%;
            transform: translateY(-50%); width: 4px; height: 26px;
            background: #fff; border-radius: 0 4px 4px 0;
            opacity: 0.95;
        }
        .sidebar-spacer { flex: 1; }
        .sidebar-bottom {
            display: flex; flex-direction: column; gap: 4px; align-items: stretch; width: 100%;
            border-top: 1px solid rgba(255,255,255,0.06); padding-top: 10px;
        }
        .sidebar-user {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 12px; border-radius: 14px; margin-bottom: 6px;
            transition: background 0.2s;
        }
        .sidebar-user:hover { background: var(--sidebar-hover); }
        .avatar-xl {
            width: 42px; height: 42px; border-radius: 14px; overflow: hidden; flex-shrink: 0;
            background: var(--btn-primary-solid);
            display: flex; align-items: center; justify-content: center; color: #fff;
            font-weight: 800; font-size: 0.88rem;
        }
        .sidebar-user-text { display: flex; flex-direction: column; line-height: 1.25; }
        .sidebar-user-text .uname { color: #fff; font-weight: 700; font-size: 0.85rem; }
        .sidebar-user-text .urole { color: var(--sidebar-muted); font-weight: 500; font-size: 0.72rem; }

        .sidebar-toggle-btn {
            width: 40px; height: 40px; border-radius: 12px;
            display: none; align-items: center; justify-content: center;
            color: #fff; background: rgba(255,255,255,0.06); border: none; cursor: pointer;
            font-size: 1.05rem; margin-bottom: 8px; align-self: flex-end;
        }
        .sidebar-toggle-btn:hover { background: var(--sidebar-hover); }

        .main { flex: 1; margin-left: 252px; min-height: 100vh; transition: margin-left 0.3s ease; }

        /* ===== TOPBAR ===== */
        .topbar {
            background: var(--bg); padding: 20px 32px 8px 32px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 100;
        }
        .welcome-area { display: flex; align-items: center; gap: 14px; }
        .welcome-text h6 {
            margin: 0; font-weight: 500; font-size: 0.88rem; color: var(--text-muted);
            display: flex; align-items: center; gap: 8px;
        }
        .welcome-text h6 .wave { font-size: 1rem; }
        .welcome-text h2 { margin: 2px 0 0; font-weight: 800; font-size: 1.55rem; color: var(--text); letter-spacing: -0.02em; }

        .topbar-right { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }

        .date-chip {
            background: #fff; border-radius: 14px; padding: 10px 16px;
            box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 10px;
            font-size: 0.82rem; font-weight: 600; color: var(--text); cursor: pointer; border: 1px solid var(--border);
        }
        .date-chip i { color: var(--primary); font-size: 0.9rem; }

        .search-box { position: relative; }
        .search-box input {
            width: 280px; padding: 11px 18px 11px 42px; border-radius: 16px;
            border: 1px solid var(--border); background: #fff; font-size: 0.85rem; color: var(--text);
            box-shadow: var(--shadow-sm); transition: all 0.2s;
        }
        .search-box input::placeholder { color: var(--text-muted); }
        .search-box input:focus { outline: none; box-shadow: 0 0 0 3px rgba(249,139,42,0.12); border-color: rgba(249,139,42,0.3); }
        .search-box i {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); font-size: 0.9rem;
        }

        .icon-btn {
            width: 44px; height: 44px; border-radius: 14px; border: 1px solid var(--border);
            background: #fff; display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.2s; color: var(--text-muted); position: relative;
            box-shadow: var(--shadow-sm);
        }
        .icon-btn:hover {
            background: var(--btn-primary-solid); color: #fff; transform: translateY(-2px);
            border-color: transparent;
            box-shadow: 0 8px 20px rgba(249, 139, 42, 0.22);
        }
        .icon-btn .badge {
            position: absolute; top: 6px; right: 6px; background: var(--danger); color: #fff;
            border-radius: 50%; min-width: 18px; height: 18px; font-size: 0.62rem;
            font-weight: 700; display: flex; align-items: center; justify-content: center; padding: 0 5px;
            border: 2px solid #fff;
        }
        .user-chip {
            display: flex; align-items: center; gap: 10px;
            padding: 4px 14px 4px 4px;
            background: #fff; border-radius: 100px; box-shadow: var(--shadow-sm);
            border: 1px solid var(--border); cursor: pointer; transition: all 0.2s;
        }
        .user-chip:hover { box-shadow: var(--shadow); border-color: rgba(249,139,42,0.28); }
        .user-chip .avatar-mini {
            width: 36px; height: 36px; border-radius: 50%;
            background: var(--btn-primary-solid); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 0.8rem;
        }
        .user-chip-text { display: flex; flex-direction: column; line-height: 1.2; }
        .user-chip-text .uname { font-size: 0.82rem; font-weight: 700; color: var(--text); }
        .user-chip-text .urole { font-size: 0.68rem; color: var(--text-muted); font-weight: 500; }
        .user-chip i.caret { color: var(--text-muted); font-size: 0.7rem; margin-left: 4px; }

        .content { padding: 10px 32px 36px 32px; }

        /* ===== PROMO / BANNER CARDS (theme 2) ===== */
        .promo-card {
            border: none; border-radius: var(--radius);
            padding: 24px 28px;
            background: linear-gradient(135deg, #FFE5C7 0%, #FFD1A1 50%, #FFB77A 100%);
            color: #4a2800; position: relative; overflow: hidden;
            min-height: 158px;
        }
        .promo-card.promo-blue {
            background: linear-gradient(135deg, #E6EEFF 0%, #D2DCFF 50%, #BEC9FF 100%);
            color: #1a2457;
        }
        .promo-card h4 {
            font-weight: 800; font-size: 1.25rem; margin: 0 0 6px 0; letter-spacing: -0.01em;
        }
        .promo-card p {
            font-size: 0.82rem; margin: 0 0 14px 0; opacity: 0.88; font-weight: 500;
            max-width: 320px;
        }
        .promo-card .promo-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 9px 18px; border-radius: 100px;
            background: #fff; color: var(--primary);
            font-weight: 700; font-size: 0.8rem;
            border: none; cursor: pointer; text-decoration: none;
            box-shadow: 0 6px 18px rgba(255,255,255,0.4);
            transition: all 0.2s;
        }
        .promo-card.promo-blue .promo-btn {
            background: #fff; color: #3b5bff;
            box-shadow: 0 6px 18px rgba(255,255,255,0.4);
        }
        .promo-card .promo-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(0,0,0,0.1); }
        .promo-card .promo-art {
            position: absolute; right: -10px; bottom: -20px;
            width: 160px; height: 160px; opacity: 0.95; pointer-events: none;
        }
        .promo-card.promo-blue .promo-art { width: 180px; height: 180px; }

        /* ===== KPI CARDS ===== */
        .kpi-card {
            border: none; border-radius: var(--radius); background: #fff;
            padding: 20px 22px; position: relative; overflow: hidden;
            transition: all 0.3s ease; box-shadow: var(--shadow-sm);
        }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: var(--shadow); }
        .kpi-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
        .kpi-icon-box {
            width: 44px; height: 44px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem; color: #fff;
        }
        .kpi-icon-box.c1 { background: var(--btn-primary-solid); box-shadow: 0 6px 14px rgba(249,139,42,0.22); }
        .kpi-icon-box.c2 { background: #3b82f6; box-shadow: 0 6px 14px rgba(59,130,246,0.2); }
        .kpi-icon-box.c3 { background: #10b981; box-shadow: 0 6px 14px rgba(16,185,129,0.2); }
        .kpi-icon-box.c4 { background: #ef4444; box-shadow: 0 6px 14px rgba(239,68,68,0.2); }
        .kpi-change {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700;
        }
        .kpi-change.up { background: var(--success-light); color: var(--success); }
        .kpi-change.down { background: var(--danger-light); color: var(--danger); }
        .kpi-label { font-size: 0.8rem; font-weight: 500; color: var(--text-muted); text-transform: none; margin-top: 10px; }
        .kpi-value { font-size: 1.7rem; font-weight: 800; margin-top: 4px; color: var(--text); letter-spacing: -0.02em; }
        .kpi-meta-row { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
        .kpi-spark {
            width: 110px; height: 34px; display: flex; align-items: flex-end; gap: 2px;
        }
        .kpi-spark span {
            flex: 1; border-radius: 2px; min-height: 3px;
            opacity: 0.9;
        }
        .kpi-spark.up span { background: var(--success); }
        .kpi-spark.down span { background: var(--danger); }

        .page-head {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 20px;
        }
        .page-head h1 { margin: 0; font-weight: 800; font-size: 1.25rem; letter-spacing: 0.1em; text-transform: uppercase; color: #111; }
        .date-filter {
            background: #fff; border-radius: 14px; padding: 10px 16px;
            box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 10px;
            font-size: 0.85rem; font-weight: 600; color: var(--text); cursor: pointer;
        }

        .card {
            border: none; border-radius: var(--radius); box-shadow: var(--shadow-sm);
            background: var(--card-bg); transition: transform 0.2s, box-shadow 0.2s; overflow: hidden;
        }
        .card:hover { box-shadow: var(--shadow); }
        .card-header {
            background: transparent; border-bottom: none;
            padding: 22px 26px 8px 26px;
        }
        .card-header .card-title, .card-header h5 {
            margin: 0; font-weight: 700; font-size: 1rem; color: var(--text);
        }
        .card-body { padding: 18px 26px 26px 26px; }

        .revenue-top {
            display: flex; align-items: flex-start; justify-content: space-between;
            margin-bottom: 14px;
        }
        .revenue-info h3 { margin: 0; font-weight: 800; font-size: 1.9rem; color: var(--text); letter-spacing: -0.02em; }
        .revenue-info .rev-change { color: var(--success); font-size: 0.8rem; font-weight: 600; margin-top: 4px; display: flex; align-items: center; gap: 5px; }
        .revenue-actions { display: flex; align-items: center; gap: 10px; }
        .year-tabs { display: flex; gap: 4px; }
        .year-tab {
            padding: 4px 10px; border-radius: 8px; font-size: 0.78rem; font-weight: 600;
            color: var(--text-muted); cursor: pointer; display: flex; align-items: center; gap: 5px;
        }
        .year-tab::before { content: ''; width: 10px; height: 10px; border-radius: 3px; display: inline-block; background: #ddd; }
        .year-tab.yl::before { background: #e5e7eb; }
        .year-tab.yd::before { background: var(--btn-primary-solid); }
        .year-tab.active { color: var(--text); }
        .add-year-btn {
            padding: 4px 10px; border-radius: 8px; font-size: 0.78rem; font-weight: 600;
            color: var(--text); background: #f3f4f6; border: none; cursor: pointer;
        }
        .interval-drop {
            padding: 5px 12px; border-radius: 8px; font-size: 0.78rem; font-weight: 600;
            color: var(--text); background: #f3f4f6; border: none; cursor: pointer;
        }

        /* ===== MONTHLY TARGET CIRCULAR GAUGE ===== */
        .target-gauge-wrap { text-align: center; padding: 14px 10px 4px; }
        .target-gauge {
            position: relative; width: 180px; height: 180px; margin: 0 auto 8px;
        }
        .target-gauge .gauge-bg { stroke: #f1f2f8; }
        .target-gauge .gauge-fg {
            stroke: var(--btn-primary-solid);
            stroke-linecap: round;
            transition: stroke-dashoffset 0.8s ease;
        }
        .target-gauge .gauge-center {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            flex-direction: column;
        }
        .target-gauge .gauge-center .pct {
            font-size: 1.85rem; font-weight: 800; color: var(--text); letter-spacing: -0.02em;
        }
        .target-gauge .gauge-center .lbl {
            font-size: 0.72rem; color: var(--text-muted); font-weight: 500;
        }
        .boost-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 22px; border-radius: 100px; border: none; cursor: pointer;
            background: var(--btn-primary-solid); color: #fff;
            font-weight: 700; font-size: 0.84rem;
            box-shadow: 0 8px 22px rgba(249, 139, 42, 0.26);
            transition: all 0.2s;
        }
        .boost-btn:hover { transform: translateY(-2px); background: var(--btn-primary-solid-hover); box-shadow: 0 12px 28px rgba(249, 139, 42, 0.34); }

        .side-card {
            padding: 22px 22px; margin-bottom: 20px;
        }
        .side-card-title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
        .side-card-title h5 { margin: 0; font-weight: 700; font-size: 0.95rem; }
        .side-card-title .dot-btn {
            background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1rem;
        }

        .sales-target .target-value { font-size: 1.35rem; font-weight: 800; letter-spacing: -0.01em; margin: 2px 0 8px; }
        .sales-target .target-sub { font-size: 0.75rem; color: var(--text-muted); margin-bottom: 14px; }
        .progress-xl { height: 10px; border-radius: 50px; background: #f3f4f6; overflow: hidden; margin-bottom: 6px; }
        .progress-xl .progress-bar {
            height: 100%; border-radius: 50px; background: var(--btn-primary-solid);
        }
        .progress-meta { display: flex; justify-content: space-between; font-size: 0.72rem; color: var(--text-muted); font-weight: 600; }

        .donut-wrap { position: relative; width: 100%; height: 200px; display: flex; align-items: center; justify-content: center; }
        .donut-center { position: absolute; text-align: center; }
        .donut-center .num { font-size: 1.5rem; font-weight: 800; }
        .donut-center .lbl { font-size: 0.7rem; color: var(--text-muted); }
        .legend { margin-top: 14px; display: flex; flex-direction: column; gap: 6px; }
        .legend-item { display: flex; align-items: center; justify-content: space-between; font-size: 0.72rem; color: var(--text-muted); }
        .legend-left { display: flex; align-items: center; gap: 6px; }
        .legend-dot { width: 10px; height: 10px; border-radius: 3px; }
        .legend-pct { font-weight: 700; color: var(--text); }

        .bsp-wrap .bsp-hint { font-size: 0.7rem; color: var(--text-muted); margin-bottom: 10px; }
        .bsp-item { margin-bottom: 14px; }
        .bsp-label { display: flex; justify-content: space-between; font-size: 0.8rem; font-weight: 600; margin-bottom: 6px; }
        .bsp-bar { height: 6px; border-radius: 50px; background: #f3f4f6; overflow: hidden; }
        .bsp-bar > div { height: 100%; border-radius: 50px; background: var(--btn-primary-solid); }
        .bsp-item:nth-child(2) .bsp-bar > div { background: #3b82f6; }
        .bsp-item:nth-child(3) .bsp-bar > div { background: #10b981; }

        .table { font-size: 0.85rem; }
        .table thead th {
            background: transparent; border-bottom: 1px solid var(--border);
            font-weight: 600; color: var(--text-muted); font-size: 0.78rem;
            letter-spacing: 0.03em; border-top: none; padding: 12px 16px;
        }
        .table tbody td { padding: 16px; vertical-align: middle; border-color: var(--border); border: none; border-bottom: 1px solid var(--border); }
        .table tbody tr { transition: background 0.15s; }
        .table tbody tr:hover { background: #fafafa; }
        .table tbody tr:last-child td { border-bottom: none; }

        .product-cell { display: flex; align-items: center; gap: 12px; }
        .product-thumb {
            width: 44px; height: 44px; border-radius: 12px;
            background: linear-gradient(135deg, #FFF3E6, #FFD9B0);
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; color: var(--primary); font-weight: 800;
        }
        .product-thumb.t2 { background: linear-gradient(135deg, #e0e7ff, #c7d2fe); color: #6366f1; }
        .product-thumb.t3 { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #10b981; }
        .product-thumb.t4 { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #ef4444; }
        .product-thumb.t5 { background: linear-gradient(135deg, #ede9fe, #ddd6fe); color: #8b5cf6; }
        .product-name { font-weight: 700; font-size: 0.88rem; color: var(--text); }
        .product-sub { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; margin-top: 1px; }
        .status-pill {
            display: inline-flex; align-items: center; padding: 4px 11px;
            border-radius: 50px; font-size: 0.7rem; font-weight: 700;
        }
        .status-pill.completed { background: var(--success-light); color: var(--success); }
        .status-pill.pending { background: var(--warning-light); color: #d97706; }
        .status-pill.refund { background: var(--warning-light); color: #d97706; }
        .status-pill.shipped { background: var(--info-light); color: var(--info); }
        .status-pill.cancelled { background: var(--danger-light); color: var(--danger); }
        .action-menu {
            background: none; border: none; color: var(--text-muted); cursor: pointer;
            padding: 4px 8px; border-radius: 6px;
        }
        .action-menu:hover { background: #f3f4f6; color: var(--text); }

        .text-money { font-weight: 700; font-size: 0.9rem; letter-spacing: -0.01em; }
        .text-muted-sm { font-size: 0.78rem; color: var(--text-muted); }

        .country-bubble {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 14px 8px 8px;
            background: #fff; border-radius: 100px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            margin: 4px; cursor: pointer; transition: all 0.2s;
        }
        .country-bubble:hover { border-color: rgba(249,139,42,0.32); box-shadow: var(--shadow); transform: translateY(-1px); }
        .country-bubble .cflag {
            width: 30px; height: 30px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            background: linear-gradient(135deg,#f3f4f6,#e5e7eb);
        }
        .country-bubble .cname { font-weight: 700; font-size: 0.82rem; color: var(--text); }
        .country-bubble .cval { font-size: 0.74rem; color: var(--text-muted); font-weight: 500; margin-left: 4px; }

        .pagination { gap: 4px; }
        .pagination .page-link {
            border: none; color: var(--text); border-radius: 10px;
            padding: 0.45rem 0.75rem; font-size: 0.82rem; font-weight: 600; background: #fff;
            box-shadow: var(--shadow-sm); margin: 0 2px;
        }
        .pagination .page-link:hover { background: var(--btn-primary-solid); color: #fff; box-shadow: 0 8px 20px rgba(249, 139, 42, 0.22); }
        .pagination .page-item.active .page-link { background: var(--btn-primary-solid); color: #fff; box-shadow: 0 8px 20px rgba(249, 139, 42, 0.26); }

        .breadcrumb { background: transparent; padding: 0; margin: 4px 0 0; font-size: 0.78rem; }
        .breadcrumb-item a { color: var(--text-muted); text-decoration: none; }
        .breadcrumb-item a:hover { color: var(--primary); }
        .breadcrumb-item.active { color: var(--text); font-weight: 600; }

        .btn-primary {
            background: var(--btn-primary-solid); border: none; color: #fff;
            font-weight: 600; border-radius: 12px; padding: 9px 18px;
            box-shadow: 0 6px 16px rgba(249, 139, 42, 0.22);
            transition: all 0.2s;
        }
        .btn-primary:hover { background: var(--btn-primary-solid-hover); transform: translateY(-1px); box-shadow: 0 10px 24px rgba(249, 139, 42, 0.32); color: #fff; }
        .btn-primary:focus, .btn-primary:active { background: var(--btn-primary-solid-hover); color: #fff; box-shadow: 0 0 0 3px rgba(249, 139, 42, 0.2); }
        .btn-accent, .btn-outline-primary:hover {
            background: var(--btn-primary-solid); color: #fff; border-color: transparent;
        }
        .btn-outline-primary {
            color: var(--primary); border-color: rgba(249,139,42,0.3);
            font-weight: 600; border-radius: 12px; padding: 9px 18px; background: #fff;
        }
        .btn-outline-primary:hover { color: #fff; background: var(--btn-primary-solid-hover); border-color: transparent; }
        .btn-light {
            background: #fff; border: 1px solid var(--border); border-radius: 12px;
            color: var(--text); font-weight: 600; padding: 9px 18px; box-shadow: var(--shadow-sm);
        }
        .btn-light:hover { background: var(--primary-soft); border-color: rgba(249,139,42,0.24); color: var(--primary); }

        .text-primary { color: var(--primary) !important; }
        .bg-primary { background: var(--btn-primary-solid) !important; }
        .border-primary { border-color: var(--primary) !important; }

        .list-group-item.active, .list-group-item.active-tab-trigger, .list-group-item.tab-link.active {
            background: var(--primary-soft) !important;
            color: var(--primary-dark) !important;
            border-color: transparent !important;
            box-shadow: inset 3px 0 0 var(--primary);
            font-weight: 700;
        }

        .tab-link.active { border-left: 3px solid var(--primary); background: var(--primary-soft); color: var(--primary-dark); font-weight: 700; }
        .tab-link:hover { background: #f8fafc; }

        .fade-in { animation: fadeIn 0.4s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .notification-dropdown { min-width: 360px; max-height: 450px; overflow-y: auto; border-radius: 18px; border: none; box-shadow: var(--shadow-lg); }
        .notification-item { padding: 0.75rem; border-radius: 10px; display: flex; gap: 0.75rem; margin-bottom: 2px; cursor: pointer; transition: background 0.15s; }
        .notification-item:hover { background: #f9fafb; }
        .notification-item.unread { background: rgba(249,139,42,0.05); }
        .notification-item .n-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.9rem; }
        .notification-item .n-body { flex: 1; min-width: 0; }
        .notification-item .n-title { font-weight: 600; font-size: 0.85rem; margin-bottom: 0.1rem; }
        .notification-item .n-text { font-size: 0.78rem; color: var(--text-muted); line-height: 1.35; }
        .notification-item .n-time { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem; }

        .loading-spinner { display: inline-block; width: 24px; height: 24px; border: 3px solid rgba(249,139,42,0.15); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .alert {
            border: none; border-radius: 14px; padding: 14px 18px;
            font-size: 0.88rem; font-weight: 500; box-shadow: var(--shadow-sm);
        }

        .dropdown-menu.show { border-radius: 18px; border: none; box-shadow: var(--shadow-lg); padding: 8px; }
        .dropdown-item {
            border-radius: 10px; padding: 8px 12px; font-size: 0.85rem;
            color: var(--text); font-weight: 500;
        }
        .dropdown-item:hover { background: var(--primary-soft); color: var(--primary-dark); }
        .dropdown-item i { margin-right: 8px; width: 18px; text-align: center; color: var(--text-muted); }
        .dropdown-item:hover i { color: var(--primary); }

        @media (max-width: 1199px) {
            .search-box input { width: 200px; }
            .promo-card .promo-art { width: 120px; height: 120px; }
        }
        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); box-shadow: var(--shadow-lg); }
            .sidebar.open { transform: translateX(0); }
            .sidebar-toggle-btn { display: flex; }
            .main { margin-left: 0; }
            .search-box { display: none; }
            .welcome-text h2 { font-size: 1.2rem; }
            .topbar { padding: 14px 18px 8px; flex-wrap: wrap; gap: 10px; }
            .content { padding: 10px 18px 30px; }
            .user-chip-text { display: none; }
        }
        @media (max-width: 576px) {
            .date-chip { display: none; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <aside class="sidebar" id="sidebar">
        <button class="sidebar-toggle-btn" onclick="document.getElementById('sidebar').classList.toggle('open')">
            <i class="fas fa-bars"></i>
        </button>
        <div class="sidebar-brand">
            <div class="sidebar-brand-logo">
                <i class="fas fa-chart-simple"></i>
            </div>
            <div class="sidebar-brand-text">
                <span class="bname">SalesForecast</span>
                <span class="bsub">BI · Dashboard</span>
            </div>
        </div>

        <?php
            $user = auth()->user();
            $uAvatar = strtoupper(substr($user['first_name'] ?? 'A', 0, 1) . substr($user['last_name'] ?? 'R', 0, 1));
            $uFull = e(($user['first_name'] ?? 'Abdur') . ' ' . ($user['last_name'] ?? 'Rais'));
            $uRole = e($user['role_name'] ?? \Session::userRole() ?? 'Administrator');
        ?>

        <div class="sidebar-section-label">Menu</div>
        <?php
            $currentPage = $currentPage ?? basename($_SERVER['PHP_SELF'], '.php');
            $notifCount = (new \Notification())->unreadNotificationCount(\Session::userId() ?? 0);
            $role = \Session::userRole() ?? '';
            $isAdmin = $role === 'Administrator';

            // Icon + Label menu matching Carttolo sample:
            $navIcons = [
                ['url' => 'dashboard.php',  'icon' => 'fas fa-house-chimney',  'label' => 'Dashboard', 'page' => 'dashboard', 'perm' => 'view_dashboard'],
                ['url' => 'predict.php',    'icon' => 'fas fa-wand-magic-sparkles', 'label' => 'Sales Prediction', 'page' => 'predict', 'perm' => 'view_forecasts'],
                ['url' => 'sales.php',      'icon' => 'fas fa-cash-register',   'label' => 'Sales',     'page' => 'sales', 'perm' => 'view_sales'],
                ['url' => 'products.php',   'icon' => 'fas fa-boxes-stacked',   'label' => 'Products',  'page' => 'products', 'perm' => 'view_products'],
                ['url' => 'orders.php',     'icon' => 'fas fa-receipt',         'label' => 'Orders',    'page' => 'orders', 'perm' => 'view_orders'],
                ['url' => 'customers.php',  'icon' => 'fas fa-users',           'label' => 'Customers', 'page' => 'customers', 'perm' => 'view_customers'],
                ['url' => 'analytics.php',  'icon' => 'fas fa-chart-column',    'label' => 'Analytics', 'page' => 'analytics', 'perm' => 'view_dashboard'],
                ['url' => 'forecast.php',   'icon' => 'fas fa-chart-line',      'label' => 'Forecast',  'page' => 'forecast', 'perm' => 'create_forecasts'],
                ['url' => 'marketing.php',  'icon' => 'fas fa-bullhorn',        'label' => 'Marketing', 'page' => 'marketing', 'perm' => 'view_dashboard'],
                ['url' => 'dataset.php',    'icon' => 'fas fa-folder-open',     'label' => 'Datasets',  'page' => 'dataset', 'perm' => 'manage_dataset'],
                ['url' => 'reports.php',    'icon' => 'fas fa-file-lines',      'label' => 'Reports',   'page' => 'reports', 'perm' => 'create_reports'],
                ['url' => 'chat.php',       'icon' => 'fas fa-comments',        'label' => 'Assistant', 'page' => 'chat', 'perm' => 'view_notifications'],
            ];
            // only show menu items this user is allowed to open (no more "Access denied" surprises)
            $navIcons = array_values(array_filter($navIcons, fn($it) => auth()->hasPermission($it['perm'])));
            $adminIcons = [
                ['url' => 'users.php',      'icon' => 'fas fa-user-shield',     'label' => 'Users',     'page' => 'users'],
                ['url' => 'settings.php',   'icon' => 'fas fa-gear',            'label' => 'Settings',  'page' => 'settings'],
            ];

            $bottomIcons = [
                ['url' => 'logout.php',     'icon' => 'fas fa-right-from-bracket', 'label' => 'Logout', 'page' => 'logout'],
            ];
        ?>
        <ul class="sidebar-nav">
            <?php foreach ($navIcons as $it):
                $active = $currentPage === $it['page'];
                $badge = null;
            ?>
                <li>
                    <a href="<?= url($it['url']) ?>" class="nav-link <?= $active ? 'active' : '' ?>" title="<?= e($it['label']) ?>">
                        <span class="nav-icon"><i class="<?= $it['icon'] ?>"></i></span>
                        <span class="nav-label"><?= e($it['label']) ?></span>
                        <?php if ($badge): ?><span class="nav-badge"><?= $badge ?></span><?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($isAdmin && !empty($adminIcons)): ?>
        <div class="sidebar-section-label">Admin</div>
        <ul class="sidebar-nav">
            <?php foreach ($adminIcons as $it):
                $active = $currentPage === $it['page'];
            ?>
                <li>
                    <a href="<?= url($it['url']) ?>" class="nav-link <?= $active ? 'active' : '' ?>" title="<?= e($it['label']) ?>">
                        <span class="nav-icon"><i class="<?= $it['icon'] ?>"></i></span>
                        <span class="nav-label"><?= e($it['label']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <div class="sidebar-spacer"></div>

        <div class="sidebar-bottom">
            <div class="sidebar-user">
                <div class="avatar-xl"><?= $uAvatar ?></div>
                <div class="sidebar-user-text">
                    <span class="uname"><?= $uFull ?></span>
                    <span class="urole"><?= $uRole ?></span>
                </div>
            </div>
            <ul class="sidebar-nav">
                <?php foreach ($bottomIcons as $it):
                    $active = $currentPage === $it['page'];
                ?>
                    <li>
                        <a href="<?= url($it['url']) ?>" class="nav-link <?= $active ? 'active' : '' ?>" title="<?= e($it['label']) ?>">
                            <span class="nav-icon"><i class="<?= $it['icon'] ?>"></i></span>
                            <span class="nav-label"><?= e($it['label']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </aside>

    <main class="main">
        <div class="topbar">
            <div class="welcome-area">
                <button class="sidebar-toggle-btn" style="display:none;background:var(--primary-gradient);color:#fff;" onclick="document.getElementById('sidebar').classList.toggle('open')">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="welcome-text">
                    <h6><span class="wave">👋</span> Hi, <?= e($user['first_name'] ?? 'Abdur Rais') ?>!</h6>
                    <h2>Welcome Back!</h2>
                </div>
            </div>
            <div class="topbar-right">
                <div class="search-box d-none d-md-block">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" class="form-control" id="globalSearch" placeholder="Search sales (product, category, order)..." autocomplete="off">
                </div>

                <?php $__rng = db()->query("SELECT MIN(sale_date) a, MAX(sale_date) b FROM sales")->fetch(); ?>
                <div class="date-chip d-none d-xl-flex" title="Date range of your imported sales data">
                    <i class="far fa-calendar"></i>
                    <span><?= !empty($__rng['a']) ? date('M d, Y', strtotime($__rng['a'])) . ' – ' . date('M d, Y', strtotime($__rng['b'])) : 'No data imported' ?></span>
                </div>

                <button class="icon-btn d-none d-md-inline-flex" title="Download Report" onclick="location.href='<?= url('reports.php') ?>'">
                    <i class="fas fa-download"></i>
                </button>
                <button class="icon-btn d-none d-md-inline-flex" title="Copy link to this page" onclick="copyPageLink()">
                    <i class="fas fa-share-nodes"></i>
                </button>
                <div class="dropdown">
                    <button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <?php if ($notifCount > 0): ?><span class="badge"><?= $notifCount > 99 ? '99+' : $notifCount ?></span><?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notification-dropdown p-3 shadow-lg border-0">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <h6 class="fw-bold mb-0">Notifications</h6>
                            <a href="#" onclick="markAllNotifRead(event)" class="text-decoration-none fw-semibold" style="font-size:0.8rem;color:var(--primary);">Mark all read</a>
                        </div>
                        <div id="notif-list">
                            <?php
                                $notifs = (new \Notification())->where('user_id', \Session::userId() ?? 0);
                                $notifs = array_slice($notifs, 0, 5);
                                if (empty($notifs)):
                            ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-bell-slash text-muted" style="font-size:2rem;opacity:0.3;"></i>
                                    <p class="mb-0 text-muted small mt-2">No notifications yet</p>
                                </div>
                            <?php else: foreach ($notifs as $n):
                                    $icons = ['forecast' => 'fa-chart-line', 'dataset' => 'fa-database', 'report' => 'fa-file-lines', 'user' => 'fa-user', 'system' => 'fa-gear'];
                                    $bgs   = ['forecast' => 'background:var(--primary-gradient);color:#fff;', 'dataset' => 'background:#FFE5C7;color:#D26911;', 'report' => 'background:var(--success-light);color:var(--success);', 'user' => 'background:var(--info-light);color:var(--info);', 'system' => 'background:var(--warning-light);color:#92400e;'];
                                    $ic = $icons[$n['type']] ?? 'fa-bell';
                                    $bg = $bgs[$n['type']] ?? 'background:var(--primary-gradient);color:#fff;';
                            ?>
                                <div class="notification-item <?= !$n['is_read'] ? 'unread' : '' ?>">
                                    <div class="n-icon" style="<?= $bg ?>"><i class="fas <?= $ic ?>"></i></div>
                                    <div class="n-body">
                                        <div class="n-title"><?= e($n['title']) ?></div>
                                        <div class="n-text"><?= limit_text(e($n['message'] ?? ''), 80) ?></div>
                                        <div class="n-time"><i class="far fa-clock me-1"></i><?= date('M d, H:i', strtotime($n['created_at'] ?? 'now')) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <div class="d-grid mt-2">
                            <a href="<?= url('notifications.php') ?>" class="btn btn-sm btn-outline-primary rounded-pill">View all notifications</a>
                        </div>
                    </div>
                </div>

                <div class="dropdown">
                    <div class="user-chip" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar-mini"><?= $uAvatar ?></div>
                        <div class="user-chip-text d-none d-md-flex">
                            <span class="uname"><?= $uFull ?></span>
                            <span class="urole"><?= $uRole ?></span>
                        </div>
                        <i class="fas fa-chevron-down caret"></i>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= url('profile.php') ?>"><i class="fas fa-user"></i> My Profile</a></li>
                        <li><a class="dropdown-item" href="<?= url('settings.php') ?>"><i class="fas fa-gear"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= url('logout.php') ?>" style="color:var(--danger);"><i class="fas fa-right-from-bracket"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="content">
            <?php if (\Session::hasFlash('success') || \Session::hasFlash('error') || \Session::hasFlash('info')): ?>
                <div class="mb-4">
                    <?php if (\Session::hasFlash('success')): ?>
                        <div class="alert alert-success fade-in" role="alert">
                            <i class="fas fa-circle-check me-2"></i><?= e(\Session::flash('success')) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (\Session::hasFlash('error')): ?>
                        <div class="alert alert-danger fade-in" role="alert">
                            <i class="fas fa-circle-xmark me-2"></i><?= e(\Session::flash('error')) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (\Session::hasFlash('info')): ?>
                        <div class="alert alert-info fade-in" role="alert">
                            <i class="fas fa-circle-info me-2"></i><?= e(\Session::flash('info')) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
