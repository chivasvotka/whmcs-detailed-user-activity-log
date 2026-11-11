<?php

define('WHMCS', true);
require_once dirname(__DIR__) . '/user_activity_log.php';

$GLOBALS['user_activity_log_smoke_registered_hooks'] = array();

function add_hook($name, $priority, $callback)
{
    if (!is_callable($callback)) {
        throw new RuntimeException($name . ' callback is not callable');
    }
    $GLOBALS['user_activity_log_smoke_registered_hooks'][] = $name;
}

function user_activity_log_smoke_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

user_activity_log_smoke_assert(user_activity_log_event_group('client.updated') === 'client', 'Müşteri olayları client grubuna ayrılır');
user_activity_log_smoke_assert(user_activity_log_event_group('service.module_create') === 'service', 'Servis olayları service grubuna ayrılır');
user_activity_log_smoke_assert(user_activity_log_event_group('client.service_view') === 'service', 'Servis görüntüleme olayları service grubuna ayrılır');
user_activity_log_smoke_assert(user_activity_log_event_group('ticket.reply_user') === 'support', 'Ticket olayları support grubuna ayrılır');
user_activity_log_smoke_assert(user_activity_log_description('Şifre password=secret123 token=abc açıklaması') === 'Şifre password: [redacted] token: [redacted] açıklaması', 'Açıklamadaki hassas değerler maskelenir');

$metadata = user_activity_log_sanitize_metadata(array(
    'safe' => 'value',
    'password' => 'secret',
    'nested' => array('message' => 'ticket body'),
));
user_activity_log_smoke_assert($metadata['safe'] === 'value', 'Güvenli metadata korunur');
user_activity_log_smoke_assert($metadata['password'] === '[redacted]', 'Şifre metadata alanı maskelenir');
user_activity_log_smoke_assert($metadata['nested']['message'] === '[redacted]', 'İç içe mesaj metadata alanı maskelenir');
user_activity_log_smoke_assert(user_activity_log_sanitize_metadata(array('note' => 'token=private-value'))['note'] === 'token: [redacted]', 'Metadata metnindeki token değerleri maskelenir');
user_activity_log_smoke_assert(user_activity_log_metadata(array('event' => 'test')) === '{"event":"test"}', 'Metadata JSON olarak serileştirilir');

require_once dirname(__DIR__) . '/hooks.php';
$registeredHooks = $GLOBALS['user_activity_log_smoke_registered_hooks'];
user_activity_log_smoke_assert(count($registeredHooks) >= 50, 'WHMCS addon hook callback kayıtları yüklenir');
foreach (array('UserLogin', 'UserLogout', 'ClientEdit', 'AdminAreaClientSummaryPage', 'AfterShoppingCartCheckout', 'InvoicePaid', 'TicketUserReply', 'AfterModuleCreate') as $requiredHook) {
    user_activity_log_smoke_assert(in_array($requiredHook, $registeredHooks, true), $requiredHook . ' hook kaydı bulunur');
}

echo 'Kullanıcı hareket günlüğü smoke testleri tamamlandı.' . PHP_EOL;