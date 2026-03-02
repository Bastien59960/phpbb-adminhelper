<?php
if (!defined('IN_PHPBB'))
{
    exit;
}

if (empty($lang) || !is_array($lang))
{
    $lang = [];
}

$lang = array_merge($lang, [
    'ADMINHELPER_SEARCH_BY_EMAIL'   => 'O cerca per indirizzo email',
    'ADMINHELPER_EMAIL_PLACEHOLDER' => 'es. utente@example.com',
    'ADMINHELPER_EMAIL_NOT_FOUND'   => 'Nessun utente trovato con l\'indirizzo: %s',
    'ADMINHELPER_HTML_MESSAGE_LABEL' => 'Il tuo messaggio (HTML)',
    'ADMINHELPER_HTML_MESSAGE_EXPLAIN' => 'Incolla solo il contenuto visivo (es. inizia con &lt;div&gt;). I tag &lt;!DOCTYPE html&gt;, &lt;html&gt;, &lt;head&gt; e &lt;body&gt; vengono gestiti automaticamente.',
    'ADMINHELPER_USE_HTML_LABEL' => 'Invia versione HTML',
    'ADMINHELPER_USE_HTML_EXPLAIN' => 'Se attivato, l\'email viene inviata come multipart (testo semplice + HTML).',
    'ADMINHELPER_APPEND_UNSUBSCRIBE_LABEL' => 'Aggiungi istruzioni di disiscrizione',
    'ADMINHELPER_APPEND_UNSUBSCRIBE_EXPLAIN' => 'Aggiunge un footer con link alle preferenze del profilo (login richiesto) per disattivare le email massive dell\'amministratore.',
    'ADMINHELPER_ENABLE_ONE_CLICK_LABEL' => 'Abilita disiscrizione one-click (RFC8058)',
    'ADMINHELPER_ENABLE_ONE_CLICK_EXPLAIN' => 'Aggiunge intestazioni List-Unsubscribe e List-Unsubscribe-Post e invia un destinatario per email per una disiscrizione one-click conforme RFC8058.',
    'ADMINHELPER_UNSUBSCRIBE_TEXT' => 'Per non ricevere piu email massive dell\'amministratore, accedi e disattiva l\'opzione corrispondente nelle preferenze del tuo profilo: %s',
    'ADMINHELPER_UNSUBSCRIBE_HTML' => '<hr><p><strong>Disiscrizione:</strong> Per non ricevere piu email massive dell\'amministratore, accedi e disattiva l\'opzione corrispondente nelle preferenze del tuo profilo: <a href="%1$s">%1$s</a></p>',
    'ADMINHELPER_NOTIFY_UNSUBSCRIBE_TEXT' => 'Per non ricevere piu email di notifica del forum, usa la disiscrizione one-click: %1$s\nOppure accedi e disattiva le notifiche email qui: %2$s',
    'ADMINHELPER_UNSUB_INVALID_REQUEST' => 'Richiesta di disiscrizione non valida.',
    'ADMINHELPER_UNSUB_USER_NOT_FOUND' => 'Utente non trovato.',
    'ADMINHELPER_UNSUB_INVALID_SIGNATURE' => 'Firma di disiscrizione non valida.',
    'ADMINHELPER_UNSUB_EXPIRED' => 'Link di disiscrizione scaduto.',
    'ADMINHELPER_UNSUB_DONE' => 'Disiscrizione completata. Non riceverai piu email massive amministratore.',
    'ADMINHELPER_UNSUB_PAGE_TITLE' => 'Disiscrizione email',
    'ADMINHELPER_UNSUB_PAGE_HEADING' => 'Disiscriviti dalle email massive amministratore',
    'ADMINHELPER_UNSUB_PAGE_EXPLAIN' => 'Questa azione disattiva solo le email massive amministratore. Non elimina il tuo account forum.',
    'ADMINHELPER_UNSUB_PAGE_BUTTON' => 'Conferma disiscrizione',
    'ADMINHELPER_NOTIFY_UNSUB_DONE' => 'Disiscrizione completata. Non riceverai piu email di notifica del forum.',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_TITLE' => 'Disiscrizione notifiche forum',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_HEADING' => 'Disiscriviti dalle email di notifica del forum',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_EXPLAIN' => 'Questa azione disattiva le notifiche forum via email. Non elimina il tuo account forum.',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_BUTTON' => 'Conferma disiscrizione',
    'ADMINHELPER_MIME_MULTIPART_NOTICE' => 'Questo e un messaggio multiparte in formato MIME.',
]);
