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
    'ADMINHELPER_SEARCH_BY_EMAIL'   => 'Oder nach E-Mail-Adresse suchen',
    'ADMINHELPER_EMAIL_PLACEHOLDER' => 'z. B. benutzer@example.com',
    'ADMINHELPER_EMAIL_NOT_FOUND'   => 'Kein Benutzer mit der Adresse gefunden: %s',
    'ADMINHELPER_HTML_MESSAGE_LABEL' => 'Ihre Nachricht (HTML)',
    'ADMINHELPER_HTML_MESSAGE_EXPLAIN' => 'Fuegen Sie nur den sichtbaren Inhalt ein (z. B. beginnend mit &lt;div&gt;). &lt;!DOCTYPE html&gt;, &lt;html&gt;, &lt;head&gt; und &lt;body&gt; werden automatisch verarbeitet.',
    'ADMINHELPER_USE_HTML_LABEL' => 'HTML-Version senden',
    'ADMINHELPER_USE_HTML_EXPLAIN' => 'Wenn aktiviert, wird die E-Mail als Multipart (Klartext + HTML) versendet.',
    'ADMINHELPER_APPEND_UNSUBSCRIBE_LABEL' => 'Hinweise zur Abmeldung hinzufuegen',
    'ADMINHELPER_APPEND_UNSUBSCRIBE_EXPLAIN' => 'Fuegt eine Fusszeile mit Link zu den Profil-Einstellungen hinzu (Login erforderlich), um Admin-Rundmails zu deaktivieren.',
    'ADMINHELPER_ENABLE_ONE_CLICK_LABEL' => 'One-Click-Abmeldung aktivieren (RFC8058)',
    'ADMINHELPER_ENABLE_ONE_CLICK_EXPLAIN' => 'Fuegt List-Unsubscribe- und List-Unsubscribe-Post-Header hinzu und sendet eine Nachricht pro Empfaenger fuer RFC8058-konforme One-Click-Abmeldung.',
    'ADMINHELPER_UNSUBSCRIBE_TEXT' => 'Um keine Admin-Rundmails mehr zu erhalten, melden Sie sich an und deaktivieren Sie die entsprechende Option in Ihren Profil-Einstellungen: %s',
    'ADMINHELPER_UNSUBSCRIBE_HTML' => '<hr><p><strong>Abmeldung:</strong> Um keine Admin-Rundmails mehr zu erhalten, melden Sie sich an und deaktivieren Sie die entsprechende Option in Ihren Profil-Einstellungen: <a href="%1$s">%1$s</a></p>',
    'ADMINHELPER_NOTIFY_UNSUBSCRIBE_TEXT' => 'Um keine Forum-Benachrichtigungs-E-Mails mehr zu erhalten, nutzen Sie die One-Click-Abmeldung: %1$s' . "\n" . 'Oder melden Sie sich an und deaktivieren Sie E-Mail-Benachrichtigungen hier: %2$s',
    'ADMINHELPER_UNSUB_INVALID_REQUEST' => 'Ungueltige Abmeldeanfrage.',
    'ADMINHELPER_UNSUB_USER_NOT_FOUND' => 'Benutzer nicht gefunden.',
    'ADMINHELPER_UNSUB_INVALID_SIGNATURE' => 'Ungueltige Abmeldesignatur.',
    'ADMINHELPER_UNSUB_EXPIRED' => 'Abmeldelink abgelaufen.',
    'ADMINHELPER_UNSUB_DONE' => 'Abmeldung verarbeitet. Sie erhalten keine Admin-Massenmails mehr.',
    'ADMINHELPER_UNSUB_PAGE_TITLE' => 'E-Mail-Abmeldung',
    'ADMINHELPER_UNSUB_PAGE_HEADING' => 'Von Admin-Massenmails abmelden',
    'ADMINHELPER_UNSUB_PAGE_EXPLAIN' => 'Diese Aktion deaktiviert nur Admin-Massenmails. Ihr Forenkonto wird nicht geloescht.',
    'ADMINHELPER_UNSUB_PAGE_BUTTON' => 'Abmeldung bestaetigen',
    'ADMINHELPER_NOTIFY_UNSUB_DONE' => 'Abmeldung verarbeitet. Sie erhalten keine Forum-Benachrichtigungs-E-Mails mehr.',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_TITLE' => 'Forum-Benachrichtigungen abmelden',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_HEADING' => 'Von Forum-Benachrichtigungs-E-Mails abmelden',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_EXPLAIN' => 'Diese Aktion deaktiviert Forum-Benachrichtigungen per E-Mail. Ihr Forenkonto wird nicht geloescht.',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_BUTTON' => 'Abmeldung bestaetigen',
    'ADMINHELPER_MIME_MULTIPART_NOTICE' => 'Dies ist eine mehrteilige Nachricht im MIME-Format.',
]);
