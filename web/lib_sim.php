<?php
/************************************************************
 * lib_sim.php
 * Simulazione sensori:
 * - valori realistici
 * - variazione lenta nel tempo
 * - stabilità per alcuni secondi (polling)
 ************************************************************/

function sim_now_iso_utc() {
    return gmdate("Y-m-d\TH:i:s\Z");
}

function sim_clamp($v, $min, $max) {
    if ($v < $min) {
        return $min;
    }
    if ($v > $max) {
        return $max;
    }
    return $v;
}

function sim_slot_seed($slot_seconds) {
    $t = time();
    $slot = intval($t / $slot_seconds);
    return $slot;
}

function sim_noise($seed, $min, $max) {
    mt_srand($seed);
    $r = mt_rand() / mt_getrandmax();
    $v = $min + ($max - $min) * $r;
    return $v;
}

function sim_wave($t, $period_seconds, $amplitude) {
    $angle = 2.0 * M_PI * ($t / $period_seconds);
    $value = sin($angle) * $amplitude;
    return $value;
}

function sim_read_sensors() {
    $t = time();
    $seed = sim_slot_seed(5);

    $temp = 22.0 + sim_wave($t, 3600, 1.2) + sim_noise($seed + 10, -0.4, 0.4);
    $temp = sim_clamp($temp, 18.0, 30.0);

    $hum = 45.0 + sim_wave($t, 2700, 6.0) + sim_noise($seed + 20, -2.0, 2.0);
    $hum = sim_clamp($hum, 25.0, 75.0);

    /* Lux: simulazione giorno/notte (24h) */
    $day_component = (sim_wave($t, 86400, 1.0) + 1.0) / 2.0;
    $lux = 20 + ($day_component * 1800) + sim_noise($seed + 30, -40, 40);
    $lux = sim_clamp($lux, 0, 2000);

    /* Luminosità percentuale (stile progetto: percent) */
    $lux_percent = intval(sim_clamp(($lux / 2000.0) * 100.0, 0, 100));

    return array(
        "temperature" => round($temp, 2),
        "humidity" => round($hum, 2),
        "light" => array(
            "adc" => intval(($lux / 2000.0) * 4095.0),
            "percent" => $lux_percent
        )
    );
}