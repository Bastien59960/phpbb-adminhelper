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
    'ACP_ADMINHELPER_TITLE' => 'Admin Helper',
    'ACP_ADMINHELPER_UNSUBSCRIBE_LOGS' => 'Log disiscrizione',
    'ACP_ADMINHELPER_UNSUBSCRIBE_LOGS_EXPLAIN' => 'Registra le disiscrizioni one-click (RFC 8058) delle email massive amministratore e delle notifiche forum.',
    'ACP_ADMINHELPER_COUNTER_OVERVIEW' => 'Panoramica globale email',
    'ACP_ADMINHELPER_COUNTER_TOTAL_MEMBERS' => 'Membri conteggiati',
    'ACP_ADMINHELPER_COUNTER_MASSMAIL' => 'Email di massa',
    'ACP_ADMINHELPER_COUNTER_FORUM_NOTIFY' => 'Notifiche forum (email)',
    'ACP_ADMINHELPER_COUNTER_SUBSCRIBED' => 'Iscritti',
    'ACP_ADMINHELPER_COUNTER_UNSUBSCRIBED' => 'Disiscritti',
    'ACP_ADMINHELPER_LOG_TOTAL' => 'Totale eventi',
    'ACP_ADMINHELPER_NOTIF_CLEANUP_TITLE' => 'Pulizia notifiche vecchie',
    'ACP_ADMINHELPER_NOTIF_CLEANUP_COUNT' => 'Notifiche non lette piu vecchie della soglia',
    'ACP_ADMINHELPER_NOTIF_CLEANUP_DAYS' => 'Soglia anzianita (giorni)',
    'ACP_ADMINHELPER_NOTIF_CLEANUP_ACTION' => 'Elimina notifiche vecchie',
    'ACP_ADMINHELPER_NOTIF_CLEANUP_CONFIRM' => 'Eliminare le notifiche non lette piu vecchie della soglia selezionata?',
    'ACP_ADMINHELPER_NOTIF_CLEANUP_SUCCESS' => 'Eliminate %1$d vecchie notifiche non lette (soglia: %2$d giorni).',
    'ACP_ADMINHELPER_LOG_NO_ROWS' => 'Nessun log di disiscrizione disponibile.',
    'ACP_ADMINHELPER_LOG_TABLE_MISSING' => 'Manca la tabella dei log disiscrizione. Esegui la migrazione di aggiornamento dell estensione.',
    'ACP_ADMINHELPER_LOG_COL_DATE' => 'Data',
    'ACP_ADMINHELPER_LOG_COL_TYPE' => 'Tipo',
    'ACP_ADMINHELPER_LOG_COL_STATUS' => 'Stato',
    'ACP_ADMINHELPER_LOG_COL_USER' => 'Utente',
    'ACP_ADMINHELPER_LOG_COL_EMAIL' => 'Email',
    'ACP_ADMINHELPER_LOG_COL_HTTP' => 'HTTP',
    'ACP_ADMINHELPER_LOG_COL_METHOD' => 'Metodo',
    'ACP_ADMINHELPER_LOG_COL_IP' => 'IP',
    'ACP_ADMINHELPER_LOG_COL_TOKEN_EXP' => 'Scadenza token',
    'ACP_ADMINHELPER_LOG_COL_UA' => 'User-Agent',
    'ACP_ADMINHELPER_LOG_COL_ACTIONS' => 'Azioni',
    'ACP_ADMINHELPER_LOG_COL_SELECT' => 'Selezione',
    'ACP_ADMINHELPER_ACTION_RESTORE_NOTIFY' => 'Ripristina notifiche',
    'ACP_ADMINHELPER_ACTION_DELETE_SELECTED' => 'Elimina selezione',
    'ACP_ADMINHELPER_ACTION_DELETE_ALL' => 'Elimina tutte le righe',
    'ACP_ADMINHELPER_ACTION_NONE' => '-',
    'ACP_ADMINHELPER_SELECT_ALL' => 'Seleziona tutto',
    'ACP_ADMINHELPER_UNSELECT_ALL' => 'Deseleziona tutto',
    'ACP_ADMINHELPER_DELETE_SELECTED_CONFIRM' => 'Eliminare le righe selezionate del log?',
    'ACP_ADMINHELPER_DELETE_ALL_CONFIRM' => 'Eliminare tutte le righe del log?',
    'ACP_ADMINHELPER_DELETE_NONE_SELECTED' => 'Nessuna riga selezionata.',
    'ACP_ADMINHELPER_DELETE_SELECTED_SUCCESS' => 'Eliminate %d righe del log.',
    'ACP_ADMINHELPER_DELETE_ALL_SUCCESS' => 'Log svuotato (%d righe eliminate).',
    'ACP_ADMINHELPER_RESTORE_NOTIFY_CONFIRM' => 'Riattivare le email di notifica forum per questo utente?',
    'ACP_ADMINHELPER_RESTORE_NOTIFY_SUCCESS' => 'Le email di notifica forum sono state riattivate per questo utente.',
    'ACP_ADMINHELPER_RESTORE_NOTIFY_INVALID_USER' => 'L utente selezionato non puo essere ripristinato.',
    'ACP_ADMINHELPER_STATUS_INVALID_REQUEST' => 'Richiesta non valida',
    'ACP_ADMINHELPER_STATUS_USER_NOT_FOUND' => 'Utente non trovato',
    'ACP_ADMINHELPER_STATUS_INVALID_SIGNATURE' => 'Firma non valida',
    'ACP_ADMINHELPER_STATUS_EXPIRED_TOKEN' => 'Token scaduto',
    'ACP_ADMINHELPER_STATUS_UNSUBSCRIBED' => 'Disiscrizione confermata',
    'ACP_ADMINHELPER_STATUS_ALREADY_UNSUBSCRIBED' => 'Gia disiscritto',
    'ACP_ADMINHELPER_STATUS_CONFIRMATION_PAGE' => 'Pagina conferma visualizzata',
    'ACP_ADMINHELPER_STATUS_ADMIN_RESTORED' => 'Notifiche ripristinate da admin',
    'ACP_ADMINHELPER_TYPE_MASSMAIL' => 'Email di massa',
    'ACP_ADMINHELPER_TYPE_FORUM_NOTIFY' => 'Notifiche forum',
]);
