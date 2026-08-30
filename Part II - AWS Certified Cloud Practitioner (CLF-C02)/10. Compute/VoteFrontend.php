<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Infrastructure Ballot</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,900&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --paper:      #f3ede0;
    --paper-line: #ded1b8;
    --card:       #fffcf4;
    --ink:        #241c15;
    --ink-soft:   #71614e;
    --seal:       #8c2f39;
    --brass:      #a1782f;
    --aws:        #a15c1f;
    --azure:      #2b4f74;
    --gcp:        #3a6b3e;
    --onprem:     #5c4470;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background-color: var(--paper);
    background-image:
      repeating-linear-gradient(0deg, rgba(36,28,21,0.02) 0px, rgba(36,28,21,0.02) 1px, transparent 1px, transparent 3px),
      repeating-linear-gradient(90deg, rgba(36,28,21,0.015) 0px, rgba(36,28,21,0.015) 1px, transparent 1px, transparent 3px);
    color: var(--ink);
    font-family: 'IBM Plex Sans', sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2.5rem 1rem;
  }

  .ballot {
    position: relative;
    width: 100%;
    max-width: 560px;
    background: var(--card);
    border: 1px solid var(--paper-line);
    padding: 2.75rem 2.5rem 2.25rem;
  }

  .ballot::before {
    content: '';
    position: absolute;
    inset: 8px;
    border: 1px solid var(--paper-line);
    pointer-events: none;
  }

  .registry {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.68rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--brass);
    text-align: center;
    margin-bottom: 0.5rem;
  }

  h1 {
    font-family: 'Fraunces', serif;
    font-weight: 700;
    font-optical-sizing: auto;
    font-size: clamp(1.5rem, 4.6vw, 2rem);
    line-height: 1.25;
    text-align: center;
    color: var(--ink);
    margin-bottom: 0.4rem;
  }

  .prompt {
    text-align: center;
    color: var(--ink-soft);
    font-size: 0.88rem;
    margin-bottom: 2.1rem;
  }

  .rule {
    height: 1px;
    background: var(--paper-line);
    margin: 0 0 1.6rem;
  }

  .choices { margin-bottom: 2rem; }

  .choice-row {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.9rem;
    width: 100%;
    background: none;
    border: none;
    border-bottom: 1px solid var(--paper-line);
    padding: 0.95rem 0.15rem;
    cursor: pointer;
    font-family: inherit;
    color: var(--ink);
    text-align: left;
  }

  .choice-row:first-child { border-top: 1px solid var(--paper-line); }

  .numeral {
    font-family: 'Fraunces', serif;
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--accent);
    width: 1.6rem;
  }

  .choice-label {
    flex: 1;
    font-size: 0.98rem;
    font-weight: 500;
  }

  .box {
    width: 17px;
    height: 17px;
    border: 1.5px solid var(--ink-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: border-color .2s, background .2s;
  }

  .box::after {
    content: '';
    width: 9px;
    height: 9px;
    background: var(--accent);
    opacity: 0;
    transition: opacity .15s;
  }

  .choice-row[data-choice="AWS"]     { --accent: var(--aws); }
  .choice-row[data-choice="Azure"]   { --accent: var(--azure); }
  .choice-row[data-choice="GCP"]     { --accent: var(--gcp); }
  .choice-row[data-choice="On Prem"] { --accent: var(--onprem); }

  .choice-row.marked .box { border-color: var(--accent); }
  .choice-row.marked .box::after { opacity: 1; }
  .choice-row.marked .choice-label { color: var(--accent); }

  .section-label {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.66rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink-soft);
    margin-bottom: 1.1rem;
  }

  .ledger-row { margin-bottom: 1rem; }

  .ledger-head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 0.35rem;
  }

  .ledger-name {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--ink);
  }

  .ledger-figures {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.85rem;
    display: flex;
    gap: 0.7rem;
  }

  .ledger-count { color: var(--ink-soft); }
  .ledger-pct   { color: var(--accent); font-weight: 600; }

  .ledger-row[data-choice="AWS"]     { --accent: var(--aws); }
  .ledger-row[data-choice="Azure"]   { --accent: var(--azure); }
  .ledger-row[data-choice="GCP"]     { --accent: var(--gcp); }
  .ledger-row[data-choice="On Prem"] { --accent: var(--onprem); }

  .track {
    height: 4px;
    background: var(--paper-line);
    position: relative;
  }

  .fill {
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 0%;
    background: var(--accent);
    transition: width .7s cubic-bezier(.22,1,.36,1);
  }

  .foot {
    margin-top: 1.9rem;
    padding-top: 1.1rem;
    border-top: 1px solid var(--paper-line);
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.75rem;
    color: var(--ink-soft);
  }

  .status { display: flex; align-items: center; gap: 0.4rem; }

  .status .pip {
    width: 6px; height: 6px;
    background: var(--gcp);
    border-radius: 50%;
    animation: breathe 2.2s infinite;
  }

  @keyframes breathe {
    0%, 100% { opacity: .4; }
    50%      { opacity: 1; }
  }

  .stamp {
    position: absolute;
    top: 38%;
    right: 8%;
    width: 122px;
    height: 122px;
    border: 3px solid var(--seal);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--seal);
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    transform: rotate(-16deg) scale(0);
    opacity: 0;
    pointer-events: none;
  }

  .stamp::before {
    content: '';
    position: absolute;
    inset: 6px;
    border: 1px solid var(--seal);
    border-radius: 50%;
  }

  .stamp.show {
    animation: stampIn .5s cubic-bezier(.2,1.4,.4,1) forwards;
  }

  @keyframes stampIn {
    0%   { transform: rotate(-16deg) scale(2.4); opacity: 0; }
    60%  { transform: rotate(-16deg) scale(0.92); opacity: 1; }
    100% { transform: rotate(-16deg) scale(1);    opacity: 1; }
  }

  #note {
    position: fixed;
    bottom: 1.6rem;
    left: 50%;
    transform: translate(-50%, 12px);
    background: var(--ink);
    color: var(--paper);
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.78rem;
    padding: 0.55rem 1.1rem;
    opacity: 0;
    transition: opacity .25s, transform .25s;
    pointer-events: none;
  }
  #note.show { opacity: 1; transform: translate(-50%, 0); }

  @media (max-width: 480px) {
    .stamp { width: 96px; height: 96px; font-size: 0.62rem; top: auto; bottom: -18px; right: -12px; }
  }
</style>
</head>
<body>
<div id="note"></div>

<div class="ballot">
  <div class="stamp" id="stamp">Recorded</div>

  <p class="registry">Infrastructure Ballot &middot; Live Registry</p>
  <h1>Which platform carries most of your workloads today?</h1>
  <p class="prompt">One entry per visit. Results are tallied as they arrive.</p>

  <div class="rule"></div>

  <div class="choices" id="choices">
    <?php
    $roman = ['AWS' => 'I', 'Azure' => 'II', 'GCP' => 'III', 'On Prem' => 'IV'];
    foreach (['AWS', 'Azure', 'GCP', 'On Prem'] as $choice): ?>
    <button class="choice-row" data-choice="<?= $choice ?>" onclick="cast('<?= $choice ?>')">
      <span class="numeral"><?= $roman[$choice] ?></span>
      <span class="choice-label"><?= $choice ?></span>
      <span class="box"></span>
    </button>
    <?php endforeach; ?>
  </div>

  <p class="section-label">Running Tally</p>

  <div id="ledger">
    <?php foreach (['AWS', 'Azure', 'GCP', 'On Prem'] as $choice): ?>
    <div class="ledger-row" data-choice="<?= $choice ?>">
      <div class="ledger-head">
        <span class="ledger-name"><?= $choice ?></span>
        <span class="ledger-figures">
          <span class="ledger-count" id="count-<?= $choice ?>">0</span>
          <span class="ledger-pct" id="pct-<?= $choice ?>">0%</span>
        </span>
      </div>
      <div class="track"><div class="fill" id="fill-<?= $choice ?>"></div></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="foot">
    <span class="status"><span class="pip"></span> Updating</span>
    <span>Entries counted: <span id="total">0</span></span>
  </div>
</div>

<script>
const CHOICES = ['AWS', 'Azure', 'GCP', 'On Prem'];
let recorded = localStorage.getItem('infra_ballot');

if (recorded) {
  document.querySelector(`.choice-row[data-choice="${recorded}"]`)?.classList.add('marked');
}

async function cast(choice) {
  if (recorded) {
    note('You already voted for ' + recorded);
    return;
  }
  recorded = choice;
  localStorage.setItem('infra_ballot', choice);
  document.querySelector(`.choice-row[data-choice="${choice}"]`).classList.add('marked');

  try {
    const response = await fetch('/vote.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ choice })
    });
    render(await response.json());
    document.getElementById('stamp').classList.add('show');
    note('Entry recorded for ' + choice);
  } catch {
    note('Connection lost. Try again.');
  }
}

async function refresh() {
  try {
    render(await (await fetch('/vote.php')).json());
  } catch {}
  setTimeout(refresh, 4000);
}

function render({ tally, total }) {
  document.getElementById('total').textContent = total;
  CHOICES.forEach(choice => {
    const count = tally[choice] || 0;
    const pct = total ? Math.round((count / total) * 100) : 0;
    document.getElementById('count-' + choice).textContent = count;
    document.getElementById('pct-' + choice).textContent = pct + '%';
    document.getElementById('fill-' + choice).style.width = pct + '%';
  });
}

function note(message) {
  const el = document.getElementById('note');
  el.textContent = message;
  el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 2600);
}

refresh();
</script>
</body>
</html>
