<?php
// index.php — auth check only; all UI is handled by JavaScript
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentDB</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.31.0/dist/tabler-icons.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg: #f5f4f0; --surface: #ffffff; --surface2: #f0ede8;
    --border: rgba(0,0,0,0.1); --border-strong: rgba(0,0,0,0.2);
    --text: #1a1916; --text2: #6b6860; --text3: #9b9890;
    --accent: #2563eb; --accent-bg: #eff6ff; --accent-text: #1d4ed8;
    --danger: #dc2626; --danger-bg: #fef2f2;
    --success: #16a34a; --success-bg: #f0fdf4;
    --warn: #d97706; --warn-bg: #fffbeb;
    --font: 'Sora', sans-serif; --mono: 'IBM Plex Mono', monospace;
    --radius: 8px; --radius-lg: 12px;
    --shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04);
    --shadow-lg: 0 4px 16px rgba(0,0,0,0.1), 0 2px 4px rgba(0,0,0,0.06);
  }

  @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Sora:wght@400;500;600&display=swap');

  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #111110; --surface: #1a1916; --surface2: #222220;
      --border: rgba(255,255,255,0.08); --border-strong: rgba(255,255,255,0.15);
      --text: #f0ede8; --text2: #9b9890; --text3: #6b6860;
      --accent: #3b82f6; --accent-bg: #1e3a5f; --accent-text: #93c5fd;
      --danger: #ef4444; --danger-bg: #3b1515;
      --success: #22c55e; --success-bg: #14301f;
      --warn: #f59e0b; --warn-bg: #312010;
      --shadow: 0 1px 3px rgba(0,0,0,0.4);
      --shadow-lg: 0 4px 16px rgba(0,0,0,0.5);
    }
  }

  body { font-family: var(--font); background: var(--bg); color: var(--text); font-size: 13px; line-height: 1.5; min-height: 100vh; }
  #app { display: flex; flex-direction: column; min-height: 100vh; }

  /* ── Header ── */
  .header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 0 20px; display: flex; align-items: center; gap: 16px; height: 52px; position: sticky; top: 0; z-index: 100; }
  .header-logo { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; color: var(--text); }
  .header-logo .db-icon { width: 28px; height: 28px; background: var(--text); border-radius: 6px; display: flex; align-items: center; justify-content: center; }
  .header-logo .db-icon i { font-size: 15px; color: var(--bg); }
  .header-sep { height: 20px; width: 1px; background: var(--border); }
  .table-pill { display: flex; align-items: center; gap: 6px; background: var(--accent-bg); color: var(--accent-text); border-radius: 6px; padding: 4px 10px; font-size: 12px; font-weight: 500; border: 1px solid rgba(37,99,235,0.15); }
  .header-right { margin-left: auto; display: flex; align-items: center; gap: 8px; }
  .stat-badge { font-family: var(--mono); font-size: 11px; color: var(--text2); background: var(--surface2); border-radius: 5px; padding: 3px 8px; border: 1px solid var(--border); }
  .conn-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--success); display: inline-block; margin-right: 4px; animation: pulse 2s infinite; }
  @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }

  /* ── Toolbar ── */
  .toolbar { background: var(--surface); border-bottom: 1px solid var(--border); padding: 0 20px; display: flex; align-items: center; gap: 8px; height: 44px; }
  .btn { display: inline-flex; align-items: center; gap: 5px; font-family: var(--font); font-size: 12px; font-weight: 500; padding: 5px 11px; border-radius: var(--radius); border: 1px solid var(--border-strong); background: var(--surface); color: var(--text); cursor: pointer; transition: all .15s; white-space: nowrap; }
  .btn:hover { background: var(--surface2); }
  .btn:active { transform: scale(0.97); }
  .btn-primary { background: var(--accent); color: #fff; border-color: var(--accent); }
  .btn-primary:hover { background: var(--accent-text); border-color: var(--accent-text); }
  .btn-danger { color: var(--danger); border-color: transparent; background: transparent; }
  .btn-danger:hover { background: var(--danger-bg); }
  .btn-ghost { border-color: transparent; background: transparent; color: var(--text2); }
  .btn-ghost:hover { background: var(--surface2); color: var(--text); }
  .sep { height: 22px; width: 1px; background: var(--border); margin: 0 2px; }
  .search-wrap { display: flex; align-items: center; gap: 6px; background: var(--surface2); border: 1px solid var(--border); border-radius: var(--radius); padding: 5px 10px; flex: 0 0 220px; transition: border-color .15s; }
  .search-wrap:focus-within { border-color: var(--accent); }
  .search-wrap i { color: var(--text3); font-size: 14px; }
  .search-wrap input { border: none; background: none; font-family: var(--font); font-size: 12px; color: var(--text); outline: none; width: 100%; }
  .search-wrap input::placeholder { color: var(--text3); }
  .toolbar-right { margin-left: auto; display: flex; gap: 8px; }
  .filtered-badge { background: var(--warn-bg); color: var(--warn); border: 1px solid rgba(217,119,6,.2); border-radius: 5px; padding: 2px 8px; font-size: 11px; font-weight: 500; }

  /* ── Main ── */
  .main { display: flex; flex: 1; overflow: hidden; min-height: 0; }

  /* ── Side Panel ── */
  .panel { width: 300px; flex-shrink: 0; background: var(--surface); border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; }
  .panel-header { padding: 14px 16px 10px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
  .panel-title { font-size: 12px; font-weight: 600; color: var(--text); }
  .panel-body { flex: 1; overflow-y: auto; padding: 14px 16px; }
  .field-group { margin-bottom: 12px; }
  .field-label { font-size: 10px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--text3); margin-bottom: 4px; display: block; }
  .field-input { width: 100%; background: var(--surface2); border: 1px solid var(--border); border-radius: var(--radius); padding: 7px 10px; font-family: var(--font); font-size: 12px; color: var(--text); outline: none; transition: border-color .15s, box-shadow .15s; }
  .field-input:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(37,99,235,.1); }
  .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  .panel-footer { padding: 12px 16px; border-top: 1px solid var(--border); display: flex; gap: 8px; }
  .panel-footer .btn { flex: 1; justify-content: center; }

  /* ── Table ── */
  .table-area { flex: 1; overflow: auto; position: relative; }
  .table-wrap { min-width: max-content; }
  table { width: 100%; border-collapse: collapse; font-size: 12px; }
  thead { position: sticky; top: 0; z-index: 10; }
  thead tr { background: var(--surface); border-bottom: 2px solid var(--border); }
  th { padding: 0 14px; height: 36px; font-size: 10px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--text3); text-align: left; white-space: nowrap; border-right: 1px solid var(--border); user-select: none; cursor: pointer; }
  th:hover { background: var(--surface2); color: var(--text2); }
  th.sorted { color: var(--accent); }
  th .th-inner { display: flex; align-items: center; gap: 4px; }
  th.col-id { width: 56px; }
  th.col-actions { width: 100px; cursor: default; }
  th.col-actions:hover { background: var(--surface); }
  tbody tr { border-bottom: 1px solid var(--border); transition: background .1s; }
  tbody tr:hover { background: var(--surface2); }
  tbody tr.selected { background: var(--accent-bg) !important; }
  tbody tr.new-row { animation: rowIn .25s ease; }
  @keyframes rowIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }
  td { padding: 0 14px; height: 38px; border-right: 1px solid var(--border); color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px; }
  td.col-id { font-family: var(--mono); font-size: 11px; color: var(--text3); font-weight: 500; }
  td.col-actions { padding: 0 8px; }
  .row-actions { display: flex; gap: 4px; align-items: center; }
  .action-btn { width: 26px; height: 26px; border-radius: 5px; border: 1px solid var(--border); background: var(--surface); color: var(--text2); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: all .15s; }
  .action-btn:hover { background: var(--surface2); color: var(--text); border-color: var(--border-strong); }
  .action-btn.del:hover { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }

  /* ── Empty state ── */
  .empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; color: var(--text3); gap: 10px; }
  .empty-state i { font-size: 36px; opacity: .4; }
  .empty-state p { font-size: 13px; }

  /* ── Toast ── */
  #toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 999; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
  .toast { padding: 10px 14px; border-radius: var(--radius); background: var(--surface); border: 1px solid var(--border); box-shadow: var(--shadow-lg); font-size: 12px; font-weight: 500; color: var(--text); display: flex; align-items: center; gap: 8px; animation: toastIn .2s ease; pointer-events: all; max-width: 280px; }
  .toast.success { border-left: 3px solid var(--success); }
  .toast.error   { border-left: 3px solid var(--danger); }
  .toast.info    { border-left: 3px solid var(--accent); }
  @keyframes toastIn { from { opacity: 0; transform: translateX(10px); } to { opacity: 1; transform: none; } }

  /* ── Status bar ── */
  .statusbar { background: var(--surface); border-top: 1px solid var(--border); padding: 0 20px; height: 30px; display: flex; align-items: center; gap: 14px; font-family: var(--mono); font-size: 10px; color: var(--text3); }

  /* ── Modal ── */
  .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.35); display: flex; align-items: center; justify-content: center; z-index: 200; animation: fadeIn .15s; }
  @keyframes fadeIn { from{opacity:0} to{opacity:1} }
  .modal { background: var(--surface); border-radius: var(--radius-lg); border: 1px solid var(--border-strong); box-shadow: var(--shadow-lg); width: 340px; animation: scaleIn .15s ease; }
  @keyframes scaleIn { from { transform: scale(.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
  .modal-header { padding: 16px 20px 12px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 600; }
  .modal-body { padding: 16px 20px; font-size: 13px; color: var(--text2); line-height: 1.6; }
  .modal-footer { padding: 12px 20px; border-top: 1px solid var(--border); display: flex; gap: 8px; justify-content: flex-end; }

  .sort-icon { font-size: 11px; }
  select.field-input { cursor: pointer; }
</style>
</head>
<body>
<div id="app">

  <!-- ── Header ── -->
  <header class="header">
    <div class="header-logo">
      <div class="db-icon"><i class="ti ti-database" aria-hidden="true"></i></div>
      StudentDB
    </div>
    <div class="header-sep"></div>
    <div class="table-pill">
      <i class="ti ti-table" aria-hidden="true"></i>
      students
    </div>
    <div class="header-right">
      <span class="stat-badge" id="row-count-badge">…</span>
      <span style="font-size:12px;color:var(--text2);display:flex;align-items:center">
        <span class="conn-dot"></span>
        <?php echo htmlspecialchars($_SESSION['db_host']); ?>
      </span>
      <a href="logout.php" class="btn btn-ghost" style="font-size:11px">
        <i class="ti ti-logout" aria-hidden="true"></i> Disconnect
      </a>
    </div>
  </header>

  <!-- ── Toolbar ── -->
  <div class="toolbar">
    <button class="btn btn-primary" onclick="showPanel('add')">
      <i class="ti ti-plus" aria-hidden="true"></i> New row
    </button>
    <div class="sep"></div>
    <div class="search-wrap">
      <i class="ti ti-search" aria-hidden="true"></i>
      <input type="text" id="search-input" placeholder="Search records…" oninput="handleSearch(this.value)">
    </div>
    <span id="filtered-indicator" class="filtered-badge" style="display:none"></span>
    <div class="sep"></div>
    <button class="btn btn-ghost" onclick="exportCSV()">
      <i class="ti ti-download" aria-hidden="true"></i> Export CSV
    </button>
    <button class="btn btn-ghost" onclick="loadData()">
      <i class="ti ti-refresh" aria-hidden="true"></i> Refresh
    </button>
    <div class="toolbar-right">
      <span style="font-size:11px;color:var(--text3);font-family:var(--mono)" id="query-time"></span>
    </div>
  </div>

  <!-- ── Main ── -->
  <div class="main">

    <!-- Side Panel -->
    <div class="panel" id="side-panel" style="display:none">
      <div class="panel-header">
        <i class="ti ti-plus" aria-hidden="true" id="panel-icon" style="font-size:14px;color:var(--accent)"></i>
        <span class="panel-title" id="panel-title">Add student</span>
        <button class="btn btn-ghost" style="margin-left:auto;padding:3px 6px" onclick="closePanel()">
          <i class="ti ti-x" aria-hidden="true"></i>
        </button>
      </div>
      <div class="panel-body">
        <input type="hidden" id="edit-id">
        <div class="field-row">
          <div class="field-group">
            <label class="field-label">First name</label>
            <input class="field-input" id="f-first" type="text" placeholder="Jane">
          </div>
          <div class="field-group">
            <label class="field-label">Last name</label>
            <input class="field-input" id="f-last" type="text" placeholder="Smith">
          </div>
        </div>
        <div class="field-row">
          <div class="field-group">
            <label class="field-label">Age</label>
            <input class="field-input" id="f-age" type="number" min="15" max="80" placeholder="21">
          </div>
          <div class="field-group">
            <label class="field-label">Year</label>
            <input class="field-input" id="f-year" type="number" min="1" max="6" placeholder="2">
          </div>
        </div>
        <div class="field-group">
          <label class="field-label">College</label>
          <input class="field-input" id="f-college" type="text" placeholder="School of Engineering">
        </div>
        <div class="field-group">
          <label class="field-label">Program</label>
          <input class="field-input" id="f-program" type="text" placeholder="Computer Science">
        </div>
        <div class="field-group">
          <label class="field-label">Semester</label>
          <select class="field-input" id="f-semester">
            <option value="">Select semester…</option>
            <option>Fall</option>
            <option>Spring</option>
            <option>Summer</option>
          </select>
        </div>
      </div>
      <div class="panel-footer">
        <button class="btn" onclick="closePanel()">Cancel</button>
        <button class="btn btn-primary" id="btn-submit" onclick="submitForm()">
          <i class="ti ti-check" aria-hidden="true"></i> Save
        </button>
      </div>
    </div>

    <!-- Table area -->
    <div class="table-area" id="table-area">
      <div class="table-wrap">
        <table id="main-table" aria-label="Student records">
          <thead>
            <tr>
              <th class="col-id" onclick="sortBy('id')"><div class="th-inner"># <span class="sort-icon" id="sort-id">↓</span></div></th>
              <th onclick="sortBy('first_name')"><div class="th-inner">First name <span class="sort-icon" id="sort-first_name"></span></div></th>
              <th onclick="sortBy('last_name')"><div class="th-inner">Last name <span class="sort-icon" id="sort-last_name"></span></div></th>
              <th onclick="sortBy('age')"><div class="th-inner">Age <span class="sort-icon" id="sort-age"></span></div></th>
              <th onclick="sortBy('college_name')"><div class="th-inner">College <span class="sort-icon" id="sort-college_name"></span></div></th>
              <th onclick="sortBy('program_name')"><div class="th-inner">Program <span class="sort-icon" id="sort-program_name"></span></div></th>
              <th onclick="sortBy('year')"><div class="th-inner">Year <span class="sort-icon" id="sort-year"></span></div></th>
              <th onclick="sortBy('semester')"><div class="th-inner">Semester <span class="sort-icon" id="sort-semester"></span></div></th>
              <th class="col-actions">Actions</th>
            </tr>
          </thead>
          <tbody id="table-body">
            <tr><td colspan="9" style="text-align:center;padding:24px;color:var(--text3)">Loading…</td></tr>
          </tbody>
        </table>
        <div id="empty-state" class="empty-state" style="display:none">
          <i class="ti ti-table-off" aria-hidden="true"></i>
          <p>No records found</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Status bar -->
  <div class="statusbar">
    <span><i class="ti ti-circle-check" style="color:var(--success)" aria-hidden="true"></i> <span id="status-text">Ready</span></span>
    <span>· <span id="selected-count">0 selected</span></span>
    <span>· Table: <code style="font-family:var(--mono)">students</code></span>
    <span style="margin-left:auto"><span id="last-op"></span></span>
  </div>

  <!-- Toast container -->
  <div id="toast-container" aria-live="polite"></div>

  <!-- Confirm modal -->
  <div id="modal-backdrop" class="modal-backdrop" style="display:none" onclick="closeModal(event)">
    <div class="modal" onclick="event.stopPropagation()">
      <div class="modal-header" id="modal-title">Delete row</div>
      <div class="modal-body" id="modal-body"></div>
      <div class="modal-footer">
        <button class="btn" onclick="closeModal()">Cancel</button>
        <button class="btn btn-primary" id="modal-confirm"
          style="background:var(--danger);border-color:var(--danger)">Delete</button>
      </div>
    </div>
  </div>

</div>

<script>
// ── State ──────────────────────────────────────────────────────────────────
let allStudents = [];
let sortCol = 'id', sortDir = 'desc';
let searchQ = '';
let selectedIds = new Set();
let panelMode = null;

// ── API helpers ────────────────────────────────────────────────────────────
async function api(action, body = null) {
  const opts = { headers: {} };
  if (body) {
    opts.method = 'POST';
    opts.body = new URLSearchParams({ action, ...body });
  } else {
    opts.method = 'GET';
  }
  const res = await fetch(`api.php?action=${action}`, opts);
  return res.json();
}

// ── Load all rows from server ──────────────────────────────────────────────
async function loadData(flashId) {
  const t0 = performance.now();
  try {
    const data = await api('list');
    if (!data.ok) throw new Error(data.error);
    allStudents = data.rows.map(r => ({ ...r, id: parseInt(r.id), age: parseInt(r.age), year: parseInt(r.year) }));
    const ms = (performance.now() - t0).toFixed(1);
    document.getElementById('query-time').textContent = `${ms} ms`;
    render(flashId);
  } catch (e) {
    toast('Failed to load data: ' + e.message, 'error');
  }
}

// ── Render table ───────────────────────────────────────────────────────────
function getFiltered() {
  const q = searchQ.toLowerCase().trim();
  let rows = q ? allStudents.filter(s =>
    Object.values(s).some(v => String(v).toLowerCase().includes(q))
  ) : [...allStudents];
  rows.sort((a, b) => {
    let va = a[sortCol], vb = b[sortCol];
    if (typeof va === 'string') va = va.toLowerCase();
    if (typeof vb === 'string') vb = vb.toLowerCase();
    if (va < vb) return sortDir === 'asc' ? -1 : 1;
    if (va > vb) return sortDir === 'asc' ? 1 : -1;
    return 0;
  });
  return rows;
}

function render(flashId) {
  const rows = getFiltered();
  const tbody = document.getElementById('table-body');
  const empty = document.getElementById('empty-state');
  document.getElementById('row-count-badge').textContent = `${allStudents.length} row${allStudents.length !== 1 ? 's' : ''}`;

  const fi = document.getElementById('filtered-indicator');
  fi.style.display = searchQ ? 'inline-flex' : 'none';
  if (searchQ) fi.textContent = `${rows.length} of ${allStudents.length} shown`;

  if (rows.length === 0) {
    tbody.innerHTML = '';
    empty.style.display = 'flex';
  } else {
    empty.style.display = 'none';
    tbody.innerHTML = rows.map(s => `
      <tr class="${selectedIds.has(s.id) ? 'selected' : ''} ${s.id === flashId ? 'new-row' : ''}"
          data-id="${s.id}" onclick="rowClick(${s.id}, event)">
        <td class="col-id"><span style="font-size:10px;opacity:.5">#</span>${s.id}</td>
        <td>${esc(s.first_name)}</td>
        <td>${esc(s.last_name)}</td>
        <td>${s.age}</td>
        <td title="${esc(s.college_name)}">${esc(s.college_name)}</td>
        <td title="${esc(s.program_name)}">${esc(s.program_name)}</td>
        <td>${s.year}</td>
        <td><span style="background:var(--surface2);padding:2px 7px;border-radius:4px;border:1px solid var(--border)">${esc(s.semester)}</span></td>
        <td class="col-actions">
          <div class="row-actions">
            <button class="action-btn" title="Edit"
              onclick="event.stopPropagation();showPanel('edit',${s.id})">
              <i class="ti ti-pencil" aria-hidden="true"></i></button>
            <button class="action-btn del" title="Delete"
              onclick="event.stopPropagation();confirmDelete(${s.id})">
              <i class="ti ti-trash" aria-hidden="true"></i></button>
          </div>
        </td>
      </tr>`).join('');
  }
  updateSortIcons();
  updateSelectedCount();
}

function esc(s) {
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function sortBy(col) {
  if (sortCol === col) sortDir = sortDir === 'asc' ? 'desc' : 'asc';
  else { sortCol = col; sortDir = 'asc'; }
  render();
}

function updateSortIcons() {
  ['id','first_name','last_name','age','college_name','program_name','year','semester'].forEach(c => {
    const el = document.getElementById('sort-' + c);
    if (el) el.textContent = c === sortCol ? (sortDir === 'asc' ? '↑' : '↓') : '';
  });
}

function handleSearch(v) { searchQ = v; selectedIds.clear(); render(); }

function rowClick(id, e) {
  if (e.target.closest('.row-actions')) return;
  if (selectedIds.has(id)) selectedIds.delete(id);
  else selectedIds.add(id);
  render();
}

function updateSelectedCount() {
  document.getElementById('selected-count').textContent =
    selectedIds.size > 0 ? `${selectedIds.size} selected` : '0 selected';
}

function setLastOp(sql) {
  document.getElementById('last-op').textContent = sql;
  document.getElementById('status-text').textContent = 'Query OK';
}

// ── Panel ──────────────────────────────────────────────────────────────────
function showPanel(mode, id) {
  panelMode = mode;
  document.getElementById('side-panel').style.display = 'flex';
  document.getElementById('panel-icon').className = `ti ${mode === 'add' ? 'ti-plus' : 'ti-pencil'}`;
  document.getElementById('panel-title').textContent = mode === 'add' ? 'Add student' : 'Edit student';
  document.getElementById('btn-submit').innerHTML =
    mode === 'add'
      ? '<i class="ti ti-plus" aria-hidden="true"></i> Add row'
      : '<i class="ti ti-check" aria-hidden="true"></i> Save changes';

  if (mode === 'edit' && id !== undefined) {
    const s = allStudents.find(x => x.id === id);
    if (!s) return;
    document.getElementById('edit-id').value = id;
    document.getElementById('f-first').value = s.first_name;
    document.getElementById('f-last').value = s.last_name;
    document.getElementById('f-age').value = s.age;
    document.getElementById('f-college').value = s.college_name;
    document.getElementById('f-program').value = s.program_name;
    document.getElementById('f-year').value = s.year;
    document.getElementById('f-semester').value = s.semester;
  } else {
    clearForm();
  }
  setTimeout(() => document.getElementById('f-first').focus(), 50);
}

function closePanel() {
  document.getElementById('side-panel').style.display = 'none';
  panelMode = null;
  clearForm();
}

function clearForm() {
  ['f-first','f-last','f-age','f-college','f-program','f-year'].forEach(id => {
    document.getElementById(id).value = '';
  });
  document.getElementById('f-semester').value = '';
  document.getElementById('edit-id').value = '';
}

function getFormData() {
  return {
    first_name:   document.getElementById('f-first').value.trim(),
    last_name:    document.getElementById('f-last').value.trim(),
    age:          document.getElementById('f-age').value,
    college_name: document.getElementById('f-college').value.trim(),
    program_name: document.getElementById('f-program').value.trim(),
    year:         document.getElementById('f-year').value,
    semester:     document.getElementById('f-semester').value,
  };
}

function validate(d) {
  if (!d.first_name || !d.last_name) return 'First and last name are required.';
  if (!d.age || d.age < 15 || d.age > 80) return 'Age must be between 15 and 80.';
  if (!d.college_name || !d.program_name) return 'College and program are required.';
  if (!d.semester) return 'Please select a semester.';
  return null;
}

async function submitForm() {
  const d = getFormData();
  const err = validate(d);
  if (err) { toast(err, 'error'); return; }

  document.getElementById('btn-submit').disabled = true;

  if (panelMode === 'add') {
    const res = await api('add', d);
    if (!res.ok) { toast('Error: ' + res.error, 'error'); }
    else {
      toast(`Row #${res.id} inserted`, 'success');
      setLastOp(`INSERT INTO students — 1 row affected`);
      closePanel();
      await loadData(parseInt(res.id));
    }
  } else {
    const id = document.getElementById('edit-id').value;
    const res = await api('update', { id, ...d });
    if (!res.ok) { toast('Error: ' + res.error, 'error'); }
    else {
      toast(`Row #${id} updated`, 'success');
      setLastOp(`UPDATE students SET … WHERE id=${id} — 1 row affected`);
      closePanel();
      await loadData();
    }
  }
  document.getElementById('btn-submit').disabled = false;
}

// ── Delete ─────────────────────────────────────────────────────────────────
let pendingDeleteId = null;

function confirmDelete(id) {
  const s = allStudents.find(x => x.id === id);
  if (!s) return;
  pendingDeleteId = id;
  document.getElementById('modal-title').textContent = 'Delete row';
  document.getElementById('modal-body').innerHTML =
    `Are you sure you want to delete <strong>${esc(s.first_name)} ${esc(s.last_name)}</strong> (ID #${id})?<br>This cannot be undone.`;
  document.getElementById('modal-confirm').onclick = doDelete;
  document.getElementById('modal-backdrop').style.display = 'flex';
}

async function doDelete() {
  const id = pendingDeleteId;
  const res = await api('delete', { id });
  if (!res.ok) { toast('Error: ' + res.error, 'error'); }
  else {
    selectedIds.delete(id);
    toast(`Row #${id} deleted`, 'info');
    setLastOp(`DELETE FROM students WHERE id=${id} — 1 row affected`);
    closeModal();
    await loadData();
  }
}

function closeModal(e) {
  if (e && e.target !== e.currentTarget) return;
  document.getElementById('modal-backdrop').style.display = 'none';
  pendingDeleteId = null;
}

// ── Export CSV ─────────────────────────────────────────────────────────────
function exportCSV() {
  const rows = getFiltered();
  const cols = ['id','first_name','last_name','age','college_name','program_name','year','semester'];
  const csv = [cols.join(','),
    ...rows.map(r => cols.map(c => `"${String(r[c]).replace(/"/g,'""')}"`).join(','))
  ].join('\n');
  const a = Object.assign(document.createElement('a'), {
    href: URL.createObjectURL(new Blob([csv], {type:'text/csv'})),
    download: 'students.csv'
  });
  a.click();
  toast(`Exported ${rows.length} rows`, 'success');
}

// ── Toast ──────────────────────────────────────────────────────────────────
function toast(msg, type) {
  const icon = type === 'success' ? 'ti-circle-check' : type === 'error' ? 'ti-alert-circle' : 'ti-info-circle';
  const color = type === 'info' ? 'var(--accent)' : `var(--${type})`;
  const el = Object.assign(document.createElement('div'), { className: `toast ${type}` });
  el.innerHTML = `<i class="ti ${icon}" aria-hidden="true" style="color:${color}"></i>${esc(msg)}`;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => {
    el.style.cssText += 'opacity:0;transition:opacity .3s';
    setTimeout(() => el.remove(), 350);
  }, 2800);
}

// ── Keyboard shortcuts ─────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closePanel(); closeModal(); }
  if ((e.metaKey || e.ctrlKey) && e.key === 'Enter' && panelMode) { submitForm(); e.preventDefault(); }
  if ((e.metaKey || e.ctrlKey) && e.key === 'n' && !panelMode) { showPanel('add'); e.preventDefault(); }
  if (e.key === '/' && !['INPUT','SELECT','TEXTAREA'].includes(document.activeElement.tagName)) {
    document.getElementById('search-input').focus(); e.preventDefault();
  }
});

// ── Init ───────────────────────────────────────────────────────────────────
loadData();
</script>
</body>
</html>
