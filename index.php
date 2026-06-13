<?php
require 'config.php';
$db = connectDB();
$stations_result = $db->query("SELECT DISTINCT station_id FROM sensor_data ORDER BY station_id");
$stations = [];
while ($row = $stations_result->fetch_assoc()) $stations[] = $row['station_id'];

$names_result = $db->query("SELECT station_id, name FROM stations");
$station_names = [];
if ($names_result) {
    while ($row = $names_result->fetch_assoc()) {
        $station_names[$row['station_id']] = $row['name'];
    }
}
foreach ($stations as $sid) {
    if (!isset($station_names[$sid])) {
        $ins = $db->prepare("INSERT IGNORE INTO stations (station_id, name) VALUES (?, '')");
        $ins->bind_param("i", $sid);
        $ins->execute();
        $ins->close();
        $station_names[$sid] = '';
    }
}
$all_presets = $db->query("SELECT * FROM fish_presets ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fish Farm Monitor</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
/* ── Dark mode (default) ── */
:root {
  --bg:#0a0a0a; --panel:#111; --border:#c8a84b; --border-dim:#3a2e10;
  --text:#e8e0cc; --text-dim:#7a7060;
  --ok:#2d6a2d; --ok-t:#7dda7d; --alert:#6a1f1f; --alert-t:#ff6b6b;
  --glow-rgb:200,168,75; --glow-ok-rgb:60,180,60; --glow-red-rgb:220,40,40;
  --pending-bg:#1a1a1a;
  --donut-empty:#1a1a1a;
  --scrollbar:#3a2e10;
}
/* ── Light mode ── */
body.light {
  --bg:#f0f0f0; --panel:#ffffff; --border:#111111; --border-dim:#cccccc;
  --text:#111111; --text-dim:#666666;
  --ok:#d4edda; --ok-t:#1a6630; --alert:#f8d7da; --alert-t:#842029;
  --glow-rgb:0,0,0; --glow-ok-rgb:20,140,40; --glow-red-rgb:180,30,30;
  --pending-bg:#eeeeee;
  --donut-empty:#dddddd;
  --scrollbar:#cccccc;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--text);font-family:'Share Tech Mono',monospace;height:100vh;display:flex;overflow:hidden;}

/* ── Sidebar ── */
#sidebar{position:relative;width:180px;min-width:180px;background:var(--panel);border-right:1px solid var(--border-dim);display:flex;flex-direction:column;padding:24px 16px;gap:12px;transition:width .25s ease,min-width .25s ease,padding .25s ease;}
#sidebar.collapsed{width:52px;min-width:52px;padding:16px 8px;}
#sidebar.collapsed .sidebar-logo{opacity:0;height:0;padding:0;margin:0;border:none;overflow:hidden;}
#sidebar.collapsed .nav-btn-label{display:none;}
#sidebar.collapsed .nav-btn{padding:12px;text-align:center;justify-content:center;}
#sidebar.collapsed .theme-toggle{padding:10px 6px;}
#sidebar.collapsed .theme-label{display:none;}
.nav-btn{display:flex;align-items:center;gap:10px;}
.nav-btn-icon{font-size:.9rem;flex-shrink:0;}
.sidebar-logo{font-family:'Orbitron',sans-serif;font-size:.7rem;color:var(--border);letter-spacing:3px;padding-bottom:20px;border-bottom:1px solid var(--border-dim);margin-bottom:8px;line-height:1.6;transition:opacity .2s,height .25s;overflow:hidden;}
.nav-btn{background:transparent;border:1px solid var(--border-dim);color:var(--text-dim);font-family:'Orbitron',sans-serif;font-size:.7rem;letter-spacing:2px;padding:12px 16px;cursor:pointer;text-align:left;transition:all .2s;text-transform:uppercase;}
.nav-btn:hover,.nav-btn.active{border-color:var(--border);color:var(--border);background:rgba(var(--glow-rgb),.05);box-shadow:0 0 14px rgba(var(--glow-rgb),.3),0 0 4px rgba(var(--glow-rgb),.1);}

/* ── Pages ── */
#main{flex:1;overflow:hidden;display:flex;flex-direction:column;}
.page{display:none;width:100%;height:100%;}
.page.active{display:flex;flex-direction:column;}
#farms-page,#history-page,#preset-page,#logs-page{padding:28px;gap:16px;overflow-y:auto;}
.page-title{font-family:'Orbitron',sans-serif;font-size:.65rem;letter-spacing:4px;color:var(--border);text-transform:uppercase;padding-bottom:16px;border-bottom:1px solid var(--border-dim);}

/* ── Station cards ── */
#cards-container{display:flex;flex-wrap:wrap;gap:20px;padding-top:8px;}
.station-card{width:220px;background:var(--panel);border:1px solid var(--border-dim);padding:18px 14px 14px;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:10px;transition:border-color .25s, box-shadow .25s;}
.station-card:hover{border-color:var(--border);box-shadow:0 0 22px rgba(var(--glow-rgb),.25),0 0 6px rgba(var(--glow-rgb),.1);}
.station-card.glow-ok{border-color:#3a7a3a;box-shadow:0 0 20px rgba(var(--glow-ok-rgb),.2);}
.station-card.glow-ok:hover{border-color:var(--ok-t);box-shadow:0 0 28px rgba(var(--glow-ok-rgb),.3);}
.station-card.glow-alert{border-color:#7a2020;box-shadow:0 0 20px rgba(var(--glow-red-rgb),.2);}
.station-card.glow-alert:hover{border-color:var(--alert-t);box-shadow:0 0 28px rgba(var(--glow-red-rgb),.35);}
.card-title{font-family:'Orbitron',sans-serif;font-size:.7rem;letter-spacing:2px;}
.donut-wrap{position:relative;width:120px;height:120px;}
.donut-wrap canvas{position:absolute;top:0;left:0;}
.card-readings{width:100%;border:1px solid var(--border-dim);padding:10px 12px;font-size:.8rem;line-height:2;}
.card-target{width:100%;padding:5px 10px;font-size:.68rem;color:var(--text-dim);border:1px solid var(--border-dim);line-height:1.7;}
.card-status{width:100%;padding:6px 10px;font-size:.7rem;letter-spacing:1px;}
.card-status.ok{background:var(--ok);color:var(--ok-t);}
.card-status.alert{background:var(--alert);color:var(--alert-t);}
.card-status.pending{background:var(--pending-bg);color:var(--text-dim);}

/* ── Buttons ── */
.btn-gold{background:transparent;border:1px solid var(--border);color:var(--border);font-family:'Orbitron',sans-serif;font-size:.6rem;letter-spacing:2px;padding:8px 16px;cursor:pointer;transition:all .2s;}
.btn-gold:hover{background:rgba(var(--glow-rgb),.1);box-shadow:0 0 10px rgba(var(--glow-rgb),.15);}
.btn-dim{background:transparent;border:1px solid var(--border-dim);color:var(--text-dim);font-family:'Orbitron',sans-serif;font-size:.6rem;letter-spacing:2px;padding:8px 16px;cursor:pointer;transition:all .2s;}
.btn-dim:hover{border-color:#666;color:#999;}
.btn-red{background:transparent;border:1px solid var(--border-dim);color:var(--text-dim);font-family:'Orbitron',sans-serif;font-size:.6rem;letter-spacing:2px;padding:6px 14px;cursor:pointer;transition:all .2s;}
.btn-red:hover{border-color:#aa3333;color:#cc4444;background:rgba(160,40,40,.08);}
.btn-save{background:transparent;border:1px solid var(--border-dim);color:var(--text-dim);font-family:'Orbitron',sans-serif;font-size:.6rem;letter-spacing:2px;padding:6px 14px;cursor:pointer;transition:all .2s;}
.btn-save:hover{border-color:#3a9a3a;color:#5dcc5d;background:rgba(40,140,40,.08);box-shadow:0 0 14px rgba(var(--glow-ok-rgb),.4),0 0 4px rgba(var(--glow-ok-rgb),.2);}
.btn-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}

/* ── Modal ── */
#modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:100;align-items:center;justify-content:center;}
#modal-overlay.open{display:flex;}
#modal{background:var(--panel);border:1px solid var(--border);width:680px;max-width:96vw;max-height:92vh;overflow-y:auto;padding:28px;display:flex;flex-direction:column;gap:20px;}
.modal-header{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border-dim);padding-bottom:14px;gap:10px;}
.modal-header h2{font-family:'Orbitron',sans-serif;font-size:.9rem;letter-spacing:3px;color:var(--border);}
.modal-header-btns{display:flex;gap:8px;}
.modal-readings{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
.reading-box{border:1px solid var(--border-dim);padding:14px;display:flex;flex-direction:column;gap:4px;transition:box-shadow .2s;}
.reading-box:hover{border-color:var(--border-dim);box-shadow:0 0 14px rgba(var(--glow-rgb),.2);}
.reading-label{font-size:.65rem;letter-spacing:2px;color:var(--text-dim);text-transform:uppercase;}
.reading-value{font-family:'Orbitron',sans-serif;font-size:1.4rem;}
.reading-range{font-size:.7rem;color:var(--text-dim);}
.section-title{font-family:'Orbitron',sans-serif;font-size:.65rem;letter-spacing:3px;color:var(--border);text-transform:uppercase;margin-bottom:10px;}

/* ── Preset chips ── */
.preset-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;min-height:28px;}
.chip{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border:1px solid var(--border);font-size:.72rem;color:var(--border);transition:box-shadow .2s;cursor:default;}
.chip:hover{box-shadow:0 0 8px rgba(var(--glow-rgb),.2);}
.chip-x{background:none;border:none;color:var(--border);cursor:pointer;font-size:1rem;line-height:1;padding:0 2px;opacity:.6;transition:all .15s;}
.chip-x:hover{opacity:1;color:var(--alert-t);text-shadow:0 0 6px rgba(255,80,80,.5);}

/* ── Preset picker ── */
#preset-picker{display:none;border:1px solid var(--border-dim);padding:16px;margin-top:8px;}
#preset-picker.open{display:block;}
.picker-grid{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px;}
.picker-item{display:flex;align-items:center;gap:8px;border:1px solid var(--border-dim);padding:8px 12px;cursor:pointer;transition:border-color .2s;}
.picker-item:hover{border-color:var(--border);box-shadow:0 0 12px rgba(var(--glow-rgb),.3);}
.picker-item input{accent-color:var(--border);}
.picker-item label{font-size:.78rem;cursor:pointer;}

/* ── Override section ── */
.override-toggle{background:none;border:none;color:var(--text-dim);font-family:'Orbitron',sans-serif;font-size:.6rem;letter-spacing:2px;cursor:pointer;padding:0;text-transform:uppercase;display:flex;align-items:center;gap:6px;transition:color .2s;}
.override-toggle:hover{color:var(--border);text-shadow:0 0 8px rgba(var(--glow-rgb),.3);}
.override-toggle .arrow{display:inline-block;transition:transform .2s;font-style:normal;}
.override-toggle.open .arrow{transform:rotate(90deg);}
#manual-body{display:none;margin-top:12px;}
#manual-body.open{display:block;}
.manual-hint{font-size:.7rem;color:var(--text-dim);margin-bottom:12px;padding:8px 10px;border-left:2px solid var(--border-dim);line-height:1.8;}
.manual-hint b{color:var(--border);}
.manual-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:12px;}
.manual-field label{font-size:.62rem;letter-spacing:1px;color:var(--text-dim);text-transform:uppercase;display:block;margin-bottom:4px;}
.manual-field input{background:var(--bg);border:1px solid var(--border-dim);color:var(--text);font-family:'Share Tech Mono',monospace;font-size:.9rem;padding:7px 9px;outline:none;width:100%;}
.manual-field input:focus{border-color:var(--border);}
.modal-msg{font-size:.75rem;color:var(--ok-t);min-height:1em;margin-top:8px;}

/* ── History page ── */
.history-table{width:100%;border-collapse:collapse;font-size:.78rem;}
.history-table th{font-family:'Orbitron',sans-serif;font-size:.6rem;letter-spacing:2px;color:var(--border);border-bottom:1px solid var(--border-dim);padding:8px 10px;text-align:left;}
.history-table td{padding:7px 10px;border-bottom:1px solid #1a1a1a;color:var(--text-dim);}
.history-table tr:hover td{color:var(--text);background:rgba(255,255,255,.02);}
.status-ok{color:var(--ok-t)!important;}
.status-alert{color:var(--alert-t)!important;}
#history-station-select{background:var(--panel);border:1px solid var(--border-dim);color:var(--text);font-family:'Share Tech Mono',monospace;padding:8px 12px;font-size:.85rem;outline:none;}
.hist-controls{display:flex;align-items:flex-end;gap:16px;flex-wrap:wrap;}
.hist-control-group{display:flex;flex-direction:column;gap:4px;}
.hist-label{font-size:.6rem;letter-spacing:2px;color:var(--text-dim);text-transform:uppercase;font-family:'Orbitron',sans-serif;}
.hist-controls input[type=number]{background:var(--panel);border:1px solid var(--border-dim);color:var(--text);font-family:'Share Tech Mono',monospace;padding:8px 10px;font-size:.85rem;outline:none;}
.hist-controls input[type=number]:focus{border-color:var(--border);box-shadow:0 0 10px rgba(var(--glow-rgb),.2);}
.hist-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;min-height:0;}
.hist-panel{display:flex;flex-direction:column;gap:8px;min-width:0;}
.hist-scroll{overflow-x:auto;}
.hist-count{font-family:'Share Tech Mono',monospace;font-size:.65rem;color:var(--text-dim);letter-spacing:0;margin-left:8px;text-transform:none;}
.history-table .source-preset{color:var(--border);}
.history-table .source-manual{color:#aaa8e0;}
.history-table .source-none{color:var(--text-dim);}
@media(max-width:900px){.hist-grid{grid-template-columns:1fr;}}

/* ── Preset library ── */
.preset-lib-grid{display:flex;flex-wrap:wrap;gap:16px;padding-top:8px;}
.preset-lib-card{width:220px;background:var(--panel);border:1px solid var(--border-dim);display:flex;flex-direction:column;transition:border-color .2s,box-shadow .2s;}
.preset-lib-card:hover{border-color:var(--border);box-shadow:0 0 18px rgba(var(--glow-rgb),.15);}
.preset-lib-card.new-card{border-style:dashed;cursor:pointer;align-items:center;justify-content:center;min-height:220px;color:var(--text-dim);font-family:'Orbitron',sans-serif;font-size:.7rem;letter-spacing:2px;}
.preset-lib-card.new-card:hover{border-color:var(--border);color:var(--border);}
.lib-card-img{width:100%;height:110px;object-fit:cover;display:block;}
.lib-card-img-placeholder{width:100%;height:110px;display:flex;align-items:center;justify-content:center;color:var(--border-dim);font-size:.6rem;letter-spacing:1px;background:#0d0d0d;}
.lib-card-body{padding:14px;display:flex;flex-direction:column;gap:8px;}
.lib-card-name{font-family:'Orbitron',sans-serif;font-size:.75rem;letter-spacing:2px;color:var(--border);}
.lib-card-range{font-size:.72rem;color:var(--text-dim);line-height:1.9;}
.lib-card-del{background:transparent;border:1px solid #3a1a1a;color:#884444;font-family:'Orbitron',sans-serif;font-size:.6rem;letter-spacing:2px;padding:6px;cursor:pointer;transition:all .2s;}
.lib-card-del:hover{border-color:var(--alert-t);color:var(--alert-t);box-shadow:0 0 10px rgba(255,80,80,.2);}

/* ── New preset form ── */
#new-preset-form{display:none;background:var(--panel);border:1px solid var(--border);padding:22px;margin-bottom:8px;}
#new-preset-form.open{display:block;}
.form-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:14px 0;}
.form-field label{font-size:.62rem;letter-spacing:1px;color:var(--text-dim);text-transform:uppercase;display:block;margin-bottom:4px;}
.form-field input{background:var(--bg);border:1px solid var(--border-dim);color:var(--text);font-family:'Share Tech Mono',monospace;font-size:.88rem;padding:7px 9px;outline:none;width:100%;}
.form-field input:focus{border-color:var(--border);}
.form-row{margin-bottom:10px;}
.form-row label{font-size:.62rem;letter-spacing:1px;color:var(--text-dim);text-transform:uppercase;display:block;margin-bottom:4px;}
.form-row input{width:100%;background:var(--bg);border:1px solid var(--border-dim);color:var(--text);font-family:'Share Tech Mono',monospace;font-size:.88rem;padding:8px 10px;outline:none;}
.form-row input:focus{border-color:var(--border);}

/* ── Log form inputs ── */
.log-input{width:100%;background:var(--bg);border:1px solid var(--border-dim);color:var(--text);font-family:'Share Tech Mono',monospace;font-size:.88rem;padding:7px 9px;outline:none;}
.log-input:focus{border-color:var(--border);}
.log-label{font-size:.62rem;letter-spacing:1px;color:var(--text-dim);text-transform:uppercase;display:block;margin-bottom:4px;}

::-webkit-scrollbar{width:4px;}
.theme-toggle{background:transparent;border:1px solid var(--border-dim);color:var(--text-dim);font-size:1rem;padding:10px;cursor:pointer;transition:all .2s;width:100%;text-align:center;}
.theme-toggle:hover{border-color:var(--border);color:var(--border);box-shadow:0 0 12px rgba(var(--glow-rgb),.3);}
.collapse-tab{position:absolute;top:50%;right:0;transform:translateY(-50%);background:var(--panel);border:1px solid var(--border-dim);border-right:none;color:var(--text-dim);font-size:.65rem;padding:10px 4px;cursor:pointer;transition:color .2s,border-color .2s,box-shadow .2s;border-radius:4px 0 0 4px;line-height:1;z-index:5;}
.collapse-tab:hover{border-color:var(--border);color:var(--border);box-shadow:-4px 0 12px rgba(var(--glow-rgb),.25);}
::-webkit-scrollbar-track{background:var(--bg);}
::-webkit-scrollbar-thumb{background:var(--scrollbar);}
input:focus, select:focus{outline:none;border-color:var(--border) !important;box-shadow:0 0 12px rgba(var(--glow-rgb),.25);}
select:hover{border-color:#666;box-shadow:0 0 8px rgba(var(--glow-rgb),.1);}
.donut-wrap:hover canvas{filter:drop-shadow(0 0 6px rgba(var(--glow-rgb),.3));}
#modal{box-shadow:0 0 40px rgba(var(--glow-rgb),.1);transition:box-shadow .3s;}
.card-readings:hover, .card-target:hover{border-color:#555;box-shadow:0 0 8px rgba(var(--glow-rgb),.08);}
.history-table tr:hover td{color:var(--text);background:rgba(var(--glow-rgb),.04);box-shadow:inset 0 0 20px rgba(var(--glow-rgb),.04);}
.chip:hover{box-shadow:0 0 10px rgba(var(--glow-rgb),.25);border-color:var(--border);}
.lib-card-del:hover{border-color:var(--alert-t);color:var(--alert-t);box-shadow:0 0 12px rgba(var(--glow-red-rgb),.3);}
.override-toggle:hover{color:var(--border);text-shadow:0 0 10px rgba(var(--glow-rgb),.5);}
::-webkit-scrollbar-thumb:hover{background:var(--border-dim);box-shadow:0 0 6px rgba(var(--glow-rgb),.2);}
.sidebar-logo{text-shadow:0 0 12px rgba(var(--glow-rgb),.3);}
.name-display{display:flex;align-items:center;gap:10px;cursor:default;}
.name-text{font-family:'Orbitron',sans-serif;font-size:.85rem;letter-spacing:2px;color:var(--border);}
.pencil-btn{background:none;border:none;cursor:pointer;padding:2px 4px;color:var(--border-dim);font-size:.9rem;line-height:1;transition:color .2s,text-shadow .2s;opacity:.5;}
.pencil-btn:hover{color:var(--border);opacity:1;text-shadow:0 0 8px rgba(var(--glow-rgb),.6);}
.name-edit{display:none;align-items:center;gap:8px;}
.name-edit.open{display:flex;}
.name-edit input{background:var(--bg);border:1px solid var(--border);color:var(--text);font-family:'Share Tech Mono',monospace;font-size:.88rem;padding:5px 10px;outline:none;flex:1;min-width:0;box-shadow:0 0 10px rgba(var(--glow-rgb),.15);}
.name-edit input:focus{box-shadow:0 0 14px rgba(var(--glow-rgb),.3);}
.name-confirm{background:none;border:none;cursor:pointer;padding:2px 6px;color:var(--text-dim);font-size:1rem;line-height:1;transition:color .2s,text-shadow .2s;}
.name-confirm:hover{color:#5dcc5d;text-shadow:0 0 8px rgba(60,200,60,.6);}
.name-cancel{background:none;border:none;cursor:pointer;padding:2px 6px;color:var(--text-dim);font-size:1rem;line-height:1;transition:color .2s,text-shadow .2s;}
.name-cancel:hover{color:var(--alert-t);text-shadow:0 0 8px rgba(255,80,80,.5);}
.page-title{text-shadow:0 0 16px rgba(var(--glow-rgb),.2);}
.section-title{text-shadow:0 0 10px rgba(var(--glow-rgb),.15);}
.reading-value{transition:text-shadow .2s;}
.reading-box:hover .reading-value{text-shadow:0 0 12px rgba(var(--glow-rgb),.3);}
.card-title{transition:text-shadow .2s;}
.station-card:hover .card-title{text-shadow:0 0 10px rgba(var(--glow-rgb),.4);}
.lib-card-name{transition:text-shadow .2s;}
.preset-lib-card:hover .lib-card-name{text-shadow:0 0 10px rgba(var(--glow-rgb),.4);}
</style>
</head>
<body>

<div id="sidebar">
  <button class="collapse-tab" id="collapse-btn" onclick="toggleSidebar()" title="Toggle sidebar">
    <span id="collapse-icon">◀</span>
  </button>
  <div class="sidebar-logo">Fish<br>Farm<br>Monitor</div>
  <button class="nav-btn active" onclick="showPage('farms',this)">
    <span class="nav-btn-icon">🐟</span>
    <span class="nav-btn-label">Farms</span>
  </button>
  <button class="nav-btn" onclick="showPage('history',this)">
    <span class="nav-btn-icon">📋</span>
    <span class="nav-btn-label">History</span>
  </button>
  <button class="nav-btn" onclick="showPage('preset',this)">
    <span class="nav-btn-icon">⚙</span>
    <span class="nav-btn-label">Presets</span>
  </button>
  <button class="nav-btn" onclick="showPage('logs',this)">
    <span class="nav-btn-icon">📓</span>
    <span class="nav-btn-label">Daily Logs</span>
  </button>
  <div style="flex:1;"></div>
  <button class="theme-toggle" id="theme-toggle" onclick="toggleTheme()" title="Toggle light/dark mode">
    <span id="theme-icon">☀</span>
    <span class="theme-label" style="font-size:.6rem;letter-spacing:1px;font-family:'Orbitron',sans-serif;margin-left:4px;">MODE</span>
  </button>
</div>

<div id="main">

  <!-- FARMS -->
  <div class="page active" id="farms-page">
    <div class="page-title">Live Station Overview</div>
    <div id="cards-container">
      <?php foreach ($stations as $sid): ?>
        <div class="station-card" id="card-<?php echo $sid;?>" onclick="openModal(<?php echo $sid;?>)">
          <div class="card-title" id="card-title-<?php echo $sid;?>"><?php echo htmlspecialchars($station_names[$sid] ?: "Station $sid"); ?></div>
          <div class="donut-wrap"><canvas id="donut-<?php echo $sid;?>" width="120" height="120"></canvas></div>
          <div class="card-readings" id="readings-<?php echo $sid;?>">pH &nbsp;: --<br>Temp : -- °C<br>Level: -- %</div>
          <div class="card-target" id="target-<?php echo $sid;?>">loading…</div>
          <div class="card-status pending" id="status-<?php echo $sid;?>">-- --</div>
        </div>
      <?php endforeach;?>
    </div>
  </div>

  <!-- HISTORY -->
  <div class="page" id="history-page">
    <div class="page-title">Data History</div>
    <div class="hist-controls">
      <div class="hist-control-group">
        <label class="hist-label">Station</label>
        <select id="history-station-select" onchange="loadHistory()">
          <?php foreach ($stations as $sid): ?>
            <option value="<?php echo $sid;?>"><?php echo htmlspecialchars($station_names[$sid] ?: "Station $sid"); ?></option>
          <?php endforeach;?>
        </select>
      </div>
      <div class="hist-control-group">
        <label class="hist-label">Rows to show</label>
        <input type="number" id="hist-limit" value="50" min="1" max="9999" onchange="loadHistory()" style="width:80px;">
      </div>
      <button class="btn-dim" onclick="loadHistory()">Refresh</button>
    </div>
    <div class="hist-grid">
      <div class="hist-panel">
        <div class="section-title">Sensor Readings <span class="hist-count" id="sensor-count"></span></div>
        <div class="hist-scroll">
          <table class="history-table">
            <thead><tr><th>#</th><th>pH</th><th>Temp °C</th><th>Level %</th><th>Status</th><th>Recorded At</th></tr></thead>
            <tbody id="history-body"><tr><td colspan="6" style="color:var(--text-dim);padding:16px">Select a station</td></tr></tbody>
          </table>
        </div>
      </div>
      <div class="hist-panel">
        <div class="section-title">Current Setpoints <span class="hist-count" id="setpoint-count"></span></div>
        <div class="hist-scroll">
          <table class="history-table">
            <thead><tr><th>Source</th><th>pH Range</th><th>Temp Range</th><th>Level Range</th><th>Updated At</th></tr></thead>
            <tbody id="setpoint-body"><tr><td colspan="5" style="color:var(--text-dim);padding:16px">Select a station</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- PRESET LIBRARY -->
  <div class="page" id="preset-page">
    <div class="page-title">Fish Preset Library</div>
    <div id="new-preset-form">
      <div class="section-title">New Preset</div>
      <div class="form-row"><label>Fish Name</label><input type="text" id="np-name" placeholder="e.g. Salmon"></div>
      <div class="form-row"><label>Image URL (optional)</label><input type="text" id="np-img" placeholder="https://..."></div>
      <div class="form-grid">
        <div class="form-field"><label>pH Min</label><input type="number" step="0.01" id="np-ph-min"></div>
        <div class="form-field"><label>pH Max</label><input type="number" step="0.01" id="np-ph-max"></div>
        <div></div>
        <div class="form-field"><label>Temp Min °C</label><input type="number" step="0.1" id="np-temp-min"></div>
        <div class="form-field"><label>Temp Max °C</label><input type="number" step="0.1" id="np-temp-max"></div>
        <div></div>
        <div class="form-field"><label>Level Min %</label><input type="number" step="0.1" id="np-lvl-min"></div>
        <div class="form-field"><label>Level Max %</label><input type="number" step="0.1" id="np-lvl-max"></div>
      </div>
      <div class="btn-row">
        <button class="btn-gold" onclick="saveNewPreset()">Save</button>
        <button class="btn-dim" onclick="toggleNewForm(false)">Cancel</button>
      </div>
      <div class="modal-msg" id="np-msg"></div>
    </div>
    <div class="preset-lib-grid" id="preset-lib-grid"></div>
  </div>

  <!-- DAILY LOGS -->
  <div class="page" id="logs-page">
    <div class="page-title">Daily Logs</div>

    <!-- Controls -->
    <div class="hist-controls">
      <div class="hist-control-group">
        <label class="hist-label">Station</label>
        <select id="log-station-select" style="background:var(--panel);border:1px solid var(--border-dim);color:var(--text);font-family:'Share Tech Mono',monospace;padding:8px 12px;font-size:.85rem;outline:none;">
          <?php foreach ($stations as $sid): ?>
            <option value="<?php echo $sid;?>"><?php echo htmlspecialchars($station_names[$sid] ?: "Station $sid"); ?></option>
          <?php endforeach;?>
        </select>
      </div>
      <div class="hist-control-group">
        <label class="hist-label">From</label>
        <input type="date" id="log-from" style="background:var(--panel);border:1px solid var(--border-dim);color:var(--text);font-family:'Share Tech Mono',monospace;padding:8px 10px;font-size:.85rem;outline:none;">
      </div>
      <div class="hist-control-group">
        <label class="hist-label">To</label>
        <input type="date" id="log-to" style="background:var(--panel);border:1px solid var(--border-dim);color:var(--text);font-family:'Share Tech Mono',monospace;padding:8px 10px;font-size:.85rem;outline:none;">
      </div>
      <div class="hist-control-group">
        <label class="hist-label">Quick</label>
        <div style="display:flex;gap:6px;">
          <button class="btn-dim" onclick="setLogRange('week')">Week</button>
          <button class="btn-dim" onclick="setLogRange('month')">Month</button>
        </div>
      </div>
      <button class="btn-gold" onclick="loadLogs()">Load</button>
      <button class="btn-dim" onclick="printLogs()">🖨 Print</button>
    </div>

    <!-- New log form -->
    <div id="new-log-form" style="background:var(--panel);border:1px solid var(--border);padding:22px;">
      <div class="section-title" style="margin-bottom:14px;">New Log Entry</div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:12px;">
        <div>
          <label class="log-label">Date</label>
          <input type="date" id="nl-date" class="log-input">
        </div>
        <div>
          <label class="log-label">Work Done</label>
          <input type="text" id="nl-work" placeholder="e.g. Feeding, Fertilizer" class="log-input">
        </div>
        <div>
          <label class="log-label">Amount</label>
          <input type="text" id="nl-amount" placeholder="e.g. 2kg, 5L" class="log-input">
        </div>
        <div>
          <label class="log-label">Cost (৳)</label>
          <input type="number" step="0.01" id="nl-cost" placeholder="0.00" class="log-input">
        </div>
        <div>
          <label class="log-label">Earning (৳)</label>
          <input type="number" step="0.01" id="nl-earning" placeholder="0.00" class="log-input">
        </div>
      </div>
      <div style="margin-bottom:12px;">
        <label class="log-label">Current Situation</label>
        <textarea id="nl-situation" placeholder="Notes about current condition..." class="log-input" style="resize:vertical;min-height:60px;"></textarea>
      </div>
      <div class="btn-row">
        <button class="btn-gold" onclick="saveLog()">Save Entry</button>
      </div>
      <div id="log-msg" style="font-size:.75rem;color:var(--ok-t);min-height:1em;margin-top:8px;"></div>
    </div>

    <!-- Summary -->
    <div id="log-summary" style="display:none;padding:10px 14px;border:1px solid var(--border-dim);font-size:.75rem;color:var(--text-dim);line-height:2;"></div>

    <!-- Log table -->
    <div style="overflow-x:auto;">
      <table class="history-table" id="log-table">
        <thead>
          <tr>
            <th>#</th><th>Date</th><th>Work Done</th><th>Amount</th>
            <th>Situation</th><th>Cost (৳)</th><th>Earning (৳)</th><th></th>
          </tr>
        </thead>
        <tbody id="log-body">
          <tr><td colspan="8" style="color:var(--text-dim);padding:16px">Load logs to view</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- MODAL -->
<div id="modal-overlay" onclick="closeModalOutside(event)">
<div id="modal">
  <div class="modal-header">
    <div style="flex:1;min-width:0;">
      <div class="name-display" id="name-display">
        <h2 id="modal-title">Station --</h2>
        <button class="pencil-btn" title="Rename" onclick="openNameEdit()">✏</button>
      </div>
      <div class="name-edit" id="name-edit">
        <input type="text" id="m-station-name" placeholder="Station name…"
          onkeydown="if(event.key==='Enter')saveStationName();if(event.key==='Escape')closeNameEdit();">
        <button class="name-confirm" title="Save" onclick="saveStationName()">✓</button>
        <button class="name-cancel" title="Cancel" onclick="closeNameEdit()">✕</button>
      </div>
    </div>
    <div class="modal-header-btns">
      <button class="btn-save" onclick="saveModalPresets()">Save</button>
      <button class="btn-red" onclick="closeModal()">Close</button>
    </div>
  </div>
  <div class="modal-readings">
    <div class="reading-box">
      <div class="reading-label">pH</div>
      <div class="reading-value" id="m-ph">--</div>
      <div class="reading-range" id="m-range-ph">Range: --</div>
    </div>
    <div class="reading-box">
      <div class="reading-label">Temperature</div>
      <div class="reading-value" id="m-temp">--</div>
      <div class="reading-range" id="m-range-temp">Range: --</div>
    </div>
    <div class="reading-box">
      <div class="reading-label">Water Level</div>
      <div class="reading-value" id="m-level">--</div>
      <div class="reading-range" id="m-range-level">Range: --</div>
    </div>
  </div>
  <div>
    <div class="section-title">Active Presets</div>
    <div class="preset-chips" id="m-chips"></div>
    <button class="btn-dim" onclick="togglePicker()">+ Add Preset</button>
    <div id="preset-picker">
      <div class="picker-grid" id="picker-grid"></div>
      <div class="btn-row" style="margin-top:10px;">
        <button class="btn-gold" onclick="saveAssignments()">Apply</button>
        <button class="btn-dim" onclick="togglePicker()">Cancel</button>
      </div>
    </div>
  </div>
  <div id="avg-display" style="border:1px solid var(--border-dim);padding:12px 14px;font-size:.75rem;color:var(--text-dim);line-height:2;display:none;">
    <span style="font-family:'Orbitron',sans-serif;font-size:.6rem;letter-spacing:2px;color:var(--border);">AVERAGED TARGET</span><br>
    <span id="avg-display-text">--</span>
  </div>
  <div>
    <button class="override-toggle" id="override-toggle" onclick="toggleOverride()">
      <i class="arrow">▶</i> Manual Override
      <span style="font-size:.55rem;color:var(--text-dim);letter-spacing:1px;margin-left:4px;">(overrides presets)</span>
    </button>
    <div id="manual-body">
      <div class="manual-hint" id="m-avg-hint">
        Preset average: <b id="m-avg-text">no presets assigned</b>
      </div>
      <div class="manual-grid">
        <div class="manual-field"><label>Override pH</label><input type="number" step="0.01" id="m-a-ph" placeholder="--"></div>
        <div class="manual-field"><label>Override Temp °C</label><input type="number" step="0.1" id="m-a-temp" placeholder="--"></div>
        <div class="manual-field"><label>Override Level %</label><input type="number" step="0.1" id="m-a-level" placeholder="--"></div>
      </div>
      <div class="btn-row">
        <button class="btn-gold" onclick="saveManual()">Save Override</button>
        <button class="btn-dim" onclick="clearManual()">Clear Override</button>
      </div>
    </div>
    <div class="modal-msg" id="m-msg"></div>
  </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.botpress.cloud/webchat/v3.2/inject.js"></script>
<script>
const savedTheme = localStorage.getItem('theme');
window.botpress.init({
  botId: "bp_bak_PR8LThSMrf4JjaBXiolsW7s91FvsBCnhPLqF",
  clientId: "a5413842-3247-49ee-a85d-cc6c0ee04000",
  configuration: {
    botName: "Fish Doctor",
    themeMode: savedTheme === 'light' ? 'light' : 'dark',
    color: "#c8a84b"
  }
});

const stations     = <?php echo json_encode($stations); ?>;
const stationNames = <?php echo json_encode($station_names); ?>;
const charts    = {};
let activeModal = null;

// ── Nav ──────────────────────────────────────────────────
function showPage(name, btn) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
  document.getElementById(name + '-page').classList.add('active');
  btn.classList.add('active');
  if (name === 'history') loadHistory();
  if (name === 'preset')  renderPresetLib();
  if (name === 'logs') {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('nl-date').value = today;
    setLogRange('month');
  }
}

// ── Donuts ───────────────────────────────────────────────
function initDonut(sid) {
  const ctx = document.getElementById('donut-' + sid).getContext('2d');
  charts[sid] = new Chart(ctx, {
    type: 'doughnut',
    data: { datasets: [
      { data:[0,100], backgroundColor:['#4a90d9',getComputedStyle(document.body).getPropertyValue('--donut-empty').trim()||'#1a1a1a'], borderWidth:0 },
      { data:[0,100], backgroundColor:['#e8833a',getComputedStyle(document.body).getPropertyValue('--donut-empty').trim()||'#1a1a1a'], borderWidth:0 },
      { data:[0,100], backgroundColor:['#aaaaaa',getComputedStyle(document.body).getPropertyValue('--donut-empty').trim()||'#1a1a1a'], borderWidth:0 }
    ]},
    options:{ cutout:'30%', responsive:false, plugins:{ legend:{display:false}, tooltip:{enabled:false} } }
  });
}
function clamp(v,lo=0,hi=100){return Math.min(Math.max(v,lo),hi);}
function updateDonut(sid, ph, temp, level) {
  const c = charts[sid]; if (!c) return;
  c.data.datasets[0].data = [clamp(((ph-5.5)/3.5)*100),  100-clamp(((ph-5.5)/3.5)*100)];
  c.data.datasets[1].data = [clamp(((temp-15)/25)*100),   100-clamp(((temp-15)/25)*100)];
  c.data.datasets[2].data = [clamp(level),                100-clamp(level)];
  c.update('none');
}

// ── Poll ─────────────────────────────────────────────────
function pollAll() {
  stations.forEach(sid => {
    fetch('getdata.php?station_id=' + sid)
      .then(r => r.json())
      .then(data => {
        updateCard(sid, data);
        if (activeModal === sid) updateModal(data);
      }).catch(()=>{});
  });
}

function updateCard(sid, data) {
  const l = data.latest, ef = data.effective;
  if (!l) return;
  document.getElementById('readings-' + sid).innerHTML =
    `pH &nbsp;: ${l.ph}<br>Temp : ${l.temp} °C<br>Level: ${l.level} %`;
  const tEl = document.getElementById('target-' + sid);
  if (ef) {
    const src = ef.source === 'manual' ? 'manual' : (ef.presets ? ef.presets.map(p=>p.name).join('+') : '--');
    tEl.innerHTML = `<span style="color:var(--border)">${src}</span> &nbsp;pH ${ef.ph_min}–${ef.ph_max} | T ${ef.temp_min}–${ef.temp_max} | Lv ${ef.level_min}–${ef.level_max}`;
  } else {
    tEl.textContent = 'No preset set';
  }
  const sEl  = document.getElementById('status-' + sid);
  const card = document.getElementById('card-' + sid);
  card.classList.remove('glow-ok','glow-alert');
  if (l.status === 'ok') {
    sEl.className = 'card-status ok'; sEl.textContent = '✓ All OK'; card.classList.add('glow-ok');
  } else if (l.status && l.status.startsWith('alert:')) {
    sEl.className = 'card-status alert'; sEl.textContent = '⚠ ' + l.status.replace('alert:','').toUpperCase(); card.classList.add('glow-alert');
  } else if (l.status === 'no_preset') {
    sEl.className = 'card-status pending'; sEl.textContent = '○ No preset set';
  } else {
    sEl.className = 'card-status pending'; sEl.textContent = '-- --';
  }
  updateDonut(sid, parseFloat(l.ph), parseFloat(l.temp), parseFloat(l.level));
}

// ── Modal ────────────────────────────────────────────────
function openModal(sid) {
  activeModal = sid;
  document.getElementById('modal-title').textContent = stationNames[sid] || 'Station ' + sid;
  document.getElementById('modal-overlay').classList.add('open');
  document.getElementById('preset-picker').classList.remove('open');
  document.getElementById('m-msg').textContent = '';
  document.getElementById('manual-body').classList.remove('open');
  document.getElementById('override-toggle').classList.remove('open');
  ['m-a-ph','m-a-temp','m-a-level'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('m-station-name').value = stationNames[sid] || '';
  closeNameEdit();
  fetch('getdata.php?station_id=' + sid).then(r=>r.json()).then(data => updateModal(data));
}

function updateModal(data) {
  const l  = data.latest;
  const ef = data.effective;
  const assigned = data.assigned_ids || [];
  const allP     = data.all_presets  || [];
  if (l) {
    document.getElementById('m-ph').textContent    = l.ph;
    document.getElementById('m-temp').textContent  = l.temp + ' °C';
    document.getElementById('m-level').textContent = l.level + ' %';
  }
  if (ef) {
    document.getElementById('m-range-ph').textContent    = `${ef.ph_min} – ${ef.ph_max}`;
    document.getElementById('m-range-temp').textContent  = `${ef.temp_min} – ${ef.temp_max} °C`;
    document.getElementById('m-range-level').textContent = `${ef.level_min} – ${ef.level_max} %`;
  } else {
    ['m-range-ph','m-range-temp','m-range-level'].forEach(id => document.getElementById(id).textContent = '--');
  }
  const avgBox  = document.getElementById('avg-display');
  const avgText = document.getElementById('avg-display-text');
  if (ef && ef.source === 'presets') {
    const midPh    = ((+ef.ph_min    + +ef.ph_max)    / 2).toFixed(2);
    const midTemp  = ((+ef.temp_min  + +ef.temp_max)  / 2).toFixed(1);
    const midLevel = ((+ef.level_min + +ef.level_max) / 2).toFixed(1);
    avgText.innerHTML = `pH <b style="color:var(--text)">${ef.ph_min}–${ef.ph_max}</b> (mid: ${midPh}) &nbsp;|&nbsp; Temp <b style="color:var(--text)">${ef.temp_min}–${ef.temp_max} °C</b> (mid: ${midTemp}) &nbsp;|&nbsp; Level <b style="color:var(--text)">${ef.level_min}–${ef.level_max} %</b> (mid: ${midLevel})`;
    avgBox.style.display = 'block';
    document.getElementById('m-avg-text').textContent = `pH ${midPh} | Temp ${midTemp} °C | Level ${midLevel} %`;
  } else if (ef && ef.source === 'manual') {
    avgText.innerHTML = `<span style="color:var(--border)">Manual override active</span> — pH ${ef.a_ph} | Temp ${ef.a_temp} °C | Level ${ef.a_level} %`;
    avgBox.style.display = 'block';
    document.getElementById('m-avg-text').textContent = 'manual override active';
    document.getElementById('m-a-ph').value    = ef.a_ph;
    document.getElementById('m-a-temp').value  = ef.a_temp;
    document.getElementById('m-a-level').value = ef.a_level;
  } else {
    avgBox.style.display = 'none';
    document.getElementById('m-avg-text').textContent = 'no presets assigned';
  }
  const chipsEl = document.getElementById('m-chips');
  chipsEl.innerHTML = assigned.length === 0 ? '' :
    assigned.map(pid => {
      const p = allP.find(x => parseInt(x.id) === parseInt(pid));
      return p ? `<span class="chip">${p.name}<button class="chip-x" onclick="unassignPreset(${p.id})">×</button></span>` : '';
    }).join('');
  const pickerGrid = document.getElementById('picker-grid');
  pickerGrid.innerHTML = allP.length === 0
    ? '<span style="color:var(--text-dim);font-size:.8rem">No presets in library</span>'
    : allP.map(p => `
      <div class="picker-item">
        <input type="checkbox" id="pk-${p.id}" value="${p.id}" ${assigned.includes(parseInt(p.id)) ? 'checked' : ''}>
        <label for="pk-${p.id}">${p.name}<br>
          <span style="font-size:.65rem;color:var(--text-dim)">pH ${p.ph_min}–${p.ph_max} | T ${p.temp_min}–${p.temp_max}</span>
        </label>
      </div>`).join('');
}

function saveModalPresets() {
  const pickerOpen = document.getElementById('preset-picker').classList.contains('open');
  if (pickerOpen) saveAssignments(); else closeModal();
}
function togglePicker() { document.getElementById('preset-picker').classList.toggle('open'); }
function saveAssignments() {
  const checked = [...document.querySelectorAll('#picker-grid input:checked')].map(i=>i.value);
  const sid = activeModal;
  fetch('setpreset.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=clear_manual&station_id=${sid}` })
    .then(()=> {
      fetch('setpreset.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=assign&station_id=${sid}&preset_ids=${checked.join(',')}` })
        .then(r=>r.json()).then(d => {
          if (d.status === 'ok') {
            document.getElementById('preset-picker').classList.remove('open');
            fetch('getdata.php?station_id='+sid).then(r=>r.json()).then(data=>{ updateModal(data); updateCard(sid, data); });
            closeModal();
          }
        });
    });
}
function unassignPreset(presetId) {
  fetch('setpreset.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=unassign&station_id=${activeModal}&preset_id=${presetId}` })
    .then(r=>r.json()).then(d => {
      if (d.status === 'ok') fetch('getdata.php?station_id='+activeModal).then(r=>r.json()).then(data=>{ updateModal(data); updateCard(activeModal, data); });
    });
}
function toggleOverride() {
  document.getElementById('manual-body').classList.toggle('open');
  document.getElementById('override-toggle').classList.toggle('open');
}
function openNameEdit() {
  document.getElementById('name-display').style.display = 'none';
  document.getElementById('name-edit').classList.add('open');
  document.getElementById('m-station-name').focus();
  document.getElementById('m-station-name').select();
}
function closeNameEdit() {
  document.getElementById('name-display').style.display = '';
  document.getElementById('name-edit').classList.remove('open');
}
function saveStationName() {
  const name = document.getElementById('m-station-name').value.trim();
  const sid  = activeModal;
  fetch('setpreset.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=rename&station_id=${sid}&name=${encodeURIComponent(name)}` })
    .then(r=>r.json()).then(d => {
      if (d.status === 'ok') {
        stationNames[sid] = name;
        const display = name || 'Station ' + sid;
        document.getElementById('card-title-' + sid).textContent = display;
        document.getElementById('modal-title').textContent = display;
        closeNameEdit();
        const msg = document.getElementById('m-msg');
        msg.textContent = '✓ Renamed';
        setTimeout(()=>msg.textContent='', 2000);
      }
    });
}
function closeModal() {
  activeModal = null;
  document.getElementById('modal-overlay').classList.remove('open');
}
function closeModalOutside(e) {
  if (e.target === document.getElementById('modal-overlay')) closeModal();
}
function saveManual() {
  const ph = document.getElementById('m-a-ph').value;
  const temp = document.getElementById('m-a-temp').value;
  const level = document.getElementById('m-a-level').value;
  if (!ph || !temp || !level) {
    document.getElementById('m-msg').textContent = '✗ Fill all three fields';
    setTimeout(()=>document.getElementById('m-msg').textContent='',3000); return;
  }
  fetch('setpreset.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=manual&station_id=${activeModal}&a_ph=${ph}&a_temp=${temp}&a_level=${level}` })
    .then(r=>r.json()).then(d => {
      const msg = document.getElementById('m-msg');
      msg.textContent = d.status==='ok' ? '✓ Override saved' : '✗ '+d.message;
      setTimeout(()=>msg.textContent='',3000);
      if (d.status==='ok') fetch('getdata.php?station_id='+activeModal).then(r=>r.json()).then(data=>{ updateModal(data); updateCard(activeModal, data); });
    });
}
function clearManual() {
  fetch('setpreset.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=clear_manual&station_id=${activeModal}` })
    .then(r=>r.json()).then(d => {
      const msg = document.getElementById('m-msg');
      msg.textContent = d.status==='ok' ? '✓ Override cleared' : '✗ Error';
      setTimeout(()=>msg.textContent='',3000);
      ['m-a-ph','m-a-temp','m-a-level'].forEach(id=>document.getElementById(id).value='');
      if (d.status==='ok') fetch('getdata.php?station_id='+activeModal).then(r=>r.json()).then(data=>{ updateModal(data); updateCard(activeModal, data); });
    });
}

// ── History ───────────────────────────────────────────────
function loadHistory() {
  const sid   = document.getElementById('history-station-select').value;
  const limit = parseInt(document.getElementById('hist-limit').value) || 50;
  fetch(`getdata.php?station_id=${sid}&limit=${limit}`)
    .then(r => r.json())
    .then(data => {
      const tbody   = document.getElementById('history-body');
      const readings = data.history || [];
      document.getElementById('sensor-count').textContent = `(${readings.length} rows)`;
      tbody.innerHTML = readings.length === 0
        ? '<tr><td colspan="6" style="color:var(--text-dim);padding:16px">No data</td></tr>'
        : readings.map((row, i) => {
            const isAlert = row.status && row.status.startsWith('alert:');
            const isOk    = row.status === 'ok';
            return `<tr><td>${i+1}</td><td>${row.ph}</td><td>${row.temp}</td><td>${row.level}</td><td class="${isAlert?'status-alert':isOk?'status-ok':''}">${row.status??'--'}</td><td>${row.recorded_at}</td></tr>`;
          }).join('');
      const spbody = document.getElementById('setpoint-body');
      const sp     = data.effective;
      document.getElementById('setpoint-count').textContent = '';
      if (!sp) {
        spbody.innerHTML = '<tr><td colspan="5" style="color:var(--text-dim);padding:16px">No setpoints configured</td></tr>';
      } else {
        const srcClass = sp.source === 'preset' ? 'source-preset' : sp.source === 'manual' ? 'source-manual' : 'source-none';
        spbody.innerHTML = `<tr><td class="${srcClass}">${sp.source??'--'}</td><td>${sp.ph_min??'--'} – ${sp.ph_max??'--'}</td><td>${sp.temp_min??'--'} – ${sp.temp_max??'--'} °C</td><td>${sp.level_min??'--'} – ${sp.level_max??'--'} %</td><td>${sp.updated_at??'--'}</td></tr>`;
      }
    })
    .catch(() => {
      document.getElementById('history-body').innerHTML = '<tr><td colspan="6" style="color:var(--alert-t);padding:16px">Failed to load</td></tr>';
    });
}

// ── Preset library ────────────────────────────────────────
let localPresets = <?php echo json_encode($all_presets); ?>;
function renderPresetLib() {
  const grid = document.getElementById('preset-lib-grid');
  grid.innerHTML = localPresets.map(p => {
    const img = p.image_url
      ? `<img class="lib-card-img" src="${p.image_url}" alt="${p.name}" onerror="this.style.display='none'">`
      : `<div class="lib-card-img-placeholder">NO IMAGE</div>`;
    return `<div class="preset-lib-card">${img}<div class="lib-card-body"><div class="lib-card-name">${p.name}</div><div class="lib-card-range">pH: ${p.ph_min}–${p.ph_max}<br>Temp: ${p.temp_min}–${p.temp_max} °C<br>Level: ${p.level_min}–${p.level_max} %</div><button class="lib-card-del" onclick="deletePreset(${p.id})">Delete</button></div></div>`;
  }).join('') + `<div class="preset-lib-card new-card" onclick="toggleNewForm(true)">+ New Preset</div>`;
}
function toggleNewForm(open) {
  document.getElementById('new-preset-form').classList.toggle('open', open);
  if (open) document.getElementById('np-name').focus();
}
function saveNewPreset() {
  const name = document.getElementById('np-name').value.trim();
  if (!name) { document.getElementById('np-msg').textContent='✗ Name required'; return; }
  const body = ['action=create',`name=${encodeURIComponent(name)}`,`ph_min=${document.getElementById('np-ph-min').value}`,`ph_max=${document.getElementById('np-ph-max').value}`,`temp_min=${document.getElementById('np-temp-min').value}`,`temp_max=${document.getElementById('np-temp-max').value}`,`level_min=${document.getElementById('np-lvl-min').value}`,`level_max=${document.getElementById('np-lvl-max').value}`,`image_url=${encodeURIComponent(document.getElementById('np-img').value.trim())}`].join('&');
  fetch('setpreset.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body})
    .then(r=>r.json()).then(d=>{
      if (d.status==='ok') {
        localPresets.push({id:d.id,name:d.name,image_url:d.image_url??null,ph_min:document.getElementById('np-ph-min').value,ph_max:document.getElementById('np-ph-max').value,temp_min:document.getElementById('np-temp-min').value,temp_max:document.getElementById('np-temp-max').value,level_min:document.getElementById('np-lvl-min').value,level_max:document.getElementById('np-lvl-max').value});
        toggleNewForm(false); renderPresetLib();
        ['np-name','np-img','np-ph-min','np-ph-max','np-temp-min','np-temp-max','np-lvl-min','np-lvl-max'].forEach(id=>document.getElementById(id).value='');
      } else {
        document.getElementById('np-msg').textContent='✗ '+d.message;
        setTimeout(()=>document.getElementById('np-msg').textContent='',3000);
      }
    });
}
function deletePreset(id) {
  if (!confirm('Delete this preset? It will be removed from all stations.')) return;
  fetch('setpreset.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=delete&id=${id}`})
    .then(r=>r.json()).then(d=>{
      if (d.status==='ok') { localPresets=localPresets.filter(p=>p.id!=id); renderPresetLib(); }
      else alert('Delete failed: '+d.message);
    });
}

// ── Daily Logs ────────────────────────────────────────────
function setLogRange(type) {
  const now = new Date();
  const to  = now.toISOString().split('T')[0];
  let from;
  if (type === 'week') {
    const d = new Date(now); d.setDate(d.getDate() - 6);
    from = d.toISOString().split('T')[0];
  } else {
    from = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
  }
  document.getElementById('log-from').value = from;
  document.getElementById('log-to').value   = to;
  loadLogs();
}

function loadLogs() {
  const sid  = document.getElementById('log-station-select').value;
  const from = document.getElementById('log-from').value;
  const to   = document.getElementById('log-to').value;
  if (!from || !to) { alert('Select a date range first'); return; }
  fetch('savelog.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=fetch&station_id=${sid}&from=${from}&to=${to}` })
    .then(r=>r.json()).then(d=>{
      const tbody = document.getElementById('log-body');
      const logs  = d.logs || [];
      if (logs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="color:var(--text-dim);padding:16px">No logs found</td></tr>';
        document.getElementById('log-summary').style.display = 'none';
        return;
      }
      tbody.innerHTML = logs.map((l,i) => `<tr>
        <td>${i+1}</td>
        <td>${l.log_date}</td>
        <td>${l.work_done}</td>
        <td>${l.amount||'--'}</td>
        <td style="max-width:200px;white-space:pre-wrap;">${l.situation||'--'}</td>
        <td style="color:var(--alert-t)">${parseFloat(l.cost||0).toFixed(2)}</td>
        <td style="color:var(--ok-t)">${parseFloat(l.earning||0).toFixed(2)}</td>
        <td><button class="btn-red" style="padding:4px 8px;font-size:.55rem;" onclick="deleteLog(${l.id})">✕</button></td>
      </tr>`).join('');
      const totalCost    = logs.reduce((s,l)=>s+parseFloat(l.cost||0),0);
      const totalEarning = logs.reduce((s,l)=>s+parseFloat(l.earning||0),0);
      const net          = totalEarning - totalCost;
      const summary      = document.getElementById('log-summary');
      summary.style.display = 'block';
      summary.innerHTML = `<span style="font-family:'Orbitron',sans-serif;font-size:.6rem;letter-spacing:2px;color:var(--border);">SUMMARY</span> &nbsp;
        Entries: <b style="color:var(--text)">${logs.length}</b> &nbsp;|&nbsp;
        Total Cost: <b style="color:var(--alert-t)">৳${totalCost.toFixed(2)}</b> &nbsp;|&nbsp;
        Total Earning: <b style="color:var(--ok-t)">৳${totalEarning.toFixed(2)}</b> &nbsp;|&nbsp;
        Net: <b style="color:${net>=0?'var(--ok-t)':'var(--alert-t)'}">৳${net.toFixed(2)}</b>`;
    });
}

function saveLog() {
  const sid  = document.getElementById('log-station-select').value;
  const date = document.getElementById('nl-date').value;
  const work = document.getElementById('nl-work').value.trim();
  if (!date || !work) {
    document.getElementById('log-msg').textContent = '✗ Date and Work Done are required';
    setTimeout(()=>document.getElementById('log-msg').textContent='',3000); return;
  }
  fetch('savelog.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`action=save&station_id=${sid}&log_date=${date}&work_done=${encodeURIComponent(work)}&amount=${encodeURIComponent(document.getElementById('nl-amount').value)}&situation=${encodeURIComponent(document.getElementById('nl-situation').value)}&cost=${document.getElementById('nl-cost').value||0}&earning=${document.getElementById('nl-earning').value||0}`
  }).then(r=>r.json()).then(d=>{
    const msg = document.getElementById('log-msg');
    if (d.status==='ok') {
      msg.textContent = '✓ Entry saved';
      ['nl-work','nl-amount','nl-situation','nl-cost','nl-earning'].forEach(id=>document.getElementById(id).value='');
      loadLogs();
    } else {
      msg.textContent = '✗ ' + d.message;
    }
    setTimeout(()=>msg.textContent='',3000);
  });
}

function deleteLog(id) {
  if (!confirm('Delete this log entry?')) return;
  fetch('savelog.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=delete&id=${id}`})
    .then(r=>r.json()).then(d=>{ if(d.status==='ok') loadLogs(); });
}

function printLogs() {
  const sid     = document.getElementById('log-station-select').value;
  const from    = document.getElementById('log-from').value;
  const to      = document.getElementById('log-to').value;
  const name    = stationNames[sid] || 'Station ' + sid;
  const summary = document.getElementById('log-summary').innerText;
  const rows    = [...document.querySelectorAll('#log-body tr')].map(r => {
    const cells = [...r.querySelectorAll('td')];
    if (cells.length < 7) return '';
    return `<tr>${cells.slice(0,7).map(c=>`<td>${c.innerText}</td>`).join('')}</tr>`;
  }).join('');

  const win = window.open('', '_blank');
  win.document.write(`<!DOCTYPE html><html><head><title>Log Report — ${name}</title>
  <style>
    body{font-family:monospace;padding:30px;color:#111;}
    h1{font-size:1.1rem;margin-bottom:4px;}
    .sub{font-size:.8rem;color:#555;margin-bottom:20px;}
    table{width:100%;border-collapse:collapse;font-size:.82rem;}
    th{border-bottom:2px solid #111;padding:6px 10px;text-align:left;font-size:.7rem;letter-spacing:1px;text-transform:uppercase;}
    td{padding:6px 10px;border-bottom:1px solid #ddd;}
    .summary{margin:16px 0;padding:10px 14px;border:1px solid #ccc;font-size:.8rem;line-height:2;}
    @media print{.no-print{display:none}}
  </style></head><body>
  <h1>Daily Log Report — ${name}</h1>
  <div class="sub">Period: ${from} to ${to}</div>
  <div class="summary">${summary}</div>
  <table>
    <thead><tr><th>#</th><th>Date</th><th>Work Done</th><th>Amount</th><th>Situation</th><th>Cost (৳)</th><th>Earning (৳)</th></tr></thead>
    <tbody>${rows}</tbody>
  </table>
  <br>
  <button class="no-print" onclick="window.print()" style="font-family:monospace;padding:8px 16px;cursor:pointer;">🖨 Print</button>
  </body></html>`);
  win.document.close();
}

// ── Sidebar collapse ─────────────────────────────────────
function toggleSidebar() {
  const sb   = document.getElementById('sidebar');
  const icon = document.getElementById('collapse-icon');
  const collapsed = sb.classList.toggle('collapsed');
  icon.textContent = collapsed ? '▶' : '◀';
  localStorage.setItem('sidebar', collapsed ? 'collapsed' : 'open');
}
if (localStorage.getItem('sidebar') === 'collapsed') {
  document.getElementById('sidebar').classList.add('collapsed');
  document.getElementById('collapse-icon').textContent = '▶';
}

// ── Theme ────────────────────────────────────────────────
function toggleTheme() {
  const isLight = document.body.classList.toggle('light');
  document.getElementById('theme-icon').textContent = isLight ? '🌙' : '☀';
  localStorage.setItem('theme', isLight ? 'light' : 'dark');
  stations.forEach(sid => {
    if (charts[sid]) { charts[sid].destroy(); delete charts[sid]; }
    initDonut(sid);
  });
  pollAll();
}
if (localStorage.getItem('theme') === 'light') {
  document.body.classList.add('light');
  document.getElementById('theme-icon').textContent = '🌙';
}

// ── Boot ─────────────────────────────────────────────────
stations.forEach(sid=>initDonut(sid));
renderPresetLib();
pollAll();
setInterval(pollAll, 5000);
</script>
</body>
</html>
