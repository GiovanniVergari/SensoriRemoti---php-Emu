<?php
/************************************************************
 * lib_db.php
 * Connessione MySQL (PDO) + funzioni base
 *
 * Best practice:
 * - spostare credenziali in secrets.php (NON versionare su GitHub)
 * - secrets.php preferibilmente "return array(...)" per essere letto
 *   dentro le funzioni senza usare global.
 ************************************************************/

/* ==========================================================
 * CARICAMENTO CONFIG (da secrets.php)
 * ========================================================== */
function db_load_config() {
    $secrets_path = __DIR__ . "/secrets.php";

    if (!file_exists($secrets_path)) {
        http_response_code(500);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(array(
            "ok" => false,
            "error" => "DB_SECRETS_MISSING",
            "detail" => "File mancante: " . $secrets_path
        ), JSON_PRETTY_PRINT);
        exit;
    }

    /*
     * Modalità consigliata:
     *   secrets.php -> return array('host'=>..., 'name'=>..., 'user'=>..., 'pass'=>...);
     *
     * Modalità compatibilità:
     *   secrets.php definisce $host, $name, $user, $pass
     */
    $cfg = include $secrets_path;

    /* Se secrets.php ritorna un array, uso quello */
    if (is_array($cfg)) {
        return $cfg;
    }

    /* Altrimenti provo a leggere le variabili definite in secrets.php */
    $out = array();

    if (isset($GLOBALS["host"])) { $out["host"] = $GLOBALS["host"]; }
    if (isset($GLOBALS["name"])) { $out["name"] = $GLOBALS["name"]; }
    if (isset($GLOBALS["user"])) { $out["user"] = $GLOBALS["user"]; }
    if (isset($GLOBALS["pass"])) { $out["pass"] = $GLOBALS["pass"]; }

    return $out;
}

/* ==========================================================
 * CONNESSIONE PDO (singleton)
 * ========================================================== */
function db_get_pdo() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $cfg = db_load_config();

    $db_host = "";
    $db_name = "";
    $db_user = "";
    $db_pass = "";

    if (isset($cfg["host"])) { $db_host = strval($cfg["host"]); }
    if (isset($cfg["name"])) { $db_name = strval($cfg["name"]); }
    if (isset($cfg["user"])) { $db_user = strval($cfg["user"]); }
    if (isset($cfg["pass"])) { $db_pass = strval($cfg["pass"]); }

    if ($db_host === "" || $db_name === "" || $db_user === "") {
        http_response_code(500);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(array(
            "ok" => false,
            "error" => "DB_SECRETS_INVALID",
            "detail" => "Configurazione DB incompleta: servono host, name, user (pass può essere vuota)."
        ), JSON_PRETTY_PRINT);
        exit;
    }

    $dsn = "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        http_response_code(500);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(array(
            "ok" => false,
            "error" => "DB_CONNECTION_FAILED",
            "detail" => $e->getMessage()
        ), JSON_PRETTY_PRINT);
        exit;
    }

    return $pdo;
}

/* ==========================================================
 * HELPER QUERY PREPARED
 * ========================================================== */
function db_exec($sql, $params) {
    $pdo = db_get_pdo();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}