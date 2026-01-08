<?php
if (!defined('ABSPATH')) exit;

/**
 * ============================================================
 *  PDF Helpers – versione 2025
 *  Genera il modulo riepilogativo della preiscrizione ASD
 *  utilizzando lo shortcode [riepilogo_iscrizione_asd]
 *  Nessuna libreria esterna, PDF testuale compatto.
 * ============================================================
 */

/**
 * 🔹 Escape sicuro per testo PDF
 */
function pdf_escape($s) {
    $s = str_replace(["\\", "(", ")"], ["\\\\", "\\(", "\\)"], $s);
    $s = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '?', $s);
    return $s;
}

/**
 * 🔹 Scrive un PDF minimale in Helvetica 10pt
 */
function asd_tr_pdf_text_only($filepath, $title, $lines) {
    $pdf  = "%PDF-1.4\n";
    $pdf .= "1 0 obj <</Type /Catalog /Pages 2 0 R>> endobj\n";
    $pdf .= "2 0 obj <</Type /Pages /Kids [3 0 R] /Count 1>> endobj\n";

    // Inizio contenuto
    $content = "BT /F1 14 Tf 0 g 50 800 Td (" . pdf_escape($title) . ") Tj ET\n";
    $y = 780;
    foreach ($lines as $ln) {
        if (trim($ln) === '') continue;
        $content .= "BT /F1 10 Tf 0 g 50 $y Td (" . pdf_escape($ln) . ") Tj ET\n";
        $y -= 14;
        if ($y < 50) break;
    }

    $len = strlen($content);
    $pdf .= "3 0 obj <</Type /Page /Parent 2 0 R "
          . "/MediaBox [0 0 595 842] "
          . "/Resources <</Font <</F1 5 0 R>>>> "
          . "/Contents 4 0 R>> endobj\n";
    $pdf .= "4 0 obj <</Length $len>> stream\n$content\nendstream endobj\n";
    $pdf .= "5 0 obj <</Type /Font /Subtype /Type1 /BaseFont /Helvetica>> endobj\n";

    $xref_offset = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    $offsets = [9, 58, 115, 220, 350];
    foreach ($offsets as $off) {
        $pdf .= sprintf("%010d 00000 n \n", $off);
    }
    $pdf .= "trailer <</Size 6 /Root 1 0 R>>\nstartxref\n" . ($xref_offset + 10) . "\n%%EOF";
    file_put_contents($filepath, $pdf);
}

/**
 * ============================================================
 * 🔹 Genera il PDF riepilogativo del modulo di iscrizione
 * ============================================================
 */
function asd_tr_generate_ricevuta_pdf($filepath, $iscritto, $importo, $voce, $num_ricevuta, $data_emissione) {
    // 1️⃣ Recupera HTML del riepilogo usando lo shortcode già esistente
    $html = do_shortcode('[riepilogo_iscrizione_asd]');

    // 2️⃣ Rimuove pulsante stampa e tag superflui
    $html = preg_replace('/<button.*?<\/button>/is', '', $html);
    $html = preg_replace('/<style.*?<\/style>/is', '', $html);
    $html = preg_replace('/<script.*?<\/script>/is', '', $html);

    // 3️⃣ Converte in testo semplice
    $text = wp_strip_all_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // 4️⃣ Divide il testo in righe
    $lines = explode("\n", wordwrap($text, 100));

    // 5️⃣ Genera effettivamente il PDF
    $titolo = "Modulo Preiscrizione ASD Oltrecity";
    asd_tr_pdf_text_only($filepath, $titolo, $lines);
}

/**
 * Dummy function mantenuta per compatibilità
 * (non genera più alcuna tessera)
 */
function asd_tr_generate_tessera_pdf($filepath, $iscritto, $scadenza) {
    $lines = ["Documento riepilogativo preiscrizione", "Generato automaticamente"];
    asd_tr_pdf_text_only($filepath, "Riepilogo Iscrizione", $lines);
}
