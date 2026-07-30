<?php
session_start();

$error       = '';
$prev_host   = '';
$prev_dbname = isset($_SESSION['db_name']) ? htmlspecialchars($_SESSION['db_name']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host   = trim($_POST['db_host'] ?? '');
    $user   = trim($_POST['db_user'] ?? '');
    $pass   = $_POST['db_pass'] ?? '';
    $dbname = trim($_POST['db_name'] ?? '');
    $prev_host = htmlspecialchars($host);

    if ($host === '' || $user === '') {
        $error = 'Please provide both the RDS endpoint and a username.';
    } else {
        try {
            $mysqli = new mysqli($host, $user, $pass);
            if ($mysqli->connect_error) {
                $error = 'Connection failed: ' . htmlspecialchars($mysqli->connect_error);
            } else {
                if ($dbname !== '') {
                    if (!$mysqli->select_db($dbname)) {
                        $error = 'Connected to RDS but database "' .
                            htmlspecialchars($dbname) .
                            '" was not found. Leave the field blank to auto-create one.';
                    }
                }
                if ($error === '') {
                    $_SESSION['db_host'] = $host;
                    $_SESSION['db_user'] = $user;
                    $_SESSION['db_pass'] = $pass;
                    $_SESSION['db_name'] = $dbname;
                    $mysqli->close();
                    header('Location: index.php');
                    exit;
                }
                $mysqli->close();
            }
        } catch (Exception $e) {
            $error = 'Connection error: ' . htmlspecialchars($e->getMessage());
        }
    }
} else {
    if (!empty($_SESSION['db_error'])) {
        $error = 'Previous connection failed: ' . htmlspecialchars($_SESSION['db_error']);
        unset($_SESSION['db_error']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentDB — Connect</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.31.0/dist/tabler-icons.min.css">
<style>
  @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Sora:wght@400;500;600&display=swap');

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:            #f5f4f0;
    --surface:       #ffffff;
    --surface2:      #f0ede8;
    --surface3:      #faf9f7;
    --border:        rgba(0,0,0,0.09);
    --border-strong: rgba(0,0,0,0.15);
    --text:          #1a1916;
    --text2:         #6b6860;
    --text3:         #9b9890;
    --text4:         #b4b2a9;
    --accent:        #2563eb;
    --accent-bg:     #eff6ff;
    --accent-text:   #1d4ed8;
    --success:       #16a34a;
    --danger:        #dc2626;
    --danger-bg:     #fef2f2;
    --danger-border: #fca5a5;
    --font:          'Sora', sans-serif;
    --mono:          'IBM Plex Mono', monospace;
    --radius:        7px;
    --radius-lg:     12px;
  }

  @media (prefers-color-scheme: dark) {
    :root {
      --bg:            #111110;
      --surface:       #1a1916;
      --surface2:      #222220;
      --surface3:      #1e1e1c;
      --border:        rgba(255,255,255,0.08);
      --border-strong: rgba(255,255,255,0.15);
      --text:          #f0ede8;
      --text2:         #9b9890;
      --text3:         #6b6860;
      --text4:         #4a4845;
      --accent:        #3b82f6;
      --accent-bg:     #1e3a5f;
      --accent-text:   #93c5fd;
      --success:       #22c55e;
      --danger:        #ef4444;
      --danger-bg:     #3b1515;
      --danger-border: #7f1d1d;
    }
  }

  body {
    font-family: var(--font);
    background: var(--bg);
    color: var(--text);
    font-size: 13px;
    line-height: 1.5;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }

  /* ── Header ── */
  .header {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    height: 52px;
    display: flex;
    align-items: center;
    padding: 0 24px;
    gap: 14px;
    position: sticky;
    top: 0;
    z-index: 10;
  }
  .logo {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    text-decoration: none;
  }
  .logo-icon {
    width: 28px; height: 28px;
    background: var(--text);
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
  }
  .logo-icon i { font-size: 15px; color: var(--bg); }
  .h-sep { width: 1px; height: 20px; background: var(--border); }
  .tag {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--accent-bg); color: var(--accent-text);
    border: 1px solid rgba(37,99,235,0.15); border-radius: 6px;
    padding: 4px 10px; font-size: 12px; font-weight: 500;
  }
  .header-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
  .stat-pill {
    font-family: var(--mono); font-size: 11px; color: var(--text2);
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: 5px; padding: 3px 8px;
  }
  .conn-status { font-size: 12px; color: var(--text3); display: flex; align-items: center; gap: 5px; }
  .conn-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--text3); }

  /* ── Body ── */
  .body {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 44px 24px;
  }

  .layout {
    display: flex;
    gap: 28px;
    width: 100%;
    max-width: 860px;
    align-items: flex-start;
  }

  /* ── Card ── */
  .card {
    flex: 0 0 400px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
  }
  .card-head {
    padding: 20px 24px 16px;
    border-bottom: 1px solid var(--border);
  }
  .card-title { font-size: 15px; font-weight: 600; color: var(--text); }
  .card-sub { font-size: 12px; color: var(--text2); margin-top: 2px; }
  .card-body { padding: 20px 24px; }
  .card-foot {
    padding: 14px 24px;
    border-top: 1px solid var(--border);
    background: var(--surface3);
  }

  /* ── Error banner ── */
  .error-banner {
    display: flex; align-items: flex-start; gap: 8px;
    background: var(--danger-bg);
    border: 1px solid var(--danger-border);
    border-radius: var(--radius);
    padding: 10px 12px;
    margin-bottom: 16px;
    font-size: 12px;
    color: var(--danger);
    line-height: 1.5;
  }
  .error-banner i { font-size: 14px; flex-shrink: 0; margin-top: 1px; }

  /* ── Fields ── */
  .field { margin-bottom: 14px; }
  .field:last-child { margin-bottom: 0; }
  .label-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 5px; }
  .label {
    display: block;
    font-size: 10px; font-weight: 600;
    letter-spacing: .07em; text-transform: uppercase;
    color: var(--text3); margin-bottom: 5px;
  }
  .label-inline { font-size: 10px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--text3); }
  .optional { font-size: 10px; color: var(--text3); font-style: italic; }
  .input-wrap { position: relative; }
  .input {
    width: 100%; background: var(--surface2);
    border: 1px solid var(--border); border-radius: var(--radius);
    padding: 8px 11px; font-family: var(--font); font-size: 12px;
    color: var(--text); outline: none;
    transition: border-color .15s, box-shadow .15s, background .15s;
  }
  .input.mono { font-family: var(--mono); font-size: 11px; }
  .input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    background: var(--surface);
  }
  .input::placeholder { color: var(--text4); }
  .input.has-eye { padding-right: 34px; }
  .eye-btn {
    position: absolute; right: 9px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    color: var(--text3); font-size: 15px; padding: 0;
    display: flex; align-items: center;
    transition: color .12s;
  }
  .eye-btn:hover { color: var(--text2); }
  .hint {
    display: flex; align-items: flex-start; gap: 5px;
    font-size: 11px; color: var(--text3);
    margin-top: 5px; line-height: 1.5;
  }
  .hint i { font-size: 12px; flex-shrink: 0; margin-top: 1px; }
  .hint code { font-family: var(--mono); font-size: 10px; color: var(--text2); }
  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .divider { border: none; border-top: 1px solid var(--border); margin: 16px 0; }

  /* ── Buttons ── */
  .btn-row { display: flex; gap: 8px; }
  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    font-family: var(--font); font-size: 12px; font-weight: 500;
    padding: 8px 14px; border-radius: var(--radius);
    border: 1px solid var(--border-strong);
    background: var(--surface); color: var(--text);
    cursor: pointer; transition: all .14s; white-space: nowrap;
    text-decoration: none;
  }
  .btn:hover { background: var(--surface2); }
  .btn:active { transform: scale(0.97); }
  .btn-primary { background: var(--accent); color: #fff; border-color: var(--accent); flex: 1; }
  .btn-primary:hover { background: var(--accent-text); border-color: var(--accent-text); }

  /* ── Note ── */
  .note {
    display: flex; align-items: flex-start; gap: 6px;
    font-size: 11px; color: var(--text3);
    line-height: 1.5; padding-top: 12px;
  }
  .note i { font-size: 13px; flex-shrink: 0; margin-top: 1px; }

  /* ── Sidebar ── */
  .sidebar { flex: 1; min-width: 0; }
  .sidebar-title {
    font-size: 10px; font-weight: 600; letter-spacing: .07em;
    text-transform: uppercase; color: var(--text3); margin-bottom: 12px;
  }
  .steps { display: flex; flex-direction: column; gap: 8px; }
  .step {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 14px;
    background: var(--surface); border: 1px solid var(--border); border-radius: 8px;
    transition: border-color .15s;
  }
  .step-num {
    width: 22px; height: 22px; flex-shrink: 0;
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-family: var(--mono); font-size: 10px; font-weight: 500;
    color: var(--text2); margin-top: 1px;
  }
  .step-label { font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 2px; }
  .step-text { font-size: 11px; color: var(--text2); line-height: 1.5; }
  .step-text code { font-family: var(--mono); font-size: 10px; }

  /* ── Status bar ── */
  .statusbar {
    background: var(--surface); border-top: 1px solid var(--border);
    height: 30px; display: flex; align-items: center;
    padding: 0 24px; gap: 14px;
    font-family: var(--mono); font-size: 10px; color: var(--text3);
  }
  .statusbar span { display: flex; align-items: center; gap: 4px; }
</style>
</head>
<body>

  <header class="header">
    <a href="#" class="logo">
      <div class="logo-icon"><i class="ti ti-database" aria-hidden="true"></i></div>
      StudentDB
    </a>
    <div class="h-sep"></div>
    <div class="tag">
      <i class="ti ti-plug" aria-hidden="true" style="font-size:12px"></i>
      New connection
    </div>
    <div class="header-right">
      <span class="stat-pill">MySQL · RDS</span>
      <span class="conn-status">
        <span class="conn-dot"></span> Not connected
      </span>
    </div>
  </header>

  <div class="body">
    <div class="layout">

      <!-- ── Login card ── -->
      <div class="card">
        <div class="card-head">
          <div class="card-title">Connect to RDS</div>
          <div class="card-sub">Enter your database credentials to continue</div>
        </div>

        <form method="post" autocomplete="off">
          <div class="card-body">

            <?php if ($error): ?>
            <div class="error-banner">
              <i class="ti ti-alert-circle" aria-hidden="true"></i>
              <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <div class="field">
              <label class="label" for="db_host">RDS Endpoint</label>
              <input
                class="input mono"
                type="text"
                id="db_host"
                name="db_host"
                required
                autocomplete="off"
                spellcheck="false"
                placeholder="mydb.xxxx.us-east-1.rds.amazonaws.com"
                value="<?php echo $prev_host; ?>">
            </div>

            <div class="grid-2">
              <div class="field">
                <label class="label" for="db_user">Username</label>
                <input
                  class="input"
                  type="text"
                  id="db_user"
                  name="db_user"
                  required
                  autocomplete="off"
                  placeholder="admin">
              </div>
              <div class="field">
                <label class="label" for="db_pass">Password</label>
                <div class="input-wrap">
                  <input
                    class="input has-eye"
                    type="password"
                    id="db_pass"
                    name="db_pass"
                    autocomplete="off"
                    placeholder="••••••••">
                  <button type="button" class="eye-btn" id="eye-btn" aria-label="Toggle password visibility">
                    <i class="ti ti-eye" id="eye-icon" aria-hidden="true"></i>
                  </button>
                </div>
              </div>
            </div>

            <hr class="divider">

            <div class="field">
              <div class="label-row">
                <label class="label-inline" for="db_name">Database name</label>
                <span class="optional">optional</span>
              </div>
              <input
                class="input mono"
                type="text"
                id="db_name"
                name="db_name"
                autocomplete="off"
                spellcheck="false"
                placeholder="Leave blank to auto-create studentdb"
                value="<?php echo $prev_dbname; ?>"
                style="margin-top:5px">
              <div class="hint">
                <i class="ti ti-info-circle" aria-hidden="true"></i>
                If left blank, <code>studentdb</code> is created automatically on first login.
              </div>
            </div>

          </div>

          <div class="card-foot">
            <div class="btn-row">
              <a href="#" class="btn">
                <i class="ti ti-x" aria-hidden="true"></i> Cancel
              </a>
              <button type="submit" class="btn btn-primary">
                <i class="ti ti-plug" aria-hidden="true"></i>
                Connect to database
              </button>
            </div>
            <div class="note">
              <i class="ti ti-lock" aria-hidden="true"></i>
              Credentials are stored in the PHP session only and cleared on logout or when the browser session ends.
            </div>
          </div>
        </form>
      </div>

      <!-- ── Sidebar ── -->
      <div class="sidebar">
        <div class="sidebar-title">How it works</div>
        <div class="steps">
          <div class="step">
            <div class="step-num">1</div>
            <div>
              <div class="step-label">Enter RDS credentials</div>
              <div class="step-text">Provide the endpoint, username, and password for your Amazon RDS instance.</div>
            </div>
          </div>
          <div class="step">
            <div class="step-num">2</div>
            <div>
              <div class="step-label">Database auto-setup</div>
              <div class="step-text">If no name is given, <code>studentdb</code> is created automatically with the <code>students</code> table ready to use.</div>
            </div>
          </div>
          <div class="step">
            <div class="step-num">3</div>
            <div>
              <div class="step-label">Redirected to the app</div>
              <div class="step-text">On success you are taken directly to the StudentDB interface to manage records.</div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <div class="statusbar">
    <span><i class="ti ti-server" aria-hidden="true" style="font-size:11px"></i> Amazon RDS · MySQL</span>
    <span>·</span>
    <span>Apache 2.4 · PHP 8</span>
    <span style="margin-left:auto">StudentDB v1.0</span>
  </div>

  <script>
    const eyeBtn  = document.getElementById('eye-btn');
    const eyeIcon = document.getElementById('eye-icon');
    const pwInput = document.getElementById('db_pass');
    eyeBtn.addEventListener('click', () => {
      const show = pwInput.type === 'password';
      pwInput.type = show ? 'text' : 'password';
      eyeIcon.className = show ? 'ti ti-eye-off' : 'ti ti-eye';
    });
    // Auto-focus first empty required field
    const host = document.getElementById('db_host');
    const user = document.getElementById('db_user');
    (host.value ? user : host).focus();
  </script>

</body>
</html>
