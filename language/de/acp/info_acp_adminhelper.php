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
    'ACP_ADMINHELPER_UNSUBSCRIBE_LOGS' => 'Abmelde-Logs',
    'ACP_ADMINHELPER_UNSUBSCRIBE_LOGS_EXPLAIN' => 'Protokolliert One-Click-Abmeldungen (RFC 8058) fuer Admin-Massenmails und Forum-Benachrichtigungs-E-Mails.',
    'ACP_ADMINHELPER_COUNTER_OVERVIEW' => 'Globale E-Mail-Uebersicht',
    'ACP_ADMINHELPER_COUNTER_TOTAL_MEMBERS' => 'Gezaehlte Mitglieder',
    'ACP_ADMINHELPER_COUNTER_MASSMAIL' => 'Massenmails',
    'ACP_ADMINHELPER_COUNTER_FORUM_NOTIFY' => 'Forum-Benachrichtigungen (E-Mail)',
    'ACP_ADMINHELPER_COUNTER_SUBSCRIBED' => 'Angemeldet',
    'ACP_ADMINHELPER_COUNTER_UNSUBSCRIBED' => 'Abgemeldet',
    'ACP_ADMINHELPER_LOG_TOTAL' => 'Gesamtzahl der Ereignisse',
    'ACP_ADMINHELPER_LOG_NO_ROWS' => 'Noch keine Abmelde-Logs vorhanden.',
    'ACP_ADMINHELPER_LOG_TABLE_MISSING' => 'Die Abmelde-Log-Tabelle fehlt. Fuehre die Erweiterungs-Migration aus.',
    'ACP_ADMINHELPER_LOG_COL_DATE' => 'Datum',
    'ACP_ADMINHELPER_LOG_COL_TYPE' => 'Typ',
    'ACP_ADMINHELPER_LOG_COL_STATUS' => 'Status',
    'ACP_ADMINHELPER_LOG_COL_USER' => 'Benutzer',
    'ACP_ADMINHELPER_LOG_COL_EMAIL' => 'E-Mail',
    'ACP_ADMINHELPER_LOG_COL_HTTP' => 'HTTP',
    'ACP_ADMINHELPER_LOG_COL_METHOD' => 'Methode',
    'ACP_ADMINHELPER_LOG_COL_IP' => 'IP',
    'ACP_ADMINHELPER_LOG_COL_TOKEN_EXP' => 'Token-Ablauf',
    'ACP_ADMINHELPER_LOG_COL_UA' => 'User-Agent',
    'ACP_ADMINHELPER_LOG_COL_ACTIONS' => 'Aktionen',
    'ACP_ADMINHELPER_ACTION_RESTORE_NOTIFY' => 'Benachrichtigungen wiederherstellen',
    'ACP_ADMINHELPER_ACTION_NONE' => '-',
    'ACP_ADMINHELPER_RESTORE_NOTIFY_CONFIRM' => 'Forum-Benachrichtigungs-E-Mails fuer diesen Benutzer wieder aktivieren?',
    'ACP_ADMINHELPER_RESTORE_NOTIFY_SUCCESS' => 'Forum-Benachrichtigungs-E-Mails wurden fuer diesen Benutzer wieder aktiviert.',
    'ACP_ADMINHELPER_RESTORE_NOTIFY_INVALID_USER' => 'Ausgewaehlter Benutzer kann nicht wiederhergestellt werden.',
    'ACP_ADMINHELPER_STATUS_INVALID_REQUEST' => 'Ungueltige Anfrage',
    'ACP_ADMINHELPER_STATUS_USER_NOT_FOUND' => 'Benutzer nicht gefunden',
    'ACP_ADMINHELPER_STATUS_INVALID_SIGNATURE' => 'Ungueltige Signatur',
    'ACP_ADMINHELPER_STATUS_EXPIRED_TOKEN' => 'Token abgelaufen',
    'ACP_ADMINHELPER_STATUS_UNSUBSCRIBED' => 'Abmeldung bestaetigt',
    'ACP_ADMINHELPER_STATUS_ALREADY_UNSUBSCRIBED' => 'Bereits abgemeldet',
    'ACP_ADMINHELPER_STATUS_CONFIRMATION_PAGE' => 'Bestaetigungsseite aufgerufen',
    'ACP_ADMINHELPER_STATUS_ADMIN_RESTORED' => 'Admin hat Benachrichtigungen wiederhergestellt',
    'ACP_ADMINHELPER_TYPE_MASSMAIL' => 'Massenmails',
    'ACP_ADMINHELPER_TYPE_FORUM_NOTIFY' => 'Forum-Benachrichtigungen',
]);
