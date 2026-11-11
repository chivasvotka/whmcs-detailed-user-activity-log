<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/user_activity_log.php';

function user_activity_log_module_params($vars)
{
    return isset($vars['params']) && is_array($vars['params']) ? $vars['params'] : $vars;
}

function user_activity_log_module_event($event, $label, $vars, $failed = false)
{
    $params = user_activity_log_module_params($vars);
    $serviceValue = $params['serviceid'] ?? ($vars['serviceid'] ?? 0);
    $clientValue = $params['userid'] ?? ($vars['userid'] ?? 0);
    $serviceId = is_numeric($serviceValue) ? (int) $serviceValue : 0;
    $clientId = is_numeric($clientValue) ? (int) $clientValue : 0;
    $module = !empty($params['moduletype']) ? $params['moduletype'] : (!empty($params['module']) ? $params['module'] : '');
    $description = 'Servis ' . $label . ($failed ? ' başarısız oldu' : ' tamamlandı');
    user_activity_log_event($event . ($failed ? '.failed' : ''), $description, array(
        'client_id' => $clientId,
        'service_id' => $serviceId,
        'entity_type' => 'service',
        'entity_id' => $serviceId,
        'metadata' => array(
            'service_id' => $serviceId,
            'module' => $module,
            'domain' => $params['domain'] ?? '',
            'product_id' => $params['pid'] ?? '',
            'failed' => (bool) $failed,
        ),
    ));
}

add_hook('UserLogin', 1, function ($vars) {
    $user = $vars['user'] ?? null;
    $userId = user_activity_log_user_id_from_object($user);
    user_activity_log_event('user.login', 'Kullanıcı hesabına giriş yaptı', array(
        'client_id' => user_activity_log_client_for_user($userId),
        'user_id' => $userId,
        'user_object' => $user,
    ));
});

add_hook('UserLogout', 1, function ($vars) {
    $user = $vars['user'] ?? null;
    $userId = user_activity_log_user_id_from_object($user);
    user_activity_log_event('user.logout', 'Kullanıcı hesabından çıkış yaptı', array(
        'client_id' => user_activity_log_client_for_user($userId) ?: user_activity_log_current_client_id(),
        'user_id' => $userId,
        'user_object' => $user,
    ));
});

add_hook('AdminLogin', 1, function ($vars) {
    user_activity_log_event('admin.login', 'Admin panele giriş yaptı', array(
        'admin_id' => !empty($vars['adminid']) ? $vars['adminid'] : user_activity_log_current_admin_id(),
    ));
});

add_hook('AdminLogout', 1, function ($vars) {
    user_activity_log_event('admin.logout', 'Admin panelden çıkış yaptı', array(
        'admin_id' => !empty($vars['adminid']) ? $vars['adminid'] : user_activity_log_current_admin_id(),
    ));
});

add_hook('ClientAdd', 1, function ($vars) {
    $clientId = !empty($vars['client_id']) ? $vars['client_id'] : ($vars['userid'] ?? 0);
    user_activity_log_event('client.created', 'Müşteri hesabı oluşturuldu', array(
        'client_id' => $clientId,
        'user_id' => $vars['user_id'] ?? 0,
        'metadata' => array('creation_source' => user_activity_log_current_admin_id() ? 'admin' : 'client_area'),
    ));
});

add_hook('ClientEdit', 1, function ($vars) {
    $clientId = $vars['userid'] ?? 0;
    $changedFields = user_activity_log_changed_fields($vars);
    user_activity_log_event('client.updated', 'Müşteri profili güncellendi', array(
        'client_id' => $clientId,
        'metadata' => array('changed_fields' => $changedFields),
    ));
    $oldData = isset($vars['olddata']) && is_array($vars['olddata']) ? $vars['olddata'] : array();
    if (array_key_exists('email_verified', $vars)
        && array_key_exists('email_verified', $oldData)
        && empty($oldData['email_verified'])
        && !empty($vars['email_verified'])) {
        user_activity_log_event('client.email_verified', 'E-posta doğrulama durumu tamamlandı', array(
            'client_id' => $clientId,
        ));
    }
});

add_hook('ClientChangePassword', 1, function ($vars) {
    user_activity_log_event('client.password_change', 'Müşteri hesabı şifresini değiştirdi', array(
        'client_id' => $vars['userid'] ?? 0,
    ));
});

add_hook('ClientClose', 1, function ($vars) {
    user_activity_log_event('client.closed', 'Müşteri hesabı kapatıldı', array('client_id' => $vars['userid'] ?? 0));
});

add_hook('PreDeleteClient', 1, function ($vars) {
    user_activity_log_event('client.deleted', 'Müşteri hesabı silinmek üzere işaretlendi', array('client_id' => $vars['userid'] ?? 0));
});

add_hook('AfterClientMerge', 1, function ($vars) {
    user_activity_log_event('client.merged', 'Müşteri hesapları birleştirildi', array(
        'client_id' => $vars['toUserID'] ?? 0,
        'metadata' => array('merged_from_client_id' => $vars['fromUserID'] ?? 0),
    ));
});

add_hook('ClientAreaPage', 1, function ($vars) {
    if (!user_activity_log_is_active() || !user_activity_log_page_views_enabled() || !user_activity_log_current_client_id()) {
        return array();
    }
    $filename = $vars['filename'] ?? ($_SERVER['SCRIPT_NAME'] ?? 'clientarea.php');
    $action = $vars['action'] ?? ($_REQUEST['action'] ?? '');
    $pageName = trim((string) $filename . ($action !== '' ? ':' . $action : ''));
    $eventKey = 'client_area.page_view';
    $entityId = isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
    if (stripos($pageName, 'profile') !== false) {
        $eventKey = 'client_area.profile_view';
    } elseif (stripos($pageName, 'productdetails') !== false || stripos($pageName, 'service') !== false) {
        $eventKey = 'client.service_view';
    }
    $context = array(
        'client_id' => user_activity_log_current_client_id(),
        'metadata' => array('filename' => $filename, 'action' => $action, 'page_title' => $vars['pagetitle'] ?? ''),
    );
    if ($eventKey === 'client.service_view' && $entityId) {
        $context['service_id'] = $entityId;
        $context['entity_type'] = 'service';
        $context['entity_id'] = $entityId;
    }
    user_activity_log_event($eventKey, 'Müşteri alanı sayfası görüntülendi: ' . $pageName, $context);
    user_activity_log_record_phone_verification(user_activity_log_current_client_id());
    return array();
});

add_hook('ClientAreaDomainDetails', 1, function ($vars) {
    if (!user_activity_log_is_active() || !user_activity_log_page_views_enabled()) {
        return;
    }
    $domain = $vars['domain'] ?? null;
    $domainId = user_activity_log_object_value($domain, 'id');
    $clientId = user_activity_log_object_value($domain, 'userid');
    if (!$clientId) {
        $clientId = user_activity_log_object_value($domain, 'userId');
    }
    if (!$clientId) {
        $clientId = user_activity_log_current_client_id();
    }
    user_activity_log_event('client.domain_view', 'Müşteri domain detayını görüntüledi', array(
        'client_id' => $clientId,
        'entity_type' => 'domain',
        'entity_id' => $domainId,
        'metadata' => array(
            'domain_id' => $domainId,
            'domain' => user_activity_log_object_value($domain, 'domain'),
        ),
    ));
});

add_hook('ViewInvoiceDetailsPage', 1, function ($vars) {
    user_activity_log_event('client.invoice_view', 'Müşteri fatura detayını görüntüledi', array(
        'invoice_id' => $vars['invoiceid'] ?? 0,
        'entity_type' => 'invoice',
        'entity_id' => $vars['invoiceid'] ?? 0,
    ));
});

add_hook('AdminAreaClientSummaryPage', 1, function ($vars) {
    $clientId = (int) ($vars['userid'] ?? 0);
    if (!$clientId || !user_activity_log_is_active()) {
        return '';
    }
    user_activity_log_event('admin.client_profile_view', 'Admin müşteri profil özetini görüntüledi', array(
        'client_id' => $clientId,
    ));
    $stats = user_activity_log_stats($clientId);
    $link = 'addonmodules.php?module=' . USER_ACTIVITY_LOG_MODULE . '&clientid=' . $clientId;
    return '<div class="panel panel-default"><div class="panel-heading"><strong>Detaylı Hareket Günlüğü</strong></div><div class="panel-body">'
        . '<p><strong>' . (int) $stats['total'] . '</strong> olay, <strong>' . (int) $stats['ips'] . '</strong> farklı IP, <strong>' . (int) $stats['sessions'] . '</strong> farklı oturum.</p>'
        . '<a class="btn btn-sm btn-primary" href="' . user_activity_log_html($link) . '">Hareket günlüğünü aç</a></div></div>';
});

add_hook('AdminAreaClientSummaryActionLinks', 1, function ($vars) {
    $clientId = (int) ($vars['userid'] ?? 0);
    if (!$clientId || !user_activity_log_is_active()) {
        return array();
    }
    return array('<a href="addonmodules.php?module=' . USER_ACTIVITY_LOG_MODULE . '&clientid=' . $clientId . '">Hareket Günlüğü</a>');
});

add_hook('AdminAreaViewTicketPage', 1, function ($vars) {
    user_activity_log_event('admin.ticket_view', 'Admin destek biletini görüntüledi', array(
        'ticket_id' => $vars['ticketid'] ?? 0,
        'entity_type' => 'ticket',
        'entity_id' => $vars['ticketid'] ?? 0,
        'admin_id' => user_activity_log_current_admin_id(),
    ));
});

add_hook('ViewOrderDetailsPage', 1, function ($vars) {
    user_activity_log_event('admin.order_view', 'Admin sipariş detayını görüntüledi', array(
        'client_id' => $vars['userid'] ?? 0,
        'order_id' => $vars['orderid'] ?? 0,
        'entity_type' => 'order',
        'entity_id' => $vars['orderid'] ?? 0,
        'metadata' => array('order_number' => $vars['ordernum'] ?? '', 'status' => $vars['status'] ?? ''),
    ));
});

add_hook('AdminServiceEdit', 1, function ($vars) {
    user_activity_log_event('service.admin_edit', 'Admin servis kaydını güncelledi', array(
        'client_id' => $vars['userid'] ?? 0,
        'service_id' => $vars['serviceid'] ?? 0,
        'entity_type' => 'service',
        'entity_id' => $vars['serviceid'] ?? 0,
    ));
});

add_hook('AfterShoppingCartCheckout', 1, function ($vars) {
    $serviceIds = isset($vars['ServiceIDs']) && is_array($vars['ServiceIDs']) ? $vars['ServiceIDs'] : array();
    user_activity_log_event('order.checkout_completed', 'Müşteri siparişi tamamlandı', array(
        'client_id' => user_activity_log_current_client_id(),
        'order_id' => $vars['OrderID'] ?? 0,
        'entity_type' => 'order',
        'entity_id' => $vars['OrderID'] ?? 0,
        'metadata' => array(
            'order_number' => $vars['OrderNumber'] ?? '',
            'service_ids' => $serviceIds,
            'addon_ids' => $vars['AddonIDs'] ?? array(),
            'domain_ids' => $vars['DomainIDs'] ?? array(),
            'invoice_id' => $vars['InvoiceID'] ?? 0,
            'payment_method' => $vars['PaymentMethod'] ?? '',
            'total_due' => $vars['TotalDue'] ?? 0,
        ),
    ));
});

add_hook('AcceptOrder', 1, function ($vars) {
    user_activity_log_event('order.accepted', 'Sipariş admin tarafından kabul edildi', array('order_id' => $vars['orderid'] ?? 0, 'entity_type' => 'order', 'entity_id' => $vars['orderid'] ?? 0));
});

add_hook('OrderPaid', 1, function ($vars) {
    user_activity_log_event('order.paid', 'Sipariş ödendi', array('order_id' => $vars['orderid'] ?? 0, 'entity_type' => 'order', 'entity_id' => $vars['orderid'] ?? 0));
});

add_hook('CancelOrder', 1, function ($vars) {
    user_activity_log_event('order.cancelled', 'Sipariş iptal edilmek üzere işaretlendi', array('order_id' => $vars['orderid'] ?? 0, 'entity_type' => 'order', 'entity_id' => $vars['orderid'] ?? 0));
});

add_hook('CancelAndRefundOrder', 1, function ($vars) {
    user_activity_log_event('order.refund_requested', 'Sipariş iptal ve iade işlemine alındı', array('order_id' => $vars['orderid'] ?? 0, 'entity_type' => 'order', 'entity_id' => $vars['orderid'] ?? 0));
});

add_hook('FraudOrder', 1, function ($vars) {
    user_activity_log_event('order.fraud', 'Sipariş fraud olarak işaretlendi', array('order_id' => $vars['orderid'] ?? 0, 'entity_type' => 'order', 'entity_id' => $vars['orderid'] ?? 0));
});

add_hook('InvoiceCreated', 1, function ($vars) {
    user_activity_log_event('invoice.created', 'Fatura oluşturuldu', array('invoice_id' => $vars['invoiceid'] ?? 0, 'entity_type' => 'invoice', 'entity_id' => $vars['invoiceid'] ?? 0));
});

add_hook('InvoicePaid', 1, function ($vars) {
    user_activity_log_event('invoice.paid', 'Fatura ödendi', array('invoice_id' => $vars['invoiceid'] ?? 0, 'entity_type' => 'invoice', 'entity_id' => $vars['invoiceid'] ?? 0));
});

add_hook('AddInvoicePayment', 1, function ($vars) {
    user_activity_log_event('invoice.payment_added', 'Faturaya ödeme işlendi', array('invoice_id' => $vars['invoiceid'] ?? 0, 'entity_type' => 'invoice', 'entity_id' => $vars['invoiceid'] ?? 0));
});

add_hook('InvoiceCancelled', 1, function ($vars) {
    user_activity_log_event('invoice.cancelled', 'Fatura iptal edildi', array('invoice_id' => $vars['invoiceid'] ?? 0, 'entity_type' => 'invoice', 'entity_id' => $vars['invoiceid'] ?? 0));
});

add_hook('InvoiceRefunded', 1, function ($vars) {
    user_activity_log_event('invoice.refunded', 'Fatura için iade işlemi yapıldı', array('invoice_id' => $vars['invoiceid'] ?? 0, 'entity_type' => 'invoice', 'entity_id' => $vars['invoiceid'] ?? 0));
});

add_hook('InvoiceUnpaid', 1, function ($vars) {
    user_activity_log_event('invoice.unpaid', 'Fatura ödenmemiş olarak işaretlendi', array('invoice_id' => $vars['invoiceid'] ?? 0, 'entity_type' => 'invoice', 'entity_id' => $vars['invoiceid'] ?? 0));
});

add_hook('AddTransaction', 1, function ($vars) {
    user_activity_log_event('transaction.created', 'Finansal işlem oluşturuldu', array(
        'client_id' => $vars['userid'] ?? 0,
        'invoice_id' => $vars['invoiceid'] ?? 0,
        'entity_type' => 'transaction',
        'entity_id' => $vars['id'] ?? 0,
        'metadata' => array('gateway' => $vars['gateway'] ?? '', 'amount_in' => $vars['amountin'] ?? 0, 'amount_out' => $vars['amountout'] ?? 0, 'currency' => $vars['currency'] ?? 0),
    ));
});

add_hook('EmailPreLog', 1, function ($vars) {
    user_activity_log_event('email.logged', 'E-posta gönderim kaydı oluşturuldu', array(
        'client_id' => $vars['userid'] ?? 0,
        'metadata' => array('message_name' => $vars['messagename'] ?? '', 'custom_type' => $vars['customtype'] ?? '', 'related_id' => $vars['relid'] ?? 0),
    ));
});

add_hook('EmailPreSend', 1, function ($vars) {
    user_activity_log_event('email.pre_send', 'E-posta gönderilmeden önce işlendi', array(
        'client_id' => $vars['userid'] ?? 0,
        'metadata' => array('message_name' => $vars['messagename'] ?? '', 'custom_type' => $vars['customtype'] ?? ''),
    ));
});

add_hook('NotificationPreSend', 1, function ($vars) {
    user_activity_log_event('notification.pre_send', 'WHMCS bildirimi gönderilmeden önce işlendi', array(
        'client_id' => $vars['userid'] ?? 0,
        'metadata' => array('notification_type' => $vars['type'] ?? ($vars['notification'] ?? '')),
    ));
});

add_hook('LogActivity', 1, function ($vars) {
    user_activity_log_event('whmcs.activity_log', 'WHMCS aktivite kaydı oluşturuldu', array(
        'client_id' => $vars['userid'] ?? 0,
        'admin_id' => $vars['adminid'] ?? 0,
        'metadata' => array('source' => 'tblactivitylog'),
    ));
});

add_hook('TicketOpen', 1, function ($vars) {
    user_activity_log_event('ticket.opened', 'Destek bileti açıldı', array('client_id' => $vars['userid'] ?? 0, 'ticket_id' => $vars['ticketid'] ?? 0, 'entity_type' => 'ticket', 'entity_id' => $vars['ticketid'] ?? 0));
});

add_hook('TicketOpenAdmin', 1, function ($vars) {
    user_activity_log_event('ticket.opened_admin', 'Admin tarafından destek bileti açıldı', array('client_id' => $vars['userid'] ?? 0, 'ticket_id' => $vars['ticketid'] ?? 0, 'entity_type' => 'ticket', 'entity_id' => $vars['ticketid'] ?? 0));
});

add_hook('TicketUserReply', 1, function ($vars) {
    user_activity_log_event('ticket.reply_user', 'Müşteri destek biletine yanıt verdi', array('client_id' => $vars['userid'] ?? 0, 'ticket_id' => $vars['ticketid'] ?? 0, 'entity_type' => 'ticket', 'entity_id' => $vars['ticketid'] ?? 0, 'metadata' => array('reply_id' => $vars['replyid'] ?? 0, 'department' => $vars['deptname'] ?? '')));
});

add_hook('TicketAdminReply', 1, function ($vars) {
    user_activity_log_event('ticket.reply_admin', 'Admin destek biletine yanıt verdi', array('client_id' => $vars['userid'] ?? 0, 'ticket_id' => $vars['ticketid'] ?? 0, 'entity_type' => 'ticket', 'entity_id' => $vars['ticketid'] ?? 0, 'admin_id' => $vars['adminid'] ?? 0));
});

add_hook('TicketStatusChange', 1, function ($vars) {
    user_activity_log_event('ticket.status_changed', 'Destek bileti durumu değiştirildi', array('ticket_id' => $vars['ticketid'] ?? 0, 'entity_type' => 'ticket', 'entity_id' => $vars['ticketid'] ?? 0, 'admin_id' => $vars['adminid'] ?? 0, 'metadata' => array('status' => $vars['status'] ?? '')));
});

add_hook('TicketClose', 1, function ($vars) {
    user_activity_log_event('ticket.closed', 'Destek bileti kapatıldı', array('ticket_id' => $vars['ticketid'] ?? 0, 'entity_type' => 'ticket', 'entity_id' => $vars['ticketid'] ?? 0));
});

add_hook('TicketDelete', 1, function ($vars) {
    user_activity_log_event('ticket.deleted', 'Destek bileti silindi', array('ticket_id' => $vars['ticketid'] ?? 0, 'entity_type' => 'ticket', 'entity_id' => $vars['ticketid'] ?? 0));
});

add_hook('TicketSubjectChange', 1, function ($vars) {
    user_activity_log_event('ticket.updated', 'Destek bileti konusu değiştirildi', array('ticket_id' => $vars['ticketid'] ?? 0, 'entity_type' => 'ticket', 'entity_id' => $vars['ticketid'] ?? 0));
});

add_hook('TicketDepartmentChange', 1, function ($vars) {
    user_activity_log_event('ticket.updated', 'Destek bileti departmanı değiştirildi', array('ticket_id' => $vars['ticketid'] ?? 0, 'entity_type' => 'ticket', 'entity_id' => $vars['ticketid'] ?? 0));
});

add_hook('TicketPriorityChange', 1, function ($vars) {
    user_activity_log_event('ticket.updated', 'Destek bileti önceliği değiştirildi', array('ticket_id' => $vars['ticketid'] ?? 0, 'entity_type' => 'ticket', 'entity_id' => $vars['ticketid'] ?? 0, 'metadata' => array('priority' => $vars['priority'] ?? '')));
});

add_hook('TicketMerge', 1, function ($vars) {
    user_activity_log_event('ticket.updated', 'Destek biletleri birleştirildi', array('ticket_id' => $vars['ticketid'] ?? ($vars['originalTicketId'] ?? 0), 'entity_type' => 'ticket', 'entity_id' => $vars['ticketid'] ?? ($vars['originalTicketId'] ?? 0)));
});

add_hook('AfterModuleCreate', 1, function ($vars) { user_activity_log_module_event('service.module_create', 'oluşturma', $vars); });
add_hook('AfterModuleCreateFailed', 1, function ($vars) { user_activity_log_module_event('service.module_create', 'oluşturma', $vars, true); });
add_hook('AfterModuleSuspend', 1, function ($vars) { user_activity_log_module_event('service.module_suspend', 'askıya alma', $vars); });
add_hook('AfterModuleSuspendFailed', 1, function ($vars) { user_activity_log_module_event('service.module_suspend', 'askıya alma', $vars, true); });
add_hook('AfterModuleUnsuspend', 1, function ($vars) { user_activity_log_module_event('service.module_unsuspend', 'askıdan çıkarma', $vars); });
add_hook('AfterModuleUnsuspendFailed', 1, function ($vars) { user_activity_log_module_event('service.module_unsuspend', 'askıdan çıkarma', $vars, true); });
add_hook('AfterModuleTerminate', 1, function ($vars) { user_activity_log_module_event('service.module_terminate', 'sonlandırma', $vars); });
add_hook('AfterModuleTerminateFailed', 1, function ($vars) { user_activity_log_module_event('service.module_terminate', 'sonlandırma', $vars, true); });
add_hook('AfterModuleChangePassword', 1, function ($vars) { user_activity_log_module_event('service.module_change_password', 'uzak şifre değiştirme', $vars); });
add_hook('AfterModuleChangePasswordFailed', 1, function ($vars) { user_activity_log_module_event('service.module_change_password', 'uzak şifre değiştirme', $vars, true); });
add_hook('AfterModuleChangePackage', 1, function ($vars) { user_activity_log_module_event('service.module_change_package', 'paket değiştirme', $vars); });
add_hook('AfterModuleChangePackageFailed', 1, function ($vars) { user_activity_log_module_event('service.module_change_package', 'paket değiştirme', $vars, true); });
add_hook('AfterModuleCustom', 1, function ($vars) { user_activity_log_module_event('service.module_custom', 'özel işlem', $vars); });
add_hook('AfterModuleCustomFailed', 1, function ($vars) { user_activity_log_module_event('service.module_custom', 'özel işlem', $vars, true); });
add_hook('AfterConfigOptionsUpgrade', 1, function ($vars) {
    user_activity_log_event('service.config_options_upgrade', 'Servis yapılandırma seçenekleri yükseltildi', array('metadata' => array('upgrade_id' => $vars['upgradeid'] ?? 0)));
});
add_hook('AfterProductUpgrade', 1, function ($vars) {
    user_activity_log_event('service.product_upgrade', 'Ürün yükseltme işlemi tamamlandı', array('metadata' => array('upgrade_id' => $vars['upgradeid'] ?? 0)));
});