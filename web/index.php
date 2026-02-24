<?php
/************************************************************
 * index.php (Dashboard)
 * - LED: solo label + swatch (nessun cursore)
 * - Polling:
 *    /state?internal=1  (NON loggato in DB)
 *    /log?internal=1    (NON loggato in DB, e log già filtrato/mascherato)
 * - Refresh ogni 2 secondi
 * - Log: aggiornamento DOM solo se cambia (anti-flicker)
 ************************************************************/
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SensoriRemoti - Simulatore Altervista</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 18px; }
    h1 { margin-top: 0; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .card { border: 1px solid #ddd; border-radius: 10px; padding: 14px; }
    .mono { font-family: monospace; font-size: 13px; white-space: pre-wrap; }
    .led-row { display: flex; align-items: center; gap: 12px; }
    .swatch { width: 42px; height: 28px; border: 1px solid #999; border-radius: 8px; background: rgb(0,0,0); }
    .muted { color: #666; }
  </style>
</head>
<body>

<h1>SensoriRemoti (Simulatore Altervista)</h1>

<div class="grid">
  <div class="card">
    <h2>LED RGB</h2>

    <div class="led-row">
      <div id="ledSwatch" class="swatch"></div>
      <div>
        <div><strong>Colore attuale</strong></div>
        <div id="ledLabel" class="mono">rgb(0, 0, 0)</div>
        <div class="muted" style="margin-top:6px;">
          Valore letto da <code>GET /state</code> (persistente su DB)
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <h2>Stato device (GET /state)</h2>
    <div id="stateBox" class="mono">Caricamento...</div>
    <div class="muted" style="margin-top:10px;">
      Aggiornamento automatico ogni 2 secondi (richieste interne non loggate).
    </div>
  </div>

  <div class="card" style="grid-column: 1 / span 2;">
    <h2>Log richieste (GET /log)</h2>
    <div class="muted" style="margin-bottom:10px;">
      <br>
    </div>
    <div id="logBox">In attesa del primo aggiornamento…</div>
  </div>
</div>

<script>
/* ==========================================================
 * Helper DOM
 * ========================================================== */
function byId(id) {
  return document.getElementById(id);
}

function setText(id, text) {
  byId(id).textContent = text;
}

/* ==========================================================
 * LED label updater (da /state)
 * ========================================================== */
function updateLedLabelFromState(stateData) {
  if (!stateData) {
    return;
  }

  if (!stateData.actuators) {
    return;
  }

  if (!stateData.actuators.led) {
    return;
  }

  var led = stateData.actuators.led;

  if (typeof led.r !== "number" || typeof led.g !== "number" || typeof led.b !== "number") {
    return;
  }

  var r = led.r;
  var g = led.g;
  var b = led.b;

  var label = "rgb(" + r + ", " + g + ", " + b + ")";
  setText("ledLabel", label);

  byId("ledSwatch").style.backgroundColor = "rgb(" + r + "," + g + "," + b + ")";
}

/* ==========================================================
 * Polling stato e log
 * - /state?internal=1  e /log?internal=1
 * - refresh ogni 2 secondi
 * - log: aggiornamento DOM solo se cambia (anti-flicker)
 * ========================================================== */
var lastLogHtml = "";

function refreshState() {
  fetch("/state?internal=1", { method: "GET" })
    .then(function(resp) {
      return resp.json();
    })
    .then(function(data) {
      setText("stateBox", JSON.stringify(data, null, 2));
      updateLedLabelFromState(data);
    })
    .catch(function(err) {
      setText("stateBox", "Errore /state: " + String(err));
    });
}

function refreshLog() {
  fetch("/log?internal=1", { method: "GET" })
    .then(function(resp) {
      return resp.text();
    })
    .then(function(html) {
      if (html !== lastLogHtml) {
        byId("logBox").innerHTML = html;
        lastLogHtml = html;
      }
    })
    .catch(function(err) {
      /* nessun reset visibile del box: loggo solo in console */
      console.log("Errore /log: " + String(err));
    });
}

/* Prima esecuzione */
refreshState();
refreshLog();

/* Frequenza: 2 secondi */
setInterval(refreshState, 2000);
setInterval(refreshLog, 2000);
</script>

</body>
</html>