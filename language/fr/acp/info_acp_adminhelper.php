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
    'ACP_ADMINHELPER_UNSUBSCRIBE_LOGS' => 'Logs desinscription',
    'ACP_ADMINHELPER_UNSUBSCRIBE_LOGS_EXPLAIN' => 'Journal des desinscriptions one-click (RFC 8058) des emails de masse admin et des notifications forum.',
    'ACP_ADMINHELPER_COUNTER_OVERVIEW' => 'Apercu global des courriels',
    'ACP_ADMINHELPER_COUNTER_TOTAL_MEMBERS' => 'Membres comptes',
    'ACP_ADMINHELPER_COUNTER_MASSMAIL' => 'Emails de masse',
    'ACP_ADMINHELPER_COUNTER_FORUM_NOTIFY' => 'Notifications forum (email)',
    'ACP_ADMINHELPER_COUNTER_SUBSCRIBED' => 'Inscrits',
    'ACP_ADMINHELPER_COUNTER_UNSUBSCRIBED' => 'Desinscrits',
    'ACP_ADMINHELPER_LOG_TOTAL' => 'Total des evenements',
    'ACP_ADMINHELPER_NOTIF_CLEANUP_TITLE' => 'Nettoyage des vieilles notifications',
    'ACP_ADMINHELPER_NOTIF_CLEANUP_COUNT' => 'Notifications non lues plus anciennes que le seuil',
    'ACP_ADMINHELPER_NOTIF_CLEANUP_DAYS' => 'Seuil d anciennete (jours)',
    'ACP_ADMINHELPER_NOTIF_CLEANUP_ACTION' => 'Supprimer les vieilles notifications',
    'ACP_ADMINHELPER_NOTIF_CLEANUP_CONFIRM' => 'Supprimer les notifications non lues plus anciennes que le seuil choisi ?',
    'ACP_ADMINHELPER_NOTIF_CLEANUP_SUCCESS' => '%1$d vieille(s) notification(s) non lue(s) supprimee(s) (seuil : %2$d jours).',
    'ACP_ADMINHELPER_LOG_NO_ROWS' => 'Aucun log de desinscription pour le moment.',
    'ACP_ADMINHELPER_LOG_TABLE_MISSING' => 'La table de logs de desinscription est absente. Lancez la migration de mise a jour de l extension.',
    'ACP_ADMINHELPER_LOG_COL_DATE' => 'Date',
    'ACP_ADMINHELPER_LOG_COL_TYPE' => 'Type',
    'ACP_ADMINHELPER_LOG_COL_STATUS' => 'Statut',
    'ACP_ADMINHELPER_LOG_COL_USER' => 'Membre',
    'ACP_ADMINHELPER_LOG_COL_EMAIL' => 'Email',
    'ACP_ADMINHELPER_LOG_COL_HTTP' => 'HTTP',
    'ACP_ADMINHELPER_LOG_COL_METHOD' => 'Methode',
    'ACP_ADMINHELPER_LOG_COL_IP' => 'IP',
    'ACP_ADMINHELPER_LOG_COL_TOKEN_EXP' => 'Expiration token',
    'ACP_ADMINHELPER_LOG_COL_UA' => 'User-Agent',
    'ACP_ADMINHELPER_LOG_COL_ACTIONS' => 'Actions',
    'ACP_ADMINHELPER_LOG_COL_SELECT' => 'Selection',
    'ACP_ADMINHELPER_ACTION_RESTORE_NOTIFY' => 'Annuler',
    'ACP_ADMINHELPER_ACTION_DELETE_SELECTED' => 'Effacer la selection',
    'ACP_ADMINHELPER_ACTION_DELETE_ALL' => 'Effacer toutes les lignes',
    'ACP_ADMINHELPER_ACTION_NONE' => '-',
    'ACP_ADMINHELPER_SELECT_ALL' => 'Tout selectionner',
    'ACP_ADMINHELPER_UNSELECT_ALL' => 'Tout deselectionner',
    'ACP_ADMINHELPER_DELETE_SELECTED_CONFIRM' => 'Effacer les lignes selectionnees ?',
    'ACP_ADMINHELPER_DELETE_ALL_CONFIRM' => 'Effacer toutes les lignes du journal ?',
    'ACP_ADMINHELPER_DELETE_NONE_SELECTED' => 'Aucune ligne selectionnee.',
    'ACP_ADMINHELPER_DELETE_SELECTED_SUCCESS' => '%d ligne(s) du journal ont ete effacees.',
    'ACP_ADMINHELPER_DELETE_ALL_SUCCESS' => 'Journal vide (%d ligne(s) supprimee(s)).',
    'ACP_ADMINHELPER_RESTORE_NOTIFY_CONFIRM' => 'Reactiver les notifications forum par email pour ce membre ?',
    'ACP_ADMINHELPER_RESTORE_NOTIFY_SUCCESS' => 'Les notifications forum par email ont ete reactivees pour ce membre.',
    'ACP_ADMINHELPER_RESTORE_NOTIFY_INVALID_USER' => 'Le membre selectionne ne peut pas etre reactive.',
    'ACP_ADMINHELPER_STATUS_INVALID_REQUEST' => 'Requete invalide',
    'ACP_ADMINHELPER_STATUS_USER_NOT_FOUND' => 'Membre introuvable',
    'ACP_ADMINHELPER_STATUS_INVALID_SIGNATURE' => 'Signature invalide',
    'ACP_ADMINHELPER_STATUS_EXPIRED_TOKEN' => 'Token expire',
    'ACP_ADMINHELPER_STATUS_UNSUBSCRIBED' => 'Desinscription confirmee',
    'ACP_ADMINHELPER_STATUS_ALREADY_UNSUBSCRIBED' => 'Deja desinscrit',
    'ACP_ADMINHELPER_STATUS_CONFIRMATION_PAGE' => 'Page de confirmation affichee',
    'ACP_ADMINHELPER_STATUS_ADMIN_RESTORED' => 'Notifications reactivees par admin',
    'ACP_ADMINHELPER_TYPE_MASSMAIL' => 'Emails de masse',
    'ACP_ADMINHELPER_TYPE_FORUM_NOTIFY' => 'Notifications forum',
]);
