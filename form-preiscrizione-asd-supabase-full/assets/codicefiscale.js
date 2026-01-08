(function(){
  // --- Normalizzazione label ---
  function norm(s){
    if(!s) return '';
    return s
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'') // rimuove accenti
      .toUpperCase()
      .replace(/\(.*?\)/g,'')      // rimuove parentesi e contenuto (es. "ROMA (RM)")
      .replace(/[^A-Z0-9 ]/g,' ')  // rimuove apostrofi, trattini, ecc.
      .replace(/\s+/g,' ')         // spazi multipli → singolo
      .trim();
  }

  // --- Prepara mappe normalizzate all'avvio ---
  let MAP = {};
  function buildMap(){
    MAP = {};
    if (window.CODICI_COMUNI) {
      for (const k in window.CODICI_COMUNI) {
        MAP[norm(k)] = window.CODICI_COMUNI[k];
      }
    }
    if (window.CODICI_ESTERI) {
      for (const k in window.CODICI_ESTERI) {
        MAP[norm(k)] = window.CODICI_ESTERI[k];
      }
    }
  }

  // --- Utility CF ---
  function cons(s){ return (s||'').toUpperCase().replace(/[^BCDFGHJKLMNPQRSTVWXYZ]/g,''); }
  function vows(s){ return (s||'').toUpperCase().replace(/[^AEIOU]/g,''); }
  function codeCognome(cg){ const c=cons(cg), v=vows(cg); return (c+v+'XXX').substring(0,3); }
  function codeNome(nm){
    const c=cons(nm);
    if (c.length>=4) return (c[0]+c[2]+c[3]).substring(0,3);
    const v=vows(nm);
    return (c+v+'XXX').substring(0,3);
  }
  const MONTH = {'01':'A','02':'B','03':'C','04':'D','05':'E','06':'H','07':'L','08':'M','09':'P','10':'R','11':'S','12':'T'};
  const ODD = {'0':1,'1':0,'2':5,'3':7,'4':9,'5':13,'6':15,'7':17,'8':19,'9':21,'A':1,'B':0,'C':5,'D':7,'E':9,'F':13,'G':15,'H':17,'I':19,'J':21,'K':2,'L':4,'M':18,'N':20,'O':11,'P':3,'Q':6,'R':8,'S':12,'T':14,'U':16,'V':10,'W':22,'X':25,'Y':24,'Z':23};
  const EVEN= {'0':0,'1':1,'2':2,'3':3,'4':4,'5':5,'6':6,'7':7,'8':8,'9':9,'A':0,'B':1,'C':2,'D':3,'E':4,'F':5,'G':6,'H':7,'I':8,'J':9,'K':10,'L':11,'M':12,'N':13,'O':14,'P':15,'Q':16,'R':17,'S':18,'T':19,'U':20,'V':21,'W':22,'X':23,'Y':24,'Z':25};
  const CTRL = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  function ctrlChar(base15){
    let sum=0;
    for (let i=0;i<15;i++){
      const ch = base15[i].toUpperCase();
      sum += ((i+1)%2===1) ? (ODD[ch]||0) : (EVEN[ch]||0);
    }
    return CTRL[sum%26];
  }

  function generaCF(){
    const campoNome = document.querySelector('input[name="nome"]');
    const campoCognome = document.querySelector('input[name="cognome"]');
    const campoSesso = document.querySelector('select[name="sesso"]');
    const campoData = document.querySelector('input[name="data_nascita"]');

    // compatibilità: campo luogo può chiamarsi citta_nascita o avere due input
    const campoComune = document.getElementById('comune_nascita') || document.querySelector('input[name="citta_nascita"]');
    const campoNazione = document.getElementById('nazione_nascita'); // se presente
    const campoCF = document.getElementById('codice_fiscale');
    const override = document.getElementById('codice_catastale_override');

    const nome = (campoNome?.value || '').trim();
    const cognome = (campoCognome?.value || '').trim();
    const sesso = (campoSesso?.value || '').trim();
    const data = (campoData?.value || '').trim();

    let luogo = '';
    if (campoComune && campoComune.value) luogo = campoComune.value;
    else if (campoNazione && campoNazione.value) luogo = campoNazione.value;
    else {
      // fallback sul vecchio singolo campo
      const campoUnico = document.querySelector('input[name="citta_nascita"]');
      luogo = (campoUnico?.value || '').trim();
    }

    if (!nome || !cognome || !sesso || !data || !luogo) {
      alert('Compila nome, cognome, sesso, data e luogo di nascita.');
      return;
    }

    const [Y,M,D] = data.split('-');
    if (!Y || !M || !D) { alert('Data di nascita non valida.'); return; }
    let giorno = parseInt(D,10);
    if ((sesso === 'F') || (sesso.toUpperCase()==='FEMMINA')) giorno += 40;

    // 1) override manuale se presente e valorizzato
    let codiceCat = (override?.value || '').trim().toUpperCase();

    // 2) altrimenti lookup su mappe normalizzate
    if (!codiceCat) {
      const key = norm(luogo);
      codiceCat = MAP[key] || '';
    }

    // 3) se ancora vuoto → prompt
    if (!codiceCat) {
      const risposta = prompt('Codice catastale non trovato. Inseriscilo manualmente (es. H501 per Roma, Z110 per Francia):');
      if (!risposta) return;
      codiceCat = risposta.trim().toUpperCase();
    }

    // montaggio CF
    const base15 = (codeCognome(cognome) +
                    codeNome(nome) +
                    Y.slice(-2) +
                    (MONTH[M]||'') +
                    String(giorno).padStart(2,'0') +
                    codiceCat).toUpperCase();

    const cf = base15 + ctrlChar(base15);
    if (campoCF) campoCF.value = cf;
  }

  document.addEventListener('DOMContentLoaded', function(){
    // verifica che le mappe siano caricate (script dei dataset deve essere incluso PRIMA)
    if (typeof window.CODICI_COMUNI === 'undefined') {
      console.warn('CODICI_COMUNI non caricato: verifica <script src="assets/codici_comuni.js"> prima di codicefiscale.js');
    }
    buildMap();
    const btn = document.getElementById('calcola_cf');
    if (btn) btn.addEventListener('click', generaCF);
  });
})();
