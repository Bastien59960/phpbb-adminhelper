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
    'ACP_ADMINHELPER_UNSUBSCRIBE_LOGS' => 'Registros de baja',
    'ACP_ADMINHELPER_UNSUBSCRIBE_LOGS_EXPLAIN' => 'Registra las bajas one-click (RFC 8058) de los correos masivos del administrador y de las notificaciones del foro.',
    'ACP_ADMINHELPER_COUNTER_OVERVIEW' => 'Resumen global de correos',
    'ACP_ADMINHELPER_COUNTER_TOTAL_MEMBERS' => 'Miembros contados',
    'ACP_ADMINHELPER_COUNTER_MASSMAIL' => 'Correos masivos',
    'ACP_ADMINHELPER_COUNTER_FORUM_NOTIFY' => 'Notificaciones del foro (email)',
    'ACP_ADMINHELPER_COUNTER_SUBSCRIBED' => 'Suscritos',
    'ACP_ADMINHELPER_COUNTER_UNSUBSCRIBED' => 'Dados de baja',
    'ACP_ADMINHELPER_LOG_TOTAL' => 'Total de eventos',
    'ACP_ADMINHELPER_LOG_NO_ROWS' => 'Aun no hay registros de baja.',
    'ACP_ADMINHELPER_LOG_TABLE_MISSING' => 'Falta la tabla de registros de baja. Ejecuta la migracion de actualizacion de la extension.',
    'ACP_ADMINHELPER_LOG_COL_DATE' => 'Fecha',
    'ACP_ADMINHELPER_LOG_COL_TYPE' => 'Tipo',
    'ACP_ADMINHELPER_LOG_COL_STATUS' => 'Estado',
    'ACP_ADMINHELPER_LOG_COL_USER' => 'Usuario',
    'ACP_ADMINHELPER_LOG_COL_EMAIL' => 'Email',
    'ACP_ADMINHELPER_LOG_COL_HTTP' => 'HTTP',
    'ACP_ADMINHELPER_LOG_COL_METHOD' => 'Metodo',
    'ACP_ADMINHELPER_LOG_COL_IP' => 'IP',
    'ACP_ADMINHELPER_LOG_COL_TOKEN_EXP' => 'Expiracion token',
    'ACP_ADMINHELPER_LOG_COL_UA' => 'User-Agent',
    'ACP_ADMINHELPER_LOG_COL_ACTIONS' => 'Acciones',
    'ACP_ADMINHELPER_LOG_COL_SELECT' => 'Seleccion',
    'ACP_ADMINHELPER_ACTION_RESTORE_NOTIFY' => 'Restaurar notificaciones',
    'ACP_ADMINHELPER_ACTION_DELETE_SELECTED' => 'Borrar seleccion',
    'ACP_ADMINHELPER_ACTION_DELETE_ALL' => 'Borrar todas las filas',
    'ACP_ADMINHELPER_ACTION_NONE' => '-',
    'ACP_ADMINHELPER_SELECT_ALL' => 'Seleccionar todo',
    'ACP_ADMINHELPER_UNSELECT_ALL' => 'Deseleccionar todo',
    'ACP_ADMINHELPER_DELETE_SELECTED_CONFIRM' => 'Borrar las filas seleccionadas del registro?',
    'ACP_ADMINHELPER_DELETE_ALL_CONFIRM' => 'Borrar todas las filas del registro?',
    'ACP_ADMINHELPER_DELETE_NONE_SELECTED' => 'No hay filas seleccionadas.',
    'ACP_ADMINHELPER_DELETE_SELECTED_SUCCESS' => 'Se borraron %d fila(s) del registro.',
    'ACP_ADMINHELPER_DELETE_ALL_SUCCESS' => 'Registro vaciado (%d fila(s) borrada(s)).',
    'ACP_ADMINHELPER_RESTORE_NOTIFY_CONFIRM' => 'Reactivar los emails de notificaciones del foro para este usuario?',
    'ACP_ADMINHELPER_RESTORE_NOTIFY_SUCCESS' => 'Los emails de notificaciones del foro se han reactivado para este usuario.',
    'ACP_ADMINHELPER_RESTORE_NOTIFY_INVALID_USER' => 'El usuario seleccionado no se puede restaurar.',
    'ACP_ADMINHELPER_STATUS_INVALID_REQUEST' => 'Solicitud invalida',
    'ACP_ADMINHELPER_STATUS_USER_NOT_FOUND' => 'Usuario no encontrado',
    'ACP_ADMINHELPER_STATUS_INVALID_SIGNATURE' => 'Firma invalida',
    'ACP_ADMINHELPER_STATUS_EXPIRED_TOKEN' => 'Token expirado',
    'ACP_ADMINHELPER_STATUS_UNSUBSCRIBED' => 'Baja confirmada',
    'ACP_ADMINHELPER_STATUS_ALREADY_UNSUBSCRIBED' => 'Ya estaba de baja',
    'ACP_ADMINHELPER_STATUS_CONFIRMATION_PAGE' => 'Pagina de confirmacion vista',
    'ACP_ADMINHELPER_STATUS_ADMIN_RESTORED' => 'Admin restauro notificaciones',
    'ACP_ADMINHELPER_TYPE_MASSMAIL' => 'Correos masivos',
    'ACP_ADMINHELPER_TYPE_FORUM_NOTIFY' => 'Notificaciones del foro',
]);
