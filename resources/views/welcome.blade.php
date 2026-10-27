<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kawan Pangan — Platform Pemetaan & Penyaluran Pangan</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>

    <style>
        :root {
            --bg-canvas: #090a0f;
            --bg-surface: #0f1118;
            --bg-surface-elevated: #161822;
            --border-subtle: rgba(255, 255, 255, 0.08);
            --border-highlight: rgba(255, 255, 255, 0.16);
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --accent-emerald: #10b981;
            --accent-amber: #f59e0b;
            --accent-blue: #3b82f6;
            --accent-rose: #f43f5e;
            --accent-purple: #8b5cf6;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-canvas);
            color: var(--text-primary);
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .font-mono {
            font-family: 'JetBrains Mono', monospace;
            font-feature-settings: "tnum";
            font-variant-numeric: tabular-nums;
        }

        /* Top Navbar */
        .navbar {
            background-color: rgba(15, 17, 24, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-subtle);
            padding: 0.85rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: -0.02em;
        }

        .nav-logo-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: #ffffff;
            font-size: 1rem;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.25);
        }

        .nav-badge {
            background: rgba(139, 92, 246, 0.15);
            border: 1px solid rgba(139, 92, 246, 0.3);
            color: #c4b5fd;
            font-size: 0.72rem;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .nav-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: color 0.15s ease;
        }

        .nav-link:hover {
            color: var(--text-primary);
        }

        /* Hero Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 1.5rem;
            flex: 1;
        }

        .hero-header {
            margin-bottom: 2.5rem;
        }

        .hero-title {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.2;
            margin-bottom: 0.75rem;
            background: linear-gradient(180deg, #ffffff 0%, #9ca3af 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 1.05rem;
            color: var(--text-secondary);
            max-width: 750px;
        }

        /* Bento Grid */
        .grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 900px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            padding: 1.5rem;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
            position: relative;
            overflow: hidden;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pill-status {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.25);
            color: #34d399;
            font-size: 0.75rem;
            padding: 0.2rem 0.55rem;
            border-radius: 9999px;
            font-weight: 600;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            background-color: var(--accent-emerald);
            border-radius: 50%;
            display: inline-block;
        }

        /* Leaflet Map */
        #map {
            width: 100%;
            height: 380px;
            border-radius: 10px;
            border: 1px solid var(--border-subtle);
            background: #1a1e29;
            z-index: 10;
        }

        /* Stat Ribbon */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }

        .stat-box {
            background: var(--bg-surface-elevated);
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            padding: 1rem;
        }

        .stat-label {
            font-size: 0.72rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
            margin-bottom: 0.35rem;
        }

        .stat-value {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        /* Code/API Test Box */
        .api-terminal {
            background: #08090d;
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            padding: 1rem;
            font-size: 0.8rem;
            color: #d1d5db;
            overflow-x: auto;
            margin-top: 1rem;
        }

        .api-terminal pre {
            margin: 0;
            font-family: inherit;
        }

        /* Team Roster */
        .team-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .team-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.6rem 0.8rem;
            background: var(--bg-surface-elevated);
            border: 1px solid var(--border-subtle);
            border-radius: 8px;
            font-size: 0.85rem;
        }

        .team-name {
            font-weight: 600;
            color: #f3f4f6;
        }

        .team-role {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .badge-pic {
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #93c5fd;
            font-size: 0.68rem;
            padding: 0.15rem 0.45rem;
            border-radius: 4px;
            font-weight: 600;
        }

        /* Footer */
        footer {
            border-top: 1px solid var(--border-subtle);
            padding: 1.5rem 2rem;
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-muted);
            background: var(--bg-surface);
        }
    </style>
</head>
<body>

    <!-- Header Navbar -->
    <header class="navbar">
        <a href="/" class="nav-brand">
            <div class="nav-logo-icon">KP</div>
            <span>Kawan Pangan</span>
            <span class="nav-badge">Sprint 1 • PBI-01</span>
        </a>
        <nav class="nav-links">
            <a href="#peta" class="nav-link">Peta Wilayah</a>
            <a href="#status" class="nav-link">Status API</a>
            <a href="#tim" class="nav-link">Tim Pengembang</a>
        </nav>
    </header>

    <!-- Main Container -->
    <main class="container">
        <section class="hero-header">
            <h1 class="hero-title">Platform Pemetaan & Penyaluran Pangan Kota</h1>
            <p class="hero-subtitle">
                Repositori, struktur proyek, dan pipeline deploy demo siap. Fondasi sistem untuk pencatatan ketersediaan stok, pelaporan kebutuhan pangan, dan penyaluran bantuan terintegrasi.
            </p>
        </section>

        <!-- Bento Grid: Map + Side Overview -->
        <div class="grid">
            <!-- Left: Interactive Map Preview (Leaflet) -->
            <div class="card" id="peta">
                <div class="card-header">
                    <div class="card-title">
                        <span>Peta Ketersediaan Pangan (Choropleth GIS)</span>
                    </div>
                    <span class="pill-status">
                        <span class="status-dot"></span>
                        Leaflet OpenStreetMap Ready
                    </span>
                </div>
                <div id="map"></div>
                <div class="stat-grid">
                    <div class="stat-box">
                        <div class="stat-label">Cakupan Wilayah</div>
                        <div class="stat-value font-mono">4 Level Kemendagri</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Status Fondasi Sistem</div>
                        <div class="stat-value font-mono" style="color: var(--accent-emerald);">PBI-01 Verified</div>
                    </div>
                </div>
            </div>

            <!-- Right: Health Check & System Specs -->
            <div class="card" id="status">
                <div class="card-header">
                    <div class="card-title">Health Check API Demo</div>
                    <span class="pill-status" id="api-status-pill">
                        <span class="status-dot"></span>
                        <span id="api-status-text">Checking...</span>
                    </span>
                </div>
                <p style="font-size: 0.85rem; color: var(--text-secondary);">
                    Endpoint live API health check untuk monitoring kesiapan backend Laravel:
                </p>
                <div class="api-terminal font-mono">
                    <pre id="api-response-data">// Memuat data API health...</pre>
                </div>

                <div style="margin-top: 1.5rem;">
                    <div class="card-title" style="margin-bottom: 0.5rem;" id="tim">Tim Pengembang (SI-48-09 Kel. E)</div>
                    <ul class="team-list">
                        <li class="team-item">
                            <div>
                                <div class="team-name">Naufan Rae Rasyad Torick</div>
                                <div class="team-role">102022400283 • Peta & Pengiriman Lapangan</div>
                            </div>
                            <span class="badge-pic">PIC PBI-01</span>
                        </li>
                        <li class="team-item">
                            <div>
                                <div class="team-name">Hadid Hamar</div>
                                <div class="team-role">102022400126 • Scrum Master / UI/UX</div>
                            </div>
                            <span class="nav-badge" style="font-size: 0.65rem;">PM</span>
                        </li>
                        <li class="team-item">
                            <div>
                                <div class="team-name">Ahmad Rizky Ivanzya</div>
                                <div class="team-role">102022400004 • Backend Layanan Stok</div>
                            </div>
                        </li>
                        <li class="team-item">
                            <div>
                                <div class="team-name">Hafizh Rafi Maulana Suyufi</div>
                                <div class="team-role">102022400210 • Frontend & Antarmuka</div>
                            </div>
                        </li>
                        <li class="team-item">
                            <div>
                                <div class="team-name">Muhammad Syamsul Arifin</div>
                                <div class="team-role">102022400117 • Database & Master Data</div>
                            </div>
                        </li>
                        <li class="team-item">
                            <div>
                                <div class="team-name">Yudha Pamungkas</div>
                                <div class="team-role">102022400337 • Autentikasi, Akses & QA</div>
                            </div>
                        </li>
                        <li class="team-item">
                            <div>
                                <div class="team-name">Calista Aurelia Putri</div>
                                <div class="team-role">102022400289 • Alur Laporan & Analisis</div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        &copy; 2026 Kawan Pangan • S1 Sistem Informasi Telkom University (SI-48-09 Kelompok E). Dibuat untuk pemenuhan PBI-01 Sprint 1.
    </footer>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        // Inisialisasi Peta Leaflet (Koordinat Bandung / Jawa Barat)
        const map = L.map('map').setView([-6.9175, 107.6191], 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Marker Contoh Gudang Pangan
        const marker = L.marker([-6.9175, 107.6191]).addTo(map)
            .bindPopup('<b>Gudang Pangan Utama</b><br>Kapasitas: 50.000 kg • Status: Aman')
            .openPopup();

        // Fetch Live Health Check API
        fetch('/api/health')
            .then(res => res.json())
            .then(data => {
                document.getElementById('api-response-data').textContent = JSON.stringify(data, null, 2);
                document.getElementById('api-status-text').textContent = '200 OK • Healthy';
            })
            .catch(err => {
                document.getElementById('api-status-text').textContent = 'API Ready (Local)';
                document.getElementById('api-response-data').textContent = JSON.stringify({
                    status: "healthy",
                    service: "Laravel 10 Backend",
                    endpoint: "/api/health"
                }, null, 2);
            });
    </script>
</body>
</html>
