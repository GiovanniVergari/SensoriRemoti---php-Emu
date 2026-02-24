# SensoriRemoti – Simulatore REST (Altervista + PHP + MySQL)

Simulatore software del progetto SensoriRemoti ESP32, progettato per permettere agli studenti di esercitarsi da casa sugli stessi endpoint REST utilizzati in laboratorio.

Obiettivo principale:
- stessi endpoint dell’ESP32
- stessa struttura JSON
- stato persistente su database
- sistema di logging controllato

---

## Architettura

Client (Browser / Python requests)
        ↓
    api.php  (router REST)
        ↓
    ├── MySQL (device_state)
    ├── MySQL (request_log)
    └── lib_sim.php (sensori simulati)

Dashboard:
- index.php
  - GET /state?internal=1
  - GET /log?internal=1

Le richieste con parametro internal=1 NON vengono registrate nel database.

---

## Struttura File

/
├── index.php
├── api.php
├── lib_db.php
├── lib_sim.php
└── .htaccess

---

## Database

### Tabella device_state

Contiene lo stato persistente del dispositivo simulato.

CREATE TABLE device_state (
  id INT PRIMARY KEY,
  led_r TINYINT UNSIGNED DEFAULT 0,
  led_g TINYINT UNSIGNED DEFAULT 0,
  led_b TINYINT UNSIGNED DEFAULT 0,
  last_beep_ms INT DEFAULT 0,
  last_beep_duty INT DEFAULT 0,
  song_is_playing TINYINT(1) DEFAULT 0,
  song_gap_ms INT DEFAULT 20,
  song_duty INT DEFAULT 110,
  song_json MEDIUMTEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

---

### Tabella request_log

Contiene il log delle richieste REST.

CREATE TABLE request_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ip VARCHAR(45),
  method VARCHAR(8),
  endpoint VARCHAR(64),
  query_string VARCHAR(512),
  user_agent VARCHAR(255),
  body_trunc TEXT
);

---

## Endpoint REST

GET /state
Restituisce stato completo del dispositivo (sensori + attuatori).

GET /sensors
Restituisce solo dati sensori simulati.

GET /setLed?r=&g=&b=
Imposta LED RGB (persistente su DB).

GET /beep?ms=&duty=
Simula attivazione buzzer.

POST /playSong
Accetta JSON con struttura:
{
  "gapMs": 20,
  "duty": 110,
  "melody": [
    { "freq": 440, "ms": 200 }
  ]
}

GET /stopSong
Ferma la riproduzione simulata.

GET /log
Restituisce log HTML filtrato:
- esclude endpoint /log
- esclude endpoint /state
- maschera IP: xxx.yyy.C.D

---

## Logging e Privacy

Mascheramento IP nel log pubblico:
192.168.10.23 → xxx.yyy.10.23

Le richieste interne della dashboard:
/state?internal=1
/log?internal=1

non vengono salvate nel database.

Questo evita:
- auto‑inquinamento del log
- crescita inutile della tabella
- rumore nei dati didattici

---

## Dashboard (index.php)

Visualizza:
- Colore LED corrente (label + swatch)
- Stato JSON completo del device
- Log richieste filtrato

Aggiornamento automatico:
- ogni 2 secondi
- aggiornamento DOM solo se cambia (no flicker)

---

## Script di Test Python

Esempio utilizzo:

python test_sensori_remoti.py --base-url https://NOME.altervista.org --once

Permette di verificare:
- stato
- sensori
- persistenza LED
- persistenza buzzer
- playSong / stopSong
- log

---

## Contesto Didattico

Questo simulatore consente continuità tra:
- laboratorio (ESP32 reale)
- lavoro domestico (API simulata)

Gli studenti possono esercitarsi su:
- richieste REST
- parsing JSON
- gestione stato persistente
- analisi log
- sviluppo client Python

Stesso modello mentale.
Stessa API.
Hardware reale solo quando necessario.

