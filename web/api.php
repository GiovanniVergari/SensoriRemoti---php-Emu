<?php
/************************************************************
 * api.php
 * Router endpoint (ESP32-like) + persistenza stato su DB
 *
 * MIGLIORIA CHIAVE:
 * - Le richieste con ?internal=1 NON vengono loggate nel DB
 *   (tipicamente: polling della dashboard)
 *
 * /log (HTML) inoltre:
 * - NON mostra endpoint 'log' e 'state'
 * - maschera i primi due ottetti dell'IP: xxx.yyy.C.D
 ************************************************************/

require_once __DIR__ . "/lib_db.php";
require_once __DIR__ . "/lib_sim.php";

/* ==========================================================
 * HEADER (CORS + no-cache)
 * ========================================================== */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    echo "";
    exit;
}

/* ==========================================================
 * IDENTIFICAZIONE ENDPOINT (da REQUEST_URI)
 * ========================================================== */
$uri_path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$endpoint = trim($uri_path, "/");

/* Se il sito è in sottocartella, prenda solo l’ultimo pezzo */
$parts = explode("/", $endpoint);
$endpoint = $parts[count($parts) - 1];

if ($endpoint === "") {
    $endpoint = "state";
}

/* ==========================================================
 * UTILITY: IP + internal flag + masking
 * ========================================================== */
function get_client_ip() {
    if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
        $xff = trim($_SERVER["HTTP_X_FORWARDED_FOR"]);
        if ($xff !== "") {
            $chunks = explode(",", $xff);
            $first = trim($chunks[0]);
            if ($first !== "") {
                return $first;
            }
        }
    }

    if (isset($_SERVER["REMOTE_ADDR"])) {
        return $_SERVER["REMOTE_ADDR"];
    }

    return "0.0.0.0";
}

function is_internal_request() {
    if (isset($_GET["internal"])) {
        if (strval($_GET["internal"]) === "1") {
            return true;
        }
    }
    return false;
}

function mask_ip_for_log($ip) {
    $ip = strval($ip);

    /* IPv4: A.B.C.D -> xxx.yyy.C.D */
    if (preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $ip) === 1) {
        $parts = explode(".", $ip);
        if (count($parts) === 4) {
            return "xxx.yyy." . $parts[2] . "." . $parts[3];
        }
        return "xxx.yyy.xxx.xxx";
    }

    /* IPv6: maschera prudente (non richiesto, ma evita mostrare tutto) */
    if (strpos($ip, ":") !== false) {
        $chunks = explode(":", $ip);
        $out = array();
        $i = 0;

        foreach ($chunks as $c) {
            if ($i < 2) {
                $out[] = "xxxx";
            } else {
                $out[] = $c;
            }
            $i++;
        }

        return implode(":", $out);
    }

    return "xxx.yyy.?.?";
}

/* ==========================================================
 * LOGGING (DB)
 * Regole:
 * - se ?internal=1 -> NON loggare
 * - se endpoint è 'log' -> NON loggare (evita auto-inquinamento)
 * ========================================================== */
function log_request_to_db($endpoint) {
    $ip = get_client_ip();

    $method = "GET";
    if (isset($_SERVER["REQUEST_METHOD"])) {
        $method = strtoupper($_SERVER["REQUEST_METHOD"]);
    }

    $qs = "";
    if (isset($_SERVER["QUERY_STRING"])) {
        $qs = $_SERVER["QUERY_STRING"];
    }

    $ua = "";
    if (isset($_SERVER["HTTP_USER_AGENT"])) {
        $ua = substr($_SERVER["HTTP_USER_AGENT"], 0, 255);
    }

    $body_trunc = null;
    if ($method === "POST") {
        $raw = file_get_contents("php://input");
        if ($raw !== false) {
            $body_trunc = substr($raw, 0, 2000);
        }
    }

    db_exec(
        "INSERT INTO request_log (ip, method, endpoint, query_string, user_agent, body_trunc)
         VALUES (:ip, :method, :endpoint, :qs, :ua, :body)",
        array(
            ":ip" => $ip,
            ":method" => $method,
            ":endpoint" => $endpoint,
            ":qs" => $qs,
            ":ua" => $ua,
            ":body" => $body_trunc
        )
    );
}

/* Applicazione regole di logging */
if (!is_internal_request()) {
    if ($endpoint !== "log") {
        log_request_to_db($endpoint);
    }
}

/* ==========================================================
 * HELPER STATO DEVICE
 * ========================================================== */
function db_get_device_state() {
    $stmt = db_exec("SELECT * FROM device_state WHERE id = 1", array());
    $row = $stmt->fetch();

    if (!$row) {
        db_exec("INSERT INTO device_state (id) VALUES (1)", array());
        $stmt = db_exec("SELECT * FROM device_state WHERE id = 1", array());
        $row = $stmt->fetch();
    }

    return $row;
}

function db_set_led($r, $g, $b) {
    db_exec(
        "UPDATE device_state
         SET led_r = :r, led_g = :g, led_b = :b
         WHERE id = 1",
        array(":r" => $r, ":g" => $g, ":b" => $b)
    );
}

function db_set_beep($ms, $duty) {
    db_exec(
        "UPDATE device_state
         SET last_beep_ms = :ms, last_beep_duty = :duty
         WHERE id = 1",
        array(":ms" => $ms, ":duty" => $duty)
    );
}

function db_set_song($json, $gap_ms, $duty) {
    db_exec(
        "UPDATE device_state
         SET song_json = :js, song_gap_ms = :gap, song_duty = :duty, song_is_playing = 1
         WHERE id = 1",
        array(":js" => $json, ":gap" => $gap_ms, ":duty" => $duty)
    );
}

function db_stop_song() {
    db_exec(
        "UPDATE device_state
         SET song_is_playing = 0
         WHERE id = 1",
        array()
    );
}

/* ==========================================================
 * OUTPUT HELPERS
 * ========================================================== */
function out_json($obj, $code) {
    http_response_code($code);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function out_html($html, $code) {
    http_response_code($code);
    header("Content-Type: text/html; charset=utf-8");
    echo $html;
    exit;
}

function bad_request($detail) {
    out_json(array(
        "ok" => false,
        "error" => "BAD_REQUEST",
        "detail" => $detail
    ), 400);
}

/* ==========================================================
 * ROUTING ENDPOINT
 * ========================================================== */

/* GET /sensors */
if ($endpoint === "sensors") {
    $s = sim_read_sensors();

    out_json(array(
        "ok" => true,
        "timestamp_utc" => sim_now_iso_utc(),
        "sensors" => array(
            "temperature" => $s["temperature"],
            "humidity" => $s["humidity"]
        ),
        "light" => $s["light"]
    ), 200);
}

/* GET /state */
if ($endpoint === "state") {
    $s = sim_read_sensors();
    $st = db_get_device_state();

    $wifi = array(
        "ssid" => "SIM_NET",
        "ip" => "0.0.0.0"
    );

    out_json(array(
        "ok" => true,
        "timestamp_utc" => sim_now_iso_utc(),
        "wifi" => $wifi,
        "sensors" => array(
            "temperature" => $s["temperature"],
            "humidity" => $s["humidity"]
        ),
        "light" => $s["light"],
        "actuators" => array(
            "led" => array(
                "r" => intval($st["led_r"]),
                "g" => intval($st["led_g"]),
                "b" => intval($st["led_b"])
            ),
            "buzzer" => array(
                "last_beep_ms" => intval($st["last_beep_ms"]),
                "last_beep_duty" => intval($st["last_beep_duty"]),
                "song_is_playing" => intval($st["song_is_playing"])
            )
        )
    ), 200);
}

/* GET /setLed?r=&g=&b= */
if ($endpoint === "setLed") {
    if (!isset($_GET["r"]) || !isset($_GET["g"]) || !isset($_GET["b"])) {
        bad_request("Parametri richiesti: r, g, b (0..255)");
    }

    $r = intval($_GET["r"]);
    $g = intval($_GET["g"]);
    $b = intval($_GET["b"]);

    if ($r < 0 || $r > 255 || $g < 0 || $g > 255 || $b < 0 || $b > 255) {
        bad_request("Valori fuori range: r,g,b devono essere 0..255");
    }

    db_set_led($r, $g, $b);

    out_json(array(
        "ok" => true,
        "endpoint" => "setLed",
        "assigned" => array("r" => $r, "g" => $g, "b" => $b),
        "timestamp_utc" => sim_now_iso_utc()
    ), 200);
}

/* GET /beep?ms=&duty= */
if ($endpoint === "beep") {
    if (!isset($_GET["ms"]) || !isset($_GET["duty"])) {
        bad_request("Parametri richiesti: ms, duty");
    }

    $ms = intval($_GET["ms"]);
    $duty = intval($_GET["duty"]);

    if ($ms < 0 || $ms > 10000) {
        bad_request("ms fuori range (0..10000)");
    }
    if ($duty < 0 || $duty > 255) {
        bad_request("duty fuori range (0..255)");
    }

    db_set_beep($ms, $duty);

    out_json(array(
        "ok" => true,
        "endpoint" => "beep",
        "assigned" => array("ms" => $ms, "duty" => $duty),
        "timestamp_utc" => sim_now_iso_utc()
    ), 200);
}

/* POST /playSong */
if ($endpoint === "playSong") {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        out_json(array("ok" => false, "error" => "METHOD_NOT_ALLOWED"), 405);
    }

    $raw = file_get_contents("php://input");
    if ($raw === false || trim($raw) === "") {
        bad_request("Body JSON mancante");
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        bad_request("Body JSON non valido");
    }

    $gap_ms = 20;
    $duty = 110;

    if (isset($data["gapMs"])) {
        $gap_ms = intval($data["gapMs"]);
    }
    if (isset($data["duty"])) {
        $duty = intval($data["duty"]);
    }

    if (!isset($data["melody"]) || !is_array($data["melody"])) {
        bad_request("Campo richiesto: melody (array)");
    }

    db_set_song($raw, $gap_ms, $duty);

    out_json(array(
        "ok" => true,
        "endpoint" => "playSong",
        "accepted" => array(
            "gapMs" => $gap_ms,
            "duty" => $duty,
            "notes" => "Simulazione: stato salvato su DB (non riproduzione reale)"
        ),
        "timestamp_utc" => sim_now_iso_utc()
    ), 200);
}

/* GET /stopSong */
if ($endpoint === "stopSong") {
    db_stop_song();

    out_json(array(
        "ok" => true,
        "endpoint" => "stopSong",
        "timestamp_utc" => sim_now_iso_utc()
    ), 200);
}

/* GET /log : HTML "pulito" */
if ($endpoint === "log") {
    /*
     * Log pulito:
     * - escludo endpoint 'log' e 'state' dalla visualizzazione
     * - maschero IP: xxx.yyy
     */
    $stmt = db_exec(
        "SELECT ts, ip, method, endpoint, query_string
         FROM request_log
         WHERE endpoint <> 'log'
           AND endpoint <> 'state'
         ORDER BY ts DESC
         LIMIT 50",
        array()
    );
    $rows = $stmt->fetchAll();

    $html = "";
    $html .= "<div style='font-family: monospace; font-size: 13px;'>";

    if (count($rows) === 0) {
        $html .= "<div>Nessuna richiesta registrata.</div>";
    } else {
        foreach ($rows as $r) {
            $ts = htmlspecialchars($r["ts"]);
            $ip = htmlspecialchars(mask_ip_for_log($r["ip"]));
            $m = htmlspecialchars($r["method"]);
            $ep = htmlspecialchars($r["endpoint"]);
            $qs = htmlspecialchars($r["query_string"]);

            $line = "[" . $ts . "] " . $ip . " " . $m . " /" . $ep;
            if ($qs !== "") {
                $line .= " ?" . $qs;
            }

            $html .= "<div>" . $line . "</div>";
        }
    }

    $html .= "</div>";

    out_html($html, 200);
}

/* Fallback */
out_json(array(
    "ok" => false,
    "error" => "NOT_FOUND",
    "detail" => "Endpoint sconosciuto: " . $endpoint
), 404);