<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Basecamp</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>
  .sidebar{width:200px;min-height:100vh}
  .logo{height:56px;border:2px dashed #adb5bd;color:#6c757d}
  .sidebar .nav-link{color:#212529;border-radius:.375rem}
  .sidebar .nav-link.active{background:#198754;color:#fff}
  section{display:none}section.show{display:block}
</style></head>
<body class="bg-light">
<div class="d-flex">
  <aside class="sidebar bg-white border-end p-3">
    <div class="logo d-flex align-items-center justify-content-center mb-4 small">[ Logo ]</div>
    <nav class="nav flex-column gap-1">
      <a class="nav-link active" href="#dashboard">Dashboard</a>
      <a class="nav-link" href="#nodes">Nodes</a>
      <a class="nav-link" href="#analytics">Analytics</a>
      <a class="nav-link" href="#system">System</a>
    </nav>
  </aside>
  <main class="flex-grow-1 p-4">

    <section id="dashboard" class="show">
      <div class="row g-3 mb-4 text-center">
        <div class="col"><div class="card"><div class="card-body"><div class="text-muted small">Total Nodes</div><div class="fs-2" id="s-total">0</div></div></div></div>
        <div class="col"><div class="card"><div class="card-body"><div class="text-success small">Online</div><div class="fs-2" id="s-on">0</div></div></div></div>
        <div class="col"><div class="card"><div class="card-body"><div class="text-danger small">Offline</div><div class="fs-2" id="s-off">0</div></div></div></div>
      </div>
      <div class="row g-3 mb-4" id="node-cards"></div>
      <h6>Recent Events</h6>
      <ul class="list-group" id="events"></ul>
    </section>

    <section id="nodes">
      <h5>Nodes</h5>
      <table class="table table-sm bg-white"><thead><tr>
        <th>Node</th><th>Status</th><th>Moisture</th><th>Soil °C</th><th>Air °C</th><th>Humidity</th><th>pH</th><th>MQ2</th><th>Light</th><th>Last seen</th>
      </tr></thead><tbody id="node-rows"></tbody></table>
    </section>

    <section id="analytics">
      <h5>Analytics</h5>
      <div class="d-flex gap-2 mb-3">
        <select id="a-node" class="form-select w-auto"><option value="">All nodes</option></select>
        <select id="a-metric" class="form-select w-auto">
          <option value="soil_moisture">Soil moisture</option><option value="soil_temp">Soil temp</option>
          <option value="air_temp">Air temp</option><option value="humidity">Humidity</option>
          <option value="ph">pH</option><option value="mq2">MQ2</option><option value="light">Light</option>
        </select>
        <div class="btn-group" id="a-period">
          <button class="btn btn-outline-success active" data-p="day">Day</button>
          <button class="btn btn-outline-success" data-p="week">Week</button>
          <button class="btn btn-outline-success" data-p="month">Month</button>
        </div>
      </div>
      <div class="card"><div class="card-body"><canvas id="chart" height="100"></canvas></div></div>
    </section>

    <section id="system">
      <h5>System</h5>
      <table class="table table-sm bg-white w-auto"><tbody id="sys"></tbody></table>
    </section>

  </main>
</div>
<script>
const $ = s => document.querySelector(s);
const ago = s => s < 60 ? s + ' sec ago' : s < 3600 ? Math.floor(s/60) + ' min ago' : Math.floor(s/3600) + ' h ago';
const nn = id => 'Node ' + String(id).padStart(2, '0');
const esc = v => String(v ?? '—').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const f = (v, d = 1) => v == null ? '—' : Number(v).toFixed(d);
let period = 'day', chart, known = '';

async function api(q) { const r = await fetch('api.php?' + q); return r.json(); }

async function refresh() {
  try {
    const { nodes, events } = await api('action=summary');
    const on = nodes.filter(n => n.online).length;
    $('#s-total').textContent = nodes.length; $('#s-on').textContent = on; $('#s-off').textContent = nodes.length - on;
    $('#node-cards').innerHTML = nodes.map(n => `
      <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body">
        <div class="d-flex justify-content-between"><strong>${nn(n.id)}</strong>
          <span class="badge ${n.online ? 'bg-success' : 'bg-danger'}">${n.online ? 'online' : 'offline'}</span></div>
        <div class="small mt-2">Soil Moisture <span class="float-end">${f(n.soil_moisture,0)}%</span></div>
        <div class="small">Temperature <span class="float-end">${f(n.soil_temp)}°C</span></div>
        <div class="small">Humidity <span class="float-end">${f(n.humidity,0)}%</span></div>
        <div class="small">pH <span class="float-end">${f(n.ph)}</span></div>
        <div class="small text-muted">Last Seen <span class="float-end">${ago(n.ago)}</span></div>
      </div></div></div>`).join('') || '<div class="text-muted">No nodes yet.</div>';
    $('#node-rows').innerHTML = nodes.map(n => `<tr><td>${nn(n.id)}</td>
      <td><span class="badge ${n.online ? 'bg-success' : 'bg-danger'}">${n.online ? 'online' : 'offline'}</span></td>
      <td>${f(n.soil_moisture,0)}%</td><td>${f(n.soil_temp)}</td><td>${f(n.air_temp)}</td><td>${f(n.humidity,0)}%</td>
      <td>${f(n.ph)}</td><td>${esc(n.mq2)}</td><td>${esc(n.light)}</td><td>${ago(n.ago)}</td></tr>`).join('');
    $('#events').innerHTML = events.map(e => `<li class="list-group-item small">${nn(e.node_id)} — ${esc(e.message)}
      <span class="float-end text-muted">${new Date(e.ts*1000).toLocaleTimeString()}</span></li>`).join('')
      || '<li class="list-group-item small text-muted">No events</li>';
    const ids = nodes.map(n => n.id).join(',');
    if (ids !== known) { known = ids;
      $('#a-node').innerHTML = '<option value="">All nodes</option>' + nodes.map(n => `<option value="${n.id}">${nn(n.id)}</option>`).join(''); }
  } catch (e) { console.error(e); }
}

async function loadChart() {
  const d = await api(`action=analytics&period=${period}&metric=${$('#a-metric').value}&node=${$('#a-node').value}`);
  chart?.destroy();
  chart = new Chart($('#chart'), { type: 'line',
    data: { labels: d.map(r => r.l), datasets: [{ label: $('#a-metric').selectedOptions[0].text, data: d.map(r => r.v),
      borderColor: '#198754', tension: .3, pointRadius: 2 }] }, options: { plugins: { legend: { display: false } } } });
}

async function loadSystem() {
  const s = await api('action=system');
  $('#sys').innerHTML = Object.entries(s).map(([k, v]) => `<tr><th>${esc(k)}</th><td>${esc(v)}</td></tr>`).join('');
}

function route() {
  const h = (location.hash || '#dashboard').slice(1);
  document.querySelectorAll('section').forEach(s => s.classList.toggle('show', s.id === h));
  document.querySelectorAll('.nav-link').forEach(a => a.classList.toggle('active', a.hash === '#' + h));
  if (h === 'analytics') loadChart(); if (h === 'system') loadSystem();
}
$('#a-period').onclick = e => { if (!e.target.dataset.p) return; period = e.target.dataset.p;
  document.querySelectorAll('#a-period .btn').forEach(b => b.classList.toggle('active', b === e.target)); loadChart(); };
$('#a-node').onchange = $('#a-metric').onchange = loadChart;
addEventListener('hashchange', route);
route(); refresh(); setInterval(refresh, 5000);
</script>
</body></html>
