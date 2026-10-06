<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Web Installer — Digital Library</title>
    <style>
        :root {
            color-scheme: light;
            font-family: "Plus Jakarta Sans", Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --bg: #f6f7fb;
            --card: #ffffff;
            --ink: #111827;
            --muted: #667085;
            --line: #e4e7ec;
            --primary: #1d4ed8;
            --primary-soft: #eff6ff;
            --success: #067647;
            --success-soft: #ecfdf3;
            --danger: #b42318;
            --danger-soft: #fef3f2;
            --warning: #b54708;
            --warning-soft: #fffaeb;
            --radius: 20px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at top right, rgba(37, 99, 235, .07), transparent 28rem),
                var(--bg);
            color: var(--ink);
        }

        .shell {
            width: min(1060px, calc(100% - 32px));
            margin: 0 auto;
            padding: 42px 0 64px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 28px;
        }

        .brand-mark {
            display: grid;
            place-items: center;
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: #0f172a;
            color: #fff;
            font-weight: 800;
            letter-spacing: -.04em;
        }

        .brand strong {
            display: block;
            font-size: 15px;
        }

        .brand span {
            display: block;
            color: var(--muted);
            font-size: 12px;
            margin-top: 2px;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 28px;
            align-items: end;
            margin-bottom: 22px;
        }

        .eyebrow {
            color: var(--primary);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        h1 {
            font-size: clamp(28px, 5vw, 44px);
            line-height: 1.08;
            letter-spacing: -.045em;
            margin: 0;
        }

        .hero p {
            max-width: 680px;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.75;
            margin: 14px 0 0;
        }

        .steps {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .step {
            padding: 8px 11px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: rgba(255,255,255,.7);
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
        }

        .step.active {
            border-color: #bfdbfe;
            background: var(--primary-soft);
            color: var(--primary);
        }

        .card {
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--card);
            box-shadow: 0 18px 50px rgba(15, 23, 42, .055);
            overflow: hidden;
        }

        .card-head {
            padding: 24px 26px 18px;
            border-bottom: 1px solid var(--line);
        }

        .card-head h2 {
            margin: 0;
            font-size: 19px;
            letter-spacing: -.025em;
        }

        .card-head p {
            margin: 7px 0 0;
            color: var(--muted);
            line-height: 1.6;
            font-size: 13px;
        }

        .card-body { padding: 26px; }

        .notice {
            border-radius: 14px;
            padding: 13px 15px;
            margin-bottom: 18px;
            font-size: 13px;
            line-height: 1.55;
        }

        .notice.success {
            background: var(--success-soft);
            color: var(--success);
            border: 1px solid #abefc6;
        }

        .notice.error {
            background: var(--danger-soft);
            color: var(--danger);
            border: 1px solid #fecdca;
        }

        .notice.warning {
            background: var(--warning-soft);
            color: var(--warning);
            border: 1px solid #fedf89;
        }

        .requirements {
            display: grid;
            gap: 8px;
            margin-bottom: 28px;
        }

        .check {
            display: grid;
            grid-template-columns: 24px minmax(180px, .8fr) minmax(0, 1.2fr) auto;
            gap: 12px;
            align-items: center;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 11px 13px;
        }

        .check-dot {
            width: 20px;
            height: 20px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            font-size: 11px;
            font-weight: 900;
        }

        .check-dot.ok {
            background: var(--success-soft);
            color: var(--success);
        }

        .check-dot.bad {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .check strong {
            font-size: 13px;
            font-weight: 700;
        }

        .check small {
            color: var(--muted);
            overflow-wrap: anywhere;
        }

        .badge {
            font-size: 10px;
            font-weight: 800;
            padding: 4px 7px;
            border-radius: 999px;
            color: var(--muted);
            background: #f2f4f7;
        }

        .badge.required {
            color: #344054;
            background: #eaecf0;
        }

        .section-title {
            margin: 2px 0 16px;
            font-size: 15px;
            letter-spacing: -.02em;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .field.full { grid-column: 1 / -1; }

        label {
            display: block;
            font-size: 12px;
            font-weight: 750;
            margin-bottom: 7px;
        }

        input, select {
            width: 100%;
            min-height: 44px;
            border: 1px solid #d0d5dd;
            border-radius: 11px;
            background: #fff;
            color: var(--ink);
            padding: 10px 12px;
            outline: none;
            font: inherit;
            font-size: 13px;
        }

        input:focus, select:focus {
            border-color: #84adff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
        }

        .help {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.55;
            margin-top: 6px;
        }

        .error-text {
            color: var(--danger);
            font-size: 11px;
            line-height: 1.45;
            margin-top: 6px;
        }

        .divider {
            border: 0;
            border-top: 1px solid var(--line);
            margin: 26px 0;
        }

        .actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-top: 26px;
        }

        .actions p {
            margin: 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.55;
            max-width: 590px;
        }

        .button {
            appearance: none;
            border: 0;
            min-height: 44px;
            padding: 0 18px;
            border-radius: 11px;
            background: #0f172a;
            color: #fff;
            font: inherit;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        .button:hover { background: #1e293b; }

        .button:disabled {
            opacity: .45;
            cursor: not-allowed;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 24px;
        }

        .summary-item {
            border: 1px solid var(--line);
            border-radius: 13px;
            padding: 13px;
            min-width: 0;
        }

        .summary-item small {
            display: block;
            color: var(--muted);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .summary-item strong {
            display: block;
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        .success-panel {
            padding: 18px 0 4px;
            text-align: center;
        }

        .success-icon {
            width: 66px;
            height: 66px;
            border-radius: 22px;
            margin: 0 auto 18px;
            display: grid;
            place-items: center;
            background: var(--success-soft);
            color: var(--success);
            font-size: 28px;
            font-weight: 900;
        }

        .success-panel h2 {
            margin: 0;
            font-size: 26px;
            letter-spacing: -.035em;
        }

        .success-panel p {
            color: var(--muted);
            max-width: 620px;
            margin: 12px auto 22px;
            font-size: 13px;
            line-height: 1.7;
        }

        .footer {
            color: #98a2b3;
            font-size: 11px;
            text-align: center;
            margin-top: 18px;
        }

        @media (max-width: 760px) {
            .shell {
                width: min(100% - 20px, 1060px);
                padding-top: 22px;
            }

            .hero { grid-template-columns: 1fr; }

            .steps { justify-content: flex-start; }

            .card-head, .card-body { padding-left: 18px; padding-right: 18px; }

            .grid, .summary { grid-template-columns: 1fr; }

            .check {
                grid-template-columns: 24px 1fr auto;
            }

            .check small {
                grid-column: 2 / -1;
            }

            .actions {
                align-items: stretch;
                flex-direction: column;
            }

            .button { width: 100%; }
        }
    </style>
</head>
<body>
    <main class="shell">
        <div class="brand">
            <div class="brand-mark">DL</div>
            <div>
                <strong>Digital Library</strong>
                <span>Secure Web Installer</span>
            </div>
        </div>

        <section class="hero">
            <div>
                <div class="eyebrow">Initial setup</div>
                <h1>Siapkan perpustakaan digital.</h1>
                <p>
                    Installer ini memeriksa requirement server, menguji database kosong,
                    menulis konfigurasi production secara aman, menjalankan migration dan seeder,
                    lalu membuat Super Admin pertama.
                </p>
            </div>

            <div class="steps" aria-label="Tahapan instalasi">
                <span class="step {{ $step === 'requirements' ? 'active' : '' }}">1. Server & Database</span>
                <span class="step {{ $step === 'admin' ? 'active' : '' }}">2. Super Admin</span>
                <span class="step {{ $step === 'complete' ? 'active' : '' }}">3. Selesai</span>
            </div>
        </section>

        <section class="card">
            @if ($step === 'requirements')
                <div class="card-head">
                    <h2>Requirement server & koneksi database</h2>
                    <p>
                        Semua item wajib harus lulus. Item rekomendasi tidak menghalangi instalasi,
                        tetapi sebaiknya diperbaiki untuk pengalaman upload yang optimal.
                    </p>
                </div>

                <div class="card-body">
                    @if ($errors->has('database'))
                        <div class="notice error">{{ $errors->first('database') }}</div>
                    @endif

                    <div class="requirements">
                        @foreach ($requirements['checks'] as $check)
                            <div class="check">
                                <span class="check-dot {{ $check['ok'] ? 'ok' : 'bad' }}">
                                    {{ $check['ok'] ? '✓' : '!' }}
                                </span>
                                <strong>{{ $check['label'] }}</strong>
                                <small>{{ $check['detail'] }}</small>
                                <span class="badge {{ $check['required'] ? 'required' : '' }}">
                                    {{ $check['required'] ? 'Wajib' : 'Rekomendasi' }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    @if (! $requirements['passed'])
                        <div class="notice error">
                            Requirement wajib belum lengkap. Perbaiki item bertanda merah,
                            lalu muat ulang halaman ini sebelum melanjutkan.
                        </div>
                    @else
                        <div class="notice success">
                            Requirement wajib sudah terpenuhi. Silakan isi konfigurasi aplikasi dan database.
                        </div>
                    @endif

                    <form method="post" action="{{ route('install.database') }}" autocomplete="off">
                        @csrf

                        <h3 class="section-title">Identitas aplikasi</h3>

                        <div class="grid">
                            <div class="field">
                                <label for="app_name">Nama perpustakaan</label>
                                <input
                                    id="app_name"
                                    name="app_name"
                                    maxlength="120"
                                    required
                                    value="{{ old('app_name', $defaults['app_name']) }}"
                                >
                                @error('app_name') <div class="error-text">{{ $message }}</div> @enderror
                            </div>

                            <div class="field">
                                <label for="timezone">Timezone</label>
                                <input
                                    id="timezone"
                                    name="timezone"
                                    maxlength="64"
                                    required
                                    value="{{ old('timezone', $defaults['timezone']) }}"
                                >
                                @error('timezone') <div class="error-text">{{ $message }}</div> @enderror
                            </div>

                            <div class="field full">
                                <label for="app_url">URL utama</label>
                                <input
                                    id="app_url"
                                    type="url"
                                    name="app_url"
                                    maxlength="255"
                                    required
                                    value="{{ old('app_url', $defaults['app_url']) }}"
                                    placeholder="https://library.example.com"
                                >
                                <div class="help">
                                    Gunakan URL HTTPS final. Host ini akan menjadi host production yang diizinkan.
                                </div>
                                @error('app_url') <div class="error-text">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <hr class="divider">

                        <h3 class="section-title">Database MySQL / MariaDB</h3>

                        <div class="grid">
                            <div class="field">
                                <label for="db_connection">Engine</label>
                                <select id="db_connection" name="db_connection" required>
                                    <option value="mysql" @selected(old('db_connection', $defaults['db_connection']) === 'mysql')>MySQL</option>
                                    <option value="mariadb" @selected(old('db_connection', $defaults['db_connection']) === 'mariadb')>MariaDB</option>
                                </select>
                                @error('db_connection') <div class="error-text">{{ $message }}</div> @enderror
                            </div>

                            <div class="field">
                                <label for="db_host">Host</label>
                                <input
                                    id="db_host"
                                    name="db_host"
                                    maxlength="255"
                                    required
                                    value="{{ old('db_host', $defaults['db_host']) }}"
                                >
                                @error('db_host') <div class="error-text">{{ $message }}</div> @enderror
                            </div>

                            <div class="field">
                                <label for="db_port">Port</label>
                                <input
                                    id="db_port"
                                    type="number"
                                    min="1"
                                    max="65535"
                                    name="db_port"
                                    required
                                    value="{{ old('db_port', $defaults['db_port']) }}"
                                >
                                @error('db_port') <div class="error-text">{{ $message }}</div> @enderror
                            </div>

                            <div class="field">
                                <label for="db_database">Nama database</label>
                                <input
                                    id="db_database"
                                    name="db_database"
                                    maxlength="64"
                                    required
                                    value="{{ old('db_database', $defaults['db_database']) }}"
                                >
                                <div class="help">Database harus kosong. Installer tidak akan menimpa tabel yang sudah ada.</div>
                                @error('db_database') <div class="error-text">{{ $message }}</div> @enderror
                            </div>

                            <div class="field">
                                <label for="db_username">Username</label>
                                <input
                                    id="db_username"
                                    name="db_username"
                                    maxlength="128"
                                    required
                                    autocomplete="off"
                                    value="{{ old('db_username', $defaults['db_username']) }}"
                                >
                                @error('db_username') <div class="error-text">{{ $message }}</div> @enderror
                            </div>

                            <div class="field">
                                <label for="db_password">Password database</label>
                                <input
                                    id="db_password"
                                    type="password"
                                    name="db_password"
                                    maxlength="1024"
                                    autocomplete="new-password"
                                >
                                <div class="help">Password tidak ditampilkan kembali oleh installer.</div>
                                @error('db_password') <div class="error-text">{{ $message }}</div> @enderror
                            </div>

                            <div class="field full">
                                <label for="trusted_proxies">Trusted proxy IP/CIDR (opsional)</label>
                                <input
                                    id="trusted_proxies"
                                    name="trusted_proxies"
                                    maxlength="1000"
                                    value="{{ old('trusted_proxies', $defaults['trusted_proxies']) }}"
                                    placeholder="127.0.0.1,10.0.0.0/24"
                                >
                                <div class="help">
                                    Isi hanya IP/CIDR reverse proxy atau tunnel yang benar-benar dipercaya.
                                    Pisahkan dengan koma. Jangan gunakan wildcard.
                                </div>
                                @error('trusted_proxies') <div class="error-text">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="actions">
                            <p>
                                Pada langkah berikutnya installer akan membuat APP_KEY,
                                menulis .env dengan permission terbatas, dan menyimpan status instalasi sementara.
                                Password database tidak disimpan di status installer.
                            </p>
                            <button class="button" type="submit" @disabled(! $requirements['passed'])>
                                Tes koneksi & lanjutkan
                            </button>
                        </div>
                    </form>
                </div>
            @elseif ($step === 'admin')
                <div class="card-head">
                    <h2>Buat Super Admin pertama</h2>
                    <p>
                        Konfigurasi .env sudah tersimpan. Setelah form ini dikirim,
                        migration, seeder, storage link, dan akun Super Admin akan dibuat.
                    </p>
                </div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="notice success">{{ session('status') }}</div>
                    @endif

                    @if ($errors->has('installation'))
                        <div class="notice error">{{ $errors->first('installation') }}</div>
                    @endif

                    @if (! $databaseReady)
                        <div class="notice error">
                            Database saat ini tidak dapat dihubungi dari konfigurasi .env.
                            Periksa koneksi database sebelum menekan tombol instalasi.
                        </div>
                    @endif

                    <div class="summary">
                        <div class="summary-item">
                            <small>Aplikasi</small>
                            <strong>{{ $pending['app_name'] ?? 'Digital Library' }}</strong>
                        </div>
                        <div class="summary-item">
                            <small>URL</small>
                            <strong>{{ $pending['app_url'] ?? '-' }}</strong>
                        </div>
                        <div class="summary-item">
                            <small>Database</small>
                            <strong>{{ $pending['db_connection'] ?? '-' }} · {{ $pending['db_database'] ?? '-' }}</strong>
                        </div>
                        <div class="summary-item">
                            <small>Database host</small>
                            <strong>{{ $pending['db_host'] ?? '-' }}:{{ $pending['db_port'] ?? '-' }}</strong>
                        </div>
                        <div class="summary-item">
                            <small>Database version</small>
                            <strong>{{ $pending['db_version'] ?? '-' }}</strong>
                        </div>
                        <div class="summary-item">
                            <small>Timezone</small>
                            <strong>{{ $pending['timezone'] ?? 'Asia/Jakarta' }}</strong>
                        </div>
                    </div>

                    <div class="notice warning">
                        Jangan tutup proses ketika tombol instalasi ditekan. Untuk database baru,
                        proses biasanya singkat dan aman untuk diulang bila migration sebelumnya terputus.
                    </div>

                    <form method="post" action="{{ route('install.reconfigure') }}" style="margin-bottom: 22px;">
                        @csrf
                        <button class="button" type="submit" style="background:#475467;">
                            Ubah konfigurasi aplikasi / database
                        </button>
                    </form>

                    <form method="post" action="{{ route('install.complete') }}" autocomplete="off">
                        @csrf

                        <div class="grid">
                            <div class="field">
                                <label for="name">Nama Super Admin</label>
                                <input
                                    id="name"
                                    name="name"
                                    maxlength="120"
                                    required
                                    autocomplete="name"
                                    value="{{ old('name') }}"
                                >
                                @error('name') <div class="error-text">{{ $message }}</div> @enderror
                            </div>

                            <div class="field">
                                <label for="email">Email Super Admin</label>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    maxlength="255"
                                    required
                                    autocomplete="username"
                                    value="{{ old('email') }}"
                                >
                                @error('email') <div class="error-text">{{ $message }}</div> @enderror
                            </div>

                            <div class="field">
                                <label for="password">Password</label>
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    maxlength="255"
                                    required
                                    autocomplete="new-password"
                                >
                                <div class="help">Minimal 12 karakter, huruf besar/kecil, angka, dan simbol.</div>
                                @error('password') <div class="error-text">{{ $message }}</div> @enderror
                            </div>

                            <div class="field">
                                <label for="password_confirmation">Konfirmasi password</label>
                                <input
                                    id="password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    maxlength="255"
                                    required
                                    autocomplete="new-password"
                                >
                            </div>
                        </div>

                        <div class="actions">
                            <p>
                                Setelah sukses, installer dikunci otomatis dan tidak dapat digunakan
                                untuk mengambil alih aplikasi yang sudah berjalan.
                            </p>
                            <button class="button" type="submit" @disabled(! $databaseReady)>
                                Jalankan instalasi
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <div class="card-body">
                    <div class="success-panel">
                        <div class="success-icon">✓</div>
                        <h2>Instalasi selesai.</h2>
                        <p>
                            Database, konfigurasi production, role & permission, pengaturan awal,
                            storage link, serta Super Admin sudah siap. Installer telah dikunci.
                            Login menggunakan <strong>{{ $adminEmail }}</strong>.
                        </p>

                        <a class="button" href="{{ rtrim($appUrl, '/') }}/admin/login">
                            Buka halaman Admin
                        </a>
                    </div>
                </div>
            @endif
        </section>

        <div class="footer">
            Digital Library · Web Installer · Jangan membagikan credential database atau password admin.
        </div>
    </main>
</body>
</html>
