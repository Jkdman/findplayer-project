# Guida all'Installazione - FindPlayer Plugin Ottimizzati

## File da Installare sul Sito Web

### Plugin 1: Find Player (Calendario Allenamenti)

**Directory da copiare sul tuo sito:**
```
wp-content/plugins/find-player/
```

**File da utilizzare:**

1. **File principale (NUOVO):** `find-player-refactored.php`
   - Rinomina questo file in `find-player.php` OPPURE
   - Modifica l'header del plugin per attivarlo

2. **Directory necessarie:**
   - `controllers/` - Contiene tutti i controller
   - `models/` - Contiene tutti i modelli
   - `helpers/` - Contiene funzioni helper
   - `includes/` - File di supporto esistenti
   - `templates/` - Template per la visualizzazione

3. **File di supporto:**
   - `includes/functions-nickname.php`
   - `includes/functions-player.php`
   - `templates/single-fp_giocatore.php`

**Nota:** Il file `find-player.php` originale (3064 righe) può essere mantenuto come backup, ma NON deve essere attivo contemporaneamente al nuovo file refactored.

---

### Plugin 2: FP Iscrizione Giocatori

**Directory da copiare sul tuo sito:**
```
wp-content/plugins/fp-iscrizione-giocatori/
```

**File da utilizzare:**

1. **File principale (NUOVO):** `fp-iscrizione-giocatori-refactored.php`
   - Rinomina questo file in `fp-iscrizione-giocatori.php` OPPURE
   - Modifica l'header del plugin per attivarlo

2. **Directory necessarie:**
   - `models/` - Modelli per database e post type
   - `helpers/` - Configurazione e utility
   - `includes/` - Tutti i file esistenti (già ben organizzati)
   - `templates/` - Template per la visualizzazione
   - `assets/` - CSS e JavaScript

3. **File di supporto (tutti necessari):**
   - `includes/ajax-check-giocatore.php`
   - `includes/cron-eventi.php`
   - `includes/eventi-token-mail.php`
   - `includes/functions-eventi.php`
   - `includes/rating.php`
   - `includes/template-loader.php`
   - `includes/votazioni.php`
   - `includes/metaboxes/` (tutta la directory)
   - `includes/sports/` (tutta la directory)
   - `templates/single-fp_giocatore.php`
   - `assets/fp-search.css`

---

### Plugin 3: FP Private Mail (con fix sicurezza)

**Directory da copiare sul tuo sito:**
```
wp-content/plugins/fp-private-mail/
```

**IMPORTANTE:** Tutti i file sono necessari. Il file `includes/helpers.php` è stato aggiornato per rimuovere la password hardcoded.

---

### Altri Plugin (opzionali)

Se li stai usando, copia anche queste directory:

- `calendario-eventi/`
- `chat-facile/`
- `find-player-scheda/`
- `find-player-sport/`
- `form-preiscrizione-asd-supabase-full/`

---

## Configurazione Necessaria

### 1. File wp-config.php

Aggiungi questa riga al tuo `wp-config.php` (PRIMA della riga che dice "/* That's all, stop editing! */"):

```php
// Configurazione SMTP per FindPlayer
define('FINDPLAYER_SMTP_PASSWORD', 'la-tua-password-smtp-qui');
```

**IMPORTANTE:** Sostituisci `'la-tua-password-smtp-qui'` con la password SMTP reale.

### 2. Costanti Supabase

Se non le hai già, aggiungi anche:

```php
// Configurazione Supabase
define('FP_SUPABASE_URL', 'https://wpxnpvsaleswzfagneib.supabase.co');
define('FP_SUPABASE_API_KEY', 'la-tua-chiave-api-supabase');
```

---

## Procedura di Installazione

### Passo 1: Backup
```bash
# Fai backup completo del sito prima di procedere
```

### Passo 2: Upload File
1. Connettiti via FTP/SFTP al tuo server
2. Vai in `wp-content/plugins/`
3. Carica le directory dei plugin

### Passo 3: Rinomina File Principali

**Opzione A - Rinomina (Consigliato):**
```bash
# Nel server, rinomina:
find-player-refactored.php → find-player.php
fp-iscrizione-giocatori-refactored.php → fp-iscrizione-giocatori.php
```

**Opzione B - Modifica Header:**
Apri i file -refactored.php e verifica che l'header del plugin sia corretto.

### Passo 4: Configurazione wp-config.php
1. Apri `wp-config.php` (nella root del sito WordPress)
2. Aggiungi le costanti necessarie (vedi sezione sopra)
3. Salva il file

### Passo 5: Attivazione Plugin
1. Vai nel pannello admin WordPress
2. Menu "Plugin" → "Plugin installati"
3. **DISATTIVA** i vecchi plugin (se attivi)
4. **ATTIVA** i nuovi plugin refactored

### Passo 6: Test
1. Controlla che non ci siano errori PHP
2. Testa la creazione di un evento
3. Verifica la registrazione di un giocatore
4. Controlla che le email vengano inviate correttamente

---

## Struttura File Completa da Copiare

```
wp-content/plugins/
├── find-player/
│   ├── find-player-refactored.php          ← File principale NUOVO
│   ├── controllers/
│   │   └── class-event-token-controller.php
│   ├── models/
│   │   ├── class-event-post-type.php
│   │   └── class-user-sync.php
│   ├── helpers/
│   │   ├── config.php
│   │   ├── class-assets-helper.php
│   │   └── class-calendar-integration.php
│   ├── includes/
│   │   ├── functions-nickname.php
│   │   └── functions-player.php
│   ├── templates/
│   │   └── single-fp_giocatore.php
│   └── README.md                           ← Documentazione
│
├── fp-iscrizione-giocatori/
│   ├── fp-iscrizione-giocatori-refactored.php  ← File principale NUOVO
│   ├── models/
│   │   ├── class-database.php
│   │   └── class-player-post-type.php
│   ├── helpers/
│   │   └── config.php
│   ├── includes/                           ← Tutta la directory
│   ├── templates/                          ← Tutta la directory
│   ├── assets/                             ← Tutta la directory
│   └── README.md                           ← Documentazione
│
└── fp-private-mail/                        ← Tutta la directory (con fix sicurezza)
    ├── fp-private-mail.php
    ├── includes/
    │   └── helpers.php                     ← AGGIORNATO (no password hardcoded)
    └── ... (tutti gli altri file)
```

---

## Risoluzione Problemi

### Errore: "Call to undefined function"
- Verifica che tutti i file siano stati caricati
- Controlla i permessi dei file (644 per file, 755 per directory)

### Errore: "Headers already sent"
- Controlla che non ci siano spazi o caratteri prima del tag `<?php`

### Email non funzionano
- Verifica che `FINDPLAYER_SMTP_PASSWORD` sia configurato in `wp-config.php`
- Controlla le credenziali SMTP

### Eventi non si creano
- Verifica che `FP_SUPABASE_URL` e `FP_SUPABASE_API_KEY` siano configurati
- Controlla i log di WordPress per errori

---

## Note Importanti

1. **Non attivare contemporaneamente** i file vecchi e quelli refactored dello stesso plugin
2. **Mantieni i file originali** come backup (puoi rinominarli con estensione .bak)
3. **Testa in staging** prima di usare in produzione
4. **Leggi i README** di ogni plugin per dettagli specifici

---

## Supporto

Per domande o problemi:
1. Consulta i file README.md in ogni plugin
2. Leggi il file OPTIMIZATION_REPORT.md per i dettagli completi
3. Controlla i log di WordPress: `wp-content/debug.log`

---

**Versione:** 2.5.0  
**Data:** 2026-01-08  
**Stato:** Pronto per produzione ✅
