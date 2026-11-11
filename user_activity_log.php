<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

const USER_ACTIVITY_LOG_MODULE = 'user_activity_log';
const USER_ACTIVITY_LOG_TABLE = 'mod_user_activity_log_events';

function user_activity_log_config()
{
    return array(
        'name' => 'Detaylı Kullanıcı Hareketleri Günlüğü',
        'description' => 'Müşteri, admin, oturum, sipariş, fatura, servis, destek ve e-posta faaliyetlerini IP ve oturum bilgisiyle kaydeder.',
        'version' => '1.0.0',
        'author' => 'Custom Module',
        'language' => 'turkish',
        'fields' => array(
            'enabled' => array(
                'FriendlyName' => 'Modül durumu',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'Faaliyet kayıtlarını ve admin hareket günlüğü ekranını etkinleştirir.',
            ),
            'pageviews' => array(
                'FriendlyName' => 'Müşteri alanı sayfa görüntülemeleri',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'Oturum açmış müşterilerin müşteri alanı sayfalarını kaydeder.',
            ),
        ),
    );
}

function user_activity_log_activate()
{
    try {
        user_activity_log_ensure_table();
        Capsule::table('tbladdonmodules')->updateOrInsert(
            array('module' => USER_ACTIVITY_LOG_MODULE, 'setting' => 'enabled'),
            array('value' => 'on')
        );
        return array(
            'status' => 'success',
            'description' => 'Kullanıcı hareket günlüğü tablosu oluşturuldu. WHMCS çekirdek dosyaları değiştirilmedi.',
        );
    } catch (Throwable $exception) {
        return array('status' => 'error', 'description' => 'Modül etkinleştirilemedi: ' . $exception->getMessage());
    }
}

function user_activity_log_deactivate()
{
    try {
        Capsule::table('tbladdonmodules')
            ->where('module', USER_ACTIVITY_LOG_MODULE)
            ->where('setting', 'enabled')
            ->update(array('value' => 'off'));
    } catch (Throwable $exception) {
        return array('status' => 'error', 'description' => 'Modül durumu güncellenemedi: ' . $exception->getMessage());
    }

    return array(
        'status' => 'success',
        'description' => 'Modül kapatıldı. Kayıtlar silinmedi; yeniden etkinleştirildiğinde görünmeye devam eder.',
    );
}

function user_activity_log_upgrade($vars)
{
    try {
        user_activity_log_ensure_table();
        return array('status' => 'success', 'description' => 'Kullanıcı hareket günlüğü veri yapısı güncellendi.');
    } catch (Throwable $exception) {
        return array('status' => 'error', 'description' => 'Güncelleme başarısız: ' . $exception->getMessage());
    }
}

function user_activity_log_ensure_table()
{
    if (!Capsule::schema()->hasTable(USER_ACTIVITY_LOG_TABLE)) {
        Capsule::schema()->create(USER_ACTIVITY_LOG_TABLE, function ($table) {
            $table->increments('id');
            $table->string('event_key', 100);
            $table->string('event_group', 40)->default('general');
            $table->string('description', 255);
            $table->unsignedInteger('client_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('admin_id')->nullable();
            $table->string('actor_type', 20)->default('system');
            $table->unsignedInteger('actor_id')->nullable();
            $table->string('entity_type', 40)->nullable();
            $table->unsignedInteger('entity_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('session_hash', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('request_method', 10)->nullable();
            $table->string('request_path', 255)->nullable();
            $table->text('metadata')->nullable();
            $table->dateTime('created_at');
            $table->index(array('client_id', 'created_at'));
            $table->index(array('event_key', 'created_at'));
            $table->index(array('ip_address', 'created_at'));
            $table->index('session_hash');
        });
    }

    $columns = array(
        'event_group' => function ($table) { $table->string('event_group', 40)->default('general'); },
        'client_id' => function ($table) { $table->unsignedInteger('client_id')->nullable(); },
        'user_id' => function ($table) { $table->unsignedInteger('user_id')->nullable(); },
        'admin_id' => function ($table) { $table->unsignedInteger('admin_id')->nullable(); },
        'actor_type' => function ($table) { $table->string('actor_type', 20)->default('system'); },
        'actor_id' => function ($table) { $table->unsignedInteger('actor_id')->nullable(); },
        'entity_type' => function ($table) { $table->string('entity_type', 40)->nullable(); },
        'entity_id' => function ($table) { $table->unsignedInteger('entity_id')->nullable(); },
        'ip_address' => function ($table) { $table->string('ip_address', 45)->nullable(); },
        'session_hash' => function ($table) { $table->string('session_hash', 64)->nullable(); },
        'user_agent' => function ($table) { $table->string('user_agent', 500)->nullable(); },
        'request_method' => function ($table) { $table->string('request_method', 10)->nullable(); },
        'request_path' => function ($table) { $table->string('request_path', 255)->nullable(); },
        'metadata' => function ($table) { $table->text('metadata')->nullable(); },
        'created_at' => function ($table) { $table->dateTime('created_at')->nullable(); },
    );

    foreach ($columns as $column => $definition) {
        if (!Capsule::schema()->hasColumn(USER_ACTIVITY_LOG_TABLE, $column)) {
            Capsule::schema()->table(USER_ACTIVITY_LOG_TABLE, $definition);
        }
    }
}

function user_activity_log_is_active()
{
    static $active;
    if ($active !== null) {
        return $active;
    }

    try {
        $value = Capsule::table('tbladdonmodules')
            ->where('module', USER_ACTIVITY_LOG_MODULE)
            ->where('setting', 'enabled')
            ->value('value');
        $active = $value !== null && strtolower((string) $value) !== 'off';
    } catch (Throwable $exception) {
        $active = false;
    }

    return $active;
}

function user_activity_log_setting($setting, $default = '')
{
    try {
        $value = Capsule::table('tbladdonmodules')
            ->where('module', USER_ACTIVITY_LOG_MODULE)
            ->where('setting', $setting)
            ->value('value');
        return $value === null ? $default : (string) $value;
    } catch (Throwable $exception) {
        return $default;
    }
}

function user_activity_log_page_views_enabled()
{
    return strtolower(user_activity_log_setting('pageviews', 'on')) !== 'off';
}

function user_activity_log_current_client_id()
{
    if (!empty($_SESSION['uid']) && is_numeric($_SESSION['uid'])) {
        return (int) $_SESSION['uid'];
    }
    return 0;
}

function user_activity_log_current_admin_id()
{
    if (!empty($_SESSION['adminid']) && is_numeric($_SESSION['adminid'])) {
        return (int) $_SESSION['adminid'];
    }
    return 0;
}

function user_activity_log_current_user_id()
{
    // WHMCS uses uid for the logged-in client context in many releases. The
    // authenticated user ID is only recorded when a hook provides it.
    return 0;
}

function user_activity_log_object_value($object, $key)
{
    if (!is_object($object)) {
        return null;
    }
    try {
        if (method_exists($object, 'getAttribute')) {
            $value = $object->getAttribute($key);
            if ($value !== null) {
                return $value;
            }
        }
        if (method_exists($object, 'get' . ucfirst($key))) {
            return $object->{'get' . ucfirst($key)}();
        }
        if (isset($object->{$key})) {
            return $object->{$key};
        }
    } catch (Throwable $exception) {
        return null;
    }
    return null;
}

function user_activity_log_user_id_from_object($user)
{
    $value = user_activity_log_object_value($user, 'id');
    return is_numeric($value) ? (int) $value : 0;
}

function user_activity_log_client_for_user($userId)
{
    if (!$userId) {
        return 0;
    }
    try {
        $clientId = Capsule::table('tblusers_clients')
            ->where('auth_user_id', (int) $userId)
            ->orderBy('owner', 'desc')
            ->value('client_id');
        return $clientId ? (int) $clientId : 0;
    } catch (Throwable $exception) {
        return 0;
    }
}

function user_activity_log_user_for_client($clientId)
{
    if (!$clientId) {
        return 0;
    }
    try {
        $userId = Capsule::table('tblusers_clients')
            ->where('client_id', (int) $clientId)
            ->orderBy('owner', 'desc')
            ->value('auth_user_id');
        return $userId ? (int) $userId : 0;
    } catch (Throwable $exception) {
        return 0;
    }
}

function user_activity_log_related_client_id($context)
{
    $directKeys = array('client_id', 'userid');
    foreach ($directKeys as $key) {
        if (isset($context[$key]) && is_numeric($context[$key]) && (int) $context[$key] > 0) {
            return (int) $context[$key];
        }
    }

    if (!empty($context['user_object'])) {
        $clientId = user_activity_log_client_for_user(user_activity_log_user_id_from_object($context['user_object']));
        if ($clientId) {
            return $clientId;
        }
    }

    if (!empty($context['user_id']) && is_numeric($context['user_id'])) {
        $clientId = user_activity_log_client_for_user((int) $context['user_id']);
        if ($clientId) {
            return $clientId;
        }
    }

    $lookups = array(
        'service_id' => array('table' => 'tblhosting', 'column' => 'id', 'result' => 'userid'),
        'order_id' => array('table' => 'tblorders', 'column' => 'id', 'result' => 'userid'),
        'invoice_id' => array('table' => 'tblinvoices', 'column' => 'id', 'result' => 'userid'),
        'ticket_id' => array('table' => 'tbltickets', 'column' => 'id', 'result' => 'userid'),
        'quote_id' => array('table' => 'tblquotes', 'column' => 'id', 'result' => 'userid'),
    );

    foreach ($lookups as $contextKey => $lookup) {
        if (empty($context[$contextKey]) || !is_numeric($context[$contextKey])) {
            continue;
        }
        try {
            $clientId = Capsule::table($lookup['table'])
                ->where($lookup['column'], (int) $context[$contextKey])
                ->value($lookup['result']);
            if ($clientId) {
                return (int) $clientId;
            }
        } catch (Throwable $exception) {
            continue;
        }
    }

    return user_activity_log_current_client_id();
}

function user_activity_log_ip_address()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? substr($ip, 0, 45) : null;
}

function user_activity_log_session_hash()
{
    $sessionId = function_exists('session_id') ? session_id() : '';
    if ($sessionId === '') {
        return null;
    }
    $key = isset($GLOBALS['cc_encryption_hash']) ? (string) $GLOBALS['cc_encryption_hash'] : 'user-activity-log';
    return hash_hmac('sha256', $sessionId, $key);
}

function user_activity_log_request_path()
{
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if ($uri === '') {
        return null;
    }
    $path = parse_url($uri, PHP_URL_PATH);
    return $path ? substr((string) $path, 0, 255) : null;
}

function user_activity_log_actor($context, $clientId)
{
    $adminId = !empty($context['admin_id']) && is_numeric($context['admin_id'])
        ? (int) $context['admin_id']
        : user_activity_log_current_admin_id();
    if ($adminId) {
        return array('type' => 'admin', 'id' => $adminId, 'admin_id' => $adminId);
    }

    $userId = !empty($context['user_id']) && is_numeric($context['user_id'])
        ? (int) $context['user_id']
        : user_activity_log_current_user_id();
    if ($userId) {
        return array('type' => 'client', 'id' => $userId, 'user_id' => $userId);
    }

    if ($clientId) {
        return array('type' => 'client', 'id' => $clientId, 'user_id' => null);
    }

    return array('type' => 'system', 'id' => null, 'user_id' => null);
}

function user_activity_log_sensitive_key($key)
{
    return (bool) preg_match('/password|pass|secret|token|otp|code|card|cvv|cvc|authdata|private|body|message|attachment|raw|credential/i', (string) $key);
}

function user_activity_log_sanitize_metadata($value, $key = '', $depth = 0)
{
    if ($depth > 2 || user_activity_log_sensitive_key($key)) {
        return '[redacted]';
    }
    if (is_null($value) || is_bool($value) || is_numeric($value)) {
        return $value;
    }
    if (is_string($value)) {
        $value = preg_replace('/(password|pass|token|secret|otp|code|cvv|cvc)\s*[:=]\s*[^,; ]+/i', '$1: [redacted]', $value);
        return substr(preg_replace('/\s+/', ' ', trim($value)), 0, 300);
    }
    if (is_object($value)) {
        return '[object]';
    }
    if (!is_array($value)) {
        return '[unsupported]';
    }

    $result = array();
    $count = 0;
    foreach ($value as $childKey => $childValue) {
        if ($count++ >= 25) {
            break;
        }
        $result[(string) $childKey] = user_activity_log_sanitize_metadata($childValue, (string) $childKey, $depth + 1);
    }
    return $result;
}

function user_activity_log_metadata($metadata)
{
    if (!$metadata) {
        return null;
    }
    $safe = user_activity_log_sanitize_metadata($metadata);
    $json = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json ? substr($json, 0, 5000) : null;
}

function user_activity_log_description($description)
{
    $description = (string) $description;
    $description = preg_replace('/(password|pass|token|secret|otp|code|cvv|cvc)\s*[:=]\s*[^,; ]+/i', '$1: [redacted]', $description);
    return substr(preg_replace('/\s+/', ' ', trim($description)), 0, 255);
}

function user_activity_log_record($eventKey, $description, $context = array())
{
    if (!user_activity_log_is_active()) {
        return false;
    }

    try {
        if (!Capsule::schema()->hasTable(USER_ACTIVITY_LOG_TABLE)) {
            user_activity_log_ensure_table();
        }

        $clientId = !empty($context['client_id']) && is_numeric($context['client_id'])
            ? (int) $context['client_id']
            : user_activity_log_related_client_id($context);
        if (empty($context['user_id']) && empty($context['admin_id'])) {
            $currentClientId = user_activity_log_current_client_id();
            if ($clientId && $currentClientId === $clientId) {
                $context['user_id'] = user_activity_log_user_for_client($clientId);
            }
        }
        $actor = user_activity_log_actor($context, $clientId);
        $adminId = !empty($context['admin_id']) && is_numeric($context['admin_id'])
            ? (int) $context['admin_id']
            : ($actor['type'] === 'admin' ? (int) $actor['id'] : null);
        $userId = !empty($context['user_id']) && is_numeric($context['user_id'])
            ? (int) $context['user_id']
            : null;

        $row = array(
            'event_key' => substr((string) $eventKey, 0, 100),
            'event_group' => substr((string) ($context['event_group'] ?? 'general'), 0, 40),
            'description' => user_activity_log_description($description),
            'client_id' => $clientId ?: null,
            'user_id' => $userId ?: null,
            'admin_id' => $adminId ?: null,
            'actor_type' => $actor['type'],
            'actor_id' => !empty($actor['id']) ? (int) $actor['id'] : null,
            'entity_type' => !empty($context['entity_type']) ? substr((string) $context['entity_type'], 0, 40) : null,
            'entity_id' => !empty($context['entity_id']) && is_numeric($context['entity_id']) ? (int) $context['entity_id'] : null,
            'ip_address' => user_activity_log_ip_address(),
            'session_hash' => user_activity_log_session_hash(),
            'user_agent' => !empty($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
            'request_method' => !empty($_SERVER['REQUEST_METHOD']) ? substr((string) $_SERVER['REQUEST_METHOD'], 0, 10) : null,
            'request_path' => user_activity_log_request_path(),
            'metadata' => user_activity_log_metadata($context['metadata'] ?? array()),
            'created_at' => date('Y-m-d H:i:s'),
        );

        Capsule::table(USER_ACTIVITY_LOG_TABLE)->insert($row);
        return true;
    } catch (Throwable $exception) {
        return false;
    }
}

function user_activity_log_changed_fields($vars)
{
    $fields = array();
    $oldData = isset($vars['olddata']) && is_array($vars['olddata']) ? $vars['olddata'] : array();
    $safeFields = array('firstname', 'lastname', 'companyname', 'email', 'address1', 'address2', 'city', 'state', 'postcode', 'country', 'phonenumber', 'currency', 'status', 'language', 'groupid', 'email_verified', 'allow_sso', 'taxexempt', 'emailoptout', 'marketing_emails_opt_in');
    foreach ($safeFields as $field) {
        if (array_key_exists($field, $vars) && array_key_exists($field, $oldData) && (string) $vars[$field] !== (string) $oldData[$field]) {
            $fields[] = $field;
        }
    }
    return $fields;
}

function user_activity_log_event_group($eventKey)
{
    $groups = array(
        'auth' => array('user.login', 'user.logout', 'admin.login', 'admin.logout', 'client.password_change', 'client.email_verified', 'client.phone_verified'),
        'client' => array('client.created', 'client.updated', 'client.closed', 'client.deleted', 'client.merged'),
        'page' => array('client_area.page_view', 'client_area.profile_view', 'admin.client_profile_view', 'admin.order_view', 'admin.ticket_view', 'client.invoice_view', 'client.domain_view'),
        'order' => array('order.checkout_completed', 'order.accepted', 'order.paid', 'order.cancelled', 'order.refund_requested', 'order.fraud'),
        'billing' => array('invoice.created', 'invoice.paid', 'invoice.payment_added', 'invoice.cancelled', 'invoice.refunded', 'invoice.unpaid', 'transaction.created'),
        'service' => array('client.service_view', 'service.module_' . 'create', 'service.module_' . 'suspend', 'service.module_' . 'unsuspend', 'service.module_' . 'terminate', 'service.module_' . 'change_password', 'service.module_' . 'change_package', 'service.module_custom'),
        'support' => array('ticket.opened', 'ticket.reply_user', 'ticket.reply_admin', 'ticket.status_changed', 'ticket.closed', 'ticket.deleted', 'ticket.updated'),
        'notification' => array('email.pre_send', 'email.logged', 'notification.pre_send'),
    );
    foreach ($groups as $group => $keys) {
        if (in_array($eventKey, $keys, true)) {
            return $group;
        }
    }
    if (strpos($eventKey, 'service.') === 0) {
        return 'service';
    }
    if (strpos($eventKey, 'ticket.') === 0) {
        return 'support';
    }
    return 'general';
}

function user_activity_log_record_phone_verification($clientId)
{
    if (!$clientId || !user_activity_log_is_active() || !function_exists('session_start')) {
        return;
    }

    try {
        if (!Capsule::schema()->hasTable('mod_phone_verification_clients')) {
            return;
        }
        $record = Capsule::table('mod_phone_verification_clients')
            ->where('client_id', (int) $clientId)
            ->first(array('id', 'phone_last4', 'method', 'verified_at'));
        if (!$record || empty($record->verified_at)) {
            return;
        }
        $marker = (string) $record->id . ':' . (string) $record->verified_at;
        if (!empty($_SESSION['user_activity_log_phone_verified']) && $_SESSION['user_activity_log_phone_verified'] === $marker) {
            return;
        }
        $_SESSION['user_activity_log_phone_verified'] = $marker;
        user_activity_log_event('client.phone_verified', 'Müşteri telefon numarasını doğruladı', array(
            'client_id' => $clientId,
            'metadata' => array(
                'method' => $record->method,
                'phone_last4' => $record->phone_last4,
                'verified_at' => $record->verified_at,
            ),
        ));
    } catch (Throwable $exception) {
        return;
    }
}

function user_activity_log_event($eventKey, $description, $context = array())
{
    if (empty($context['event_group'])) {
        $context['event_group'] = user_activity_log_event_group($eventKey);
    }
    return user_activity_log_record($eventKey, $description, $context);
}

function user_activity_log_client_summary($clientId)
{
    try {
        $client = Capsule::table('tblclients')
            ->where('id', (int) $clientId)
            ->first(array('id', 'firstname', 'lastname', 'email', 'datecreated', 'status'));
        return $client ?: null;
    } catch (Throwable $exception) {
        return null;
    }
}

function user_activity_log_stats($clientId)
{
    $stats = array('total' => 0, 'ips' => 0, 'sessions' => 0, 'first' => null, 'last' => null);
    try {
        $query = Capsule::table(USER_ACTIVITY_LOG_TABLE);
        if ($clientId) {
            $query->where('client_id', (int) $clientId);
        }
        $stats['total'] = (int) $query->count();
        $stats['ips'] = (int) (clone $query)->whereNotNull('ip_address')->distinct()->count('ip_address');
        $stats['sessions'] = (int) (clone $query)->whereNotNull('session_hash')->distinct()->count('session_hash');
        $stats['first'] = (clone $query)->orderBy('created_at', 'asc')->value('created_at');
        $stats['last'] = (clone $query)->orderBy('created_at', 'desc')->value('created_at');
    } catch (Throwable $exception) {
        return $stats;
    }
    return $stats;
}

function user_activity_log_ensure_registration_baseline($client)
{
    if (!$client || empty($client->id) || empty($client->datecreated)) {
        return;
    }
    try {
        $exists = Capsule::table(USER_ACTIVITY_LOG_TABLE)
            ->where('client_id', (int) $client->id)
            ->where('event_key', 'client.registration_baseline')
            ->exists();
        if ($exists) {
            return;
        }
        Capsule::table(USER_ACTIVITY_LOG_TABLE)->insert(array(
            'event_key' => 'client.registration_baseline',
            'event_group' => 'client',
            'description' => 'Müşteri kayıt tarihi (geçmiş faaliyet kaydı değildir)',
            'client_id' => (int) $client->id,
            'user_id' => null,
            'admin_id' => null,
            'actor_type' => 'system',
            'actor_id' => null,
            'entity_type' => 'client',
            'entity_id' => (int) $client->id,
            'ip_address' => null,
            'session_hash' => null,
            'user_agent' => null,
            'request_method' => null,
            'request_path' => null,
            'metadata' => json_encode(array('baseline' => true, 'source' => 'tblclients.datecreated')),
            'created_at' => $client->datecreated,
        ));
    } catch (Throwable $exception) {
        return;
    }
}

function user_activity_log_output($vars)
{
    user_activity_log_ensure_table();
    $moduleLink = $vars['modulelink'] ?? 'addonmodules.php?module=' . USER_ACTIVITY_LOG_MODULE;
    $clientId = !empty($_GET['clientid']) && is_numeric($_GET['clientid']) ? (int) $_GET['clientid'] : 0;
    $page = !empty($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $perPage = 50;
    $event = isset($_GET['event']) ? trim((string) $_GET['event']) : '';
    $ip = isset($_GET['ip']) ? trim((string) $_GET['ip']) : '';
    $from = isset($_GET['from']) ? trim((string) $_GET['from']) : '';
    $to = isset($_GET['to']) ? trim((string) $_GET['to']) : '';
    $client = $clientId ? user_activity_log_client_summary($clientId) : null;
    user_activity_log_ensure_registration_baseline($client);

    $query = Capsule::table(USER_ACTIVITY_LOG_TABLE);
    if ($clientId) {
        $query->where('client_id', $clientId);
    }
    if ($event !== '') {
        $query->where('event_key', 'like', '%' . str_replace(array('%', '_'), array('\\%', '\\_'), $event) . '%');
    }
    if ($ip !== '') {
        $query->where('ip_address', 'like', '%' . str_replace(array('%', '_'), array('\\%', '\\_'), $ip) . '%');
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $query->where('created_at', '>=', $from . ' 00:00:00');
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $query->where('created_at', '<=', $to . ' 23:59:59');
    }
    $total = (int) (clone $query)->count();
    $rows = $query->orderBy('created_at', 'desc')->forPage($page, $perPage)->get();
    $lastPage = max(1, (int) ceil($total / $perPage));
    $stats = user_activity_log_stats($clientId);

    echo '<div class="panel panel-default"><div class="panel-heading"><strong>Detaylı Kullanıcı Hareketleri Günlüğü</strong></div><div class="panel-body">';
    echo '<p class="text-muted">WHMCS çekirdek dosyalarına dokunmadan, desteklenen hook olaylarından oluşturulan bağımsız audit günlüğü.</p>';
    echo '<form method="get" class="form-inline" style="margin-bottom:15px"><input type="hidden" name="module" value="' . user_activity_log_html(USER_ACTIVITY_LOG_MODULE) . '">';
    echo '<label style="margin-right:6px">Müşteri ID</label><input class="form-control" style="width:100px;margin-right:10px" type="number" min="1" name="clientid" value="' . (int) $clientId . '">';
    echo '<label style="margin-right:6px">Olay</label><input class="form-control" style="width:190px;margin-right:10px" name="event" value="' . user_activity_log_html($event) . '" placeholder="client.updated">';
    echo '<label style="margin-right:6px">IP</label><input class="form-control" style="width:150px;margin-right:10px" name="ip" value="' . user_activity_log_html($ip) . '">';
    echo '<label style="margin-right:6px">Başlangıç</label><input class="form-control" style="width:145px;margin-right:10px" type="date" name="from" value="' . user_activity_log_html($from) . '">';
    echo '<label style="margin-right:6px">Bitiş</label><input class="form-control" style="width:145px;margin-right:10px" type="date" name="to" value="' . user_activity_log_html($to) . '">';
    echo '<button class="btn btn-primary" type="submit">Filtrele</button> <a class="btn btn-default" href="' . user_activity_log_html($moduleLink) . '">Temizle</a></form>';

    if ($client) {
        echo '<div class="alert alert-info"><strong>Müşteri #' . (int) $client->id . '</strong> ' . user_activity_log_html(trim($client->firstname . ' ' . $client->lastname)) . ' &lt;' . user_activity_log_html($client->email) . '&gt;';
        echo ' <span class="label label-default">' . user_activity_log_html($client->status) . '</span> <span class="text-muted">Kayıt: ' . user_activity_log_html($client->datecreated) . '</span></div>';
    } elseif ($clientId) {
        echo '<div class="alert alert-warning">Müşteri bulunamadı. Silinmiş müşteri kayıtları için müşteri filtresini kaldırıp ID ile arama yapabilirsiniz.</div>';
    }

    echo '<div class="row"><div class="col-sm-3"><div class="well well-sm"><strong>' . (int) $stats['total'] . '</strong><br>Toplam olay</div></div><div class="col-sm-3"><div class="well well-sm"><strong>' . (int) $stats['ips'] . '</strong><br>Farklı IP</div></div><div class="col-sm-3"><div class="well well-sm"><strong>' . (int) $stats['sessions'] . '</strong><br>Farklı oturum</div></div><div class="col-sm-3"><div class="well well-sm"><strong>' . user_activity_log_html($stats['last'] ?: '-') . '</strong><br>Son kayıt</div></div></div>';
    echo '<div class="table-responsive"><table class="table table-striped table-hover"><thead><tr><th>Tarih</th><th>Olay</th><th>Açıklama</th><th>Aktör</th><th>IP</th><th>Oturum</th><th>İlgili kayıt</th><th>Detay</th></tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="8" class="text-muted">Bu filtrelerle kayıt bulunamadı.</td></tr>';
    }
    foreach ($rows as $row) {
        $metadata = $row->metadata ? json_decode($row->metadata, true) : array();
        $related = $row->entity_type && $row->entity_id ? $row->entity_type . ' #' . $row->entity_id : '-';
        $actor = $row->actor_type . ($row->actor_id ? ' #' . $row->actor_id : '');
        echo '<tr><td style="white-space:nowrap">' . user_activity_log_html($row->created_at) . '</td><td><code>' . user_activity_log_html($row->event_key) . '</code><br><span class="label label-info">' . user_activity_log_html($row->event_group) . '</span></td><td>' . user_activity_log_html($row->description) . '</td><td>' . user_activity_log_html($actor) . '</td><td>' . user_activity_log_html($row->ip_address ?: '-') . '</td><td><code>' . user_activity_log_html($row->session_hash ? substr($row->session_hash, 0, 12) : '-') . '</code></td><td>' . user_activity_log_html($related) . '</td><td><button type="button" class="btn btn-xs btn-default" data-toggle="collapse" data-target="#activity-' . (int) $row->id . '">Aç</button></td></tr>';
        echo '<tr id="activity-' . (int) $row->id . '" class="collapse"><td colspan="8"><strong>İstek:</strong> ' . user_activity_log_html(($row->request_method ?: '-') . ' ' . ($row->request_path ?: '-')) . '<br><strong>User-Agent:</strong> ' . user_activity_log_html($row->user_agent ?: '-') . '<br><strong>Metadata:</strong> <pre style="max-height:180px;overflow:auto">' . user_activity_log_html($metadata ? json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '-') . '</pre></td></tr>';
    }
    echo '</tbody></table></div>';
    if ($lastPage > 1) {
        echo '<ul class="pagination">';
        for ($index = 1; $index <= $lastPage; $index++) {
            $params = $_GET;
            unset($params['module']);
            $params['page'] = $index;
            $class = $index === $page ? ' class="active"' : '';
            echo '<li' . $class . '><a href="' . user_activity_log_html('addonmodules.php?module=' . USER_ACTIVITY_LOG_MODULE . '&' . http_build_query($params)) . '">' . $index . '</a></li>';
        }
        echo '</ul>';
    }
    echo '</div></div>';
}

function user_activity_log_html($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}