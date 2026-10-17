<?php
/**
 * Email templates: order invoice, admin notification, status updates, password reset, marketing.
 * All styles are inline so they look right in Gmail, Outlook and phone mail apps.
 */

/** Run $fn with the interface language temporarily switched (emails go out in the customer's language). */
function with_lang(string $code, callable $fn)
{
    global $LANG, $LANG_CODE;
    $savedLang = $LANG;
    $savedCode = $LANG_CODE;
    $LANG_CODE = in_array($code, ['en', 'bn'], true) ? $code : 'en';
    $LANG = require SHIFA_ROOT . '/includes/lang/' . $LANG_CODE . '.php';
    if ($LANG_CODE !== 'en') {
        $LANG = $LANG + (require SHIFA_ROOT . '/includes/lang/en.php');
    }
    try {
        return $fn();
    } finally {
        $LANG = $savedLang;
        $LANG_CODE = $savedCode;
    }
}

function email_layout(string $heading, string $inner, string $footerExtra = ''): string
{
    $store = e(setting('store_name', 'Shifa'));
    $phone = e(setting('store_phone'));
    $addr = e(setting('store_address'));
    $site = e(full_url());
    $contact = trim(($phone ? $phone : '') . ($phone && $addr ? ' · ' : '') . ($addr ? $addr : ''));
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;padding:0;background:#fdf2f6;font-family:Arial,Helvetica,sans-serif;color:#3a2a31;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fdf2f6;padding:24px 0;"><tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #f6d5e2;">
<tr><td style="background:#f8c8dc;padding:22px 28px;">
  <a href="' . $site . '" style="text-decoration:none;color:#8a2f55;font-size:26px;font-weight:bold;letter-spacing:.5px;">' . $store . '</a>
</td></tr>
<tr><td style="padding:28px;">
  <h1 style="margin:0 0 16px;font-size:20px;color:#3a2a31;">' . e($heading) . '</h1>
  ' . $inner . '
</td></tr>
<tr><td style="background:#fff7fa;padding:18px 28px;font-size:12px;color:#7d6670;border-top:1px solid #f6d5e2;">
  <strong style="color:#8a2f55;">' . $store . '</strong>' . ($contact ? '<br>' . $contact : '') . '<br><a href="' . $site . '" style="color:#b8456f;">' . $site . '</a>' . $footerExtra . '
</td></tr>
</table></td></tr></table></body></html>';
}

function email_button(string $href, string $label): string
{
    return '<p style="margin:22px 0;"><a href="' . e($href) . '" style="display:inline-block;background:#b8456f;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:bold;">' . e($label) . '</a></p>';
}

/** Invoice block shared by the customer email, the admin email and the printable invoice page. */
function invoice_html(array $order, array $items): string
{
    $td = 'padding:8px 6px;border-bottom:1px solid #f3e1e8;font-size:14px;';
    $th = 'padding:8px 6px;border-bottom:2px solid #f6d5e2;font-size:12px;text-transform:uppercase;color:#7d6670;text-align:left;';
    $zone = $order['zone'] === 'inside' ? t('inside_dhaka') : t('outside_dhaka');
    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px;font-size:14px;">
<tr><td style="vertical-align:top;padding-right:10px;">
  <div style="font-size:12px;color:#7d6670;text-transform:uppercase;">' . e(t('invoice_no')) . '</div>
  <div style="font-size:18px;font-weight:bold;color:#8a2f55;letter-spacing:1px;">' . e($order['serial']) . '</div>
  <div style="color:#7d6670;margin-top:4px;">' . e(t('order_date')) . ': ' . e(date('d M Y, h:i A', strtotime($order['created_at']))) . '</div>
  <div style="color:#7d6670;">' . e(t('payment')) . ': ' . e(t('pay_' . $order['payment_method'])) . '</div>
</td><td style="vertical-align:top;">
  <div style="font-size:12px;color:#7d6670;text-transform:uppercase;">' . e(t('deliver_to')) . '</div>
  <div style="font-weight:bold;">' . e($order['name']) . '</div>
  <div>' . e($order['phone']) . '</div>
  <div>' . e($order['email']) . '</div>
  <div>' . nl2br(e($order['address'])) . ($order['city'] ? ', ' . e($order['city']) : '') . '</div>
  <div style="color:#7d6670;">' . e($zone) . '</div>
</td></tr></table>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
<tr><th style="' . $th . '">' . e(t('item')) . '</th><th style="' . $th . 'text-align:center;">' . e(t('qty')) . '</th><th style="' . $th . 'text-align:right;">' . e(t('price')) . '</th><th style="' . $th . 'text-align:right;">' . e(t('total')) . '</th></tr>';
    foreach ($items as $it) {
        $html .= '<tr><td style="' . $td . '">' . e($it['name']) . '</td><td style="' . $td . 'text-align:center;">' . e(num($it['quantity'])) . '</td><td style="' . $td . 'text-align:right;white-space:nowrap;">' . e(money($it['price'])) . '</td><td style="' . $td . 'text-align:right;white-space:nowrap;">' . e(money($it['line_total'])) . '</td></tr>';
    }
    $r = 'padding:6px;font-size:14px;text-align:right;';
    $html .= '<tr><td colspan="3" style="' . $r . 'padding-top:12px;">' . e(t('subtotal')) . '</td><td style="' . $r . 'padding-top:12px;white-space:nowrap;">' . e(money($order['subtotal'])) . '</td></tr>
<tr><td colspan="3" style="' . $r . '">' . e(t('delivery_charge')) . ' (' . e(num($order['total_weight'], 2)) . ' ' . e(t('kg')) . ')</td><td style="' . $r . 'white-space:nowrap;">' . e(money($order['delivery_charge'])) . '</td></tr>
<tr><td colspan="3" style="' . $r . 'font-size:16px;font-weight:bold;color:#8a2f55;">' . e(t('grand_total')) . '</td><td style="' . $r . 'font-size:16px;font-weight:bold;color:#8a2f55;white-space:nowrap;">' . e(money($order['total'])) . '</td></tr>
</table>';
    if (!empty($order['note'])) {
        $html .= '<p style="font-size:13px;color:#7d6670;margin-top:14px;"><strong>' . e(t('order_note')) . ':</strong> ' . nl2br(e($order['note'])) . '</p>';
    }
    return $html;
}

/** Sends the thank-you + invoice email to the customer and the new-order alert to the shop owner. */
function send_order_emails(int $orderId): array
{
    $order = row('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$order) {
        return [false, false];
    }
    $items = order_items($orderId);

    $customerOk = with_lang($order['lang'] ?: 'en', function () use ($order, $items) {
        $inner = '<p style="font-size:15px;line-height:1.6;">' . e(t('email_hi', ['name' => $order['name']])) . '</p>
<p style="font-size:15px;line-height:1.6;">' . e(t('email_thanks_body', ['store' => setting('store_name', 'Shifa')])) . '</p>
<p style="font-size:15px;line-height:1.6;background:#fdf2f6;border-radius:8px;padding:12px 14px;">' . e(t('email_tracking_is')) . ' <strong style="color:#8a2f55;letter-spacing:1px;">' . e($order['serial']) . '</strong></p>'
            . invoice_html($order, $items)
            . email_button(full_url('track.php?serial=' . urlencode($order['serial'])), t('track_your_order'))
            . '<p style="font-size:14px;color:#7d6670;">' . e(t('email_cod_note')) . '</p>';
        $html = email_layout(t('email_thanks_heading'), $inner);
        return send_mail($order['email'], t('email_invoice_subject', ['serial' => $order['serial'], 'store' => setting('store_name', 'Shifa')]), $html, [
            'to_name' => $order['name'],
            'reply_to' => setting('notify_email'),
        ]);
    });

    $adminOk = false;
    $notify = setting('notify_email');
    if ($notify) {
        $adminOk = with_lang('en', function () use ($order, $items, $notify) {
            $inner = '<p style="font-size:15px;">A new order was placed on your shop.</p>'
                . invoice_html($order, $items)
                . email_button(full_url('admin/order.php?id=' . $order['id']), 'Open order in admin panel');
            $html = email_layout('New order ' . $order['serial'] . ' — ' . money($order['total'], false), $inner);
            return send_mail($notify, '🛍 New order ' . $order['serial'] . ' — ' . money($order['total'], false) . ' (' . $order['name'] . ')', $html, [
                'reply_to' => $order['email'],
                'reply_name' => $order['name'],
            ]);
        });
    }
    return [$customerOk, $adminOk];
}

function send_status_email(int $orderId, string $note = ''): bool
{
    $order = row('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$order) {
        return false;
    }
    return with_lang($order['lang'] ?: 'en', function () use ($order, $note) {
        $status = status_label($order['status']);
        $inner = '<p style="font-size:15px;line-height:1.6;">' . e(t('email_hi', ['name' => $order['name']])) . '</p>
<p style="font-size:15px;line-height:1.6;">' . e(t('email_status_body', ['serial' => $order['serial']])) . '</p>
<p style="font-size:20px;font-weight:bold;color:#8a2f55;margin:10px 0;">' . e($status) . '</p>'
            . ($note !== '' ? '<p style="font-size:14px;line-height:1.6;background:#fdf2f6;border-radius:8px;padding:12px 14px;">' . nl2br(e($note)) . '</p>' : '')
            . email_button(full_url('track.php?serial=' . urlencode($order['serial'])), t('track_your_order'));
        $html = email_layout(t('email_status_heading'), $inner);
        return send_mail($order['email'], t('email_status_subject', ['serial' => $order['serial'], 'status' => $status]), $html, [
            'to_name' => $order['name'],
            'reply_to' => setting('notify_email'),
        ]);
    });
}

function send_reset_email(string $to, string $name, string $link, string $langCode = 'en'): bool
{
    return with_lang($langCode, function () use ($to, $name, $link) {
        $inner = '<p style="font-size:15px;line-height:1.6;">' . e(t('email_hi', ['name' => $name])) . '</p>
<p style="font-size:15px;line-height:1.6;">' . e(t('email_reset_body')) . '</p>'
            . email_button($link, t('reset_password'))
            . '<p style="font-size:13px;color:#7d6670;">' . e(t('email_reset_ignore')) . '</p>';
        return send_mail($to, t('email_reset_subject', ['store' => setting('store_name', 'Shifa')]), email_layout(t('reset_password'), $inner), ['to_name' => $name]);
    });
}

/** Marketing / new product email. $body is plain text written by the shop owner. */
function marketing_email_html(array $campaign, array $products, string $customerName, string $email): string
{
    $body = nl2br(e(str_replace('{name}', $customerName, $campaign['body'])));
    $inner = '<div style="font-size:15px;line-height:1.7;">' . $body . '</div>';
    if ($products) {
        $inner .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;">';
        $cells = 0;
        foreach ($products as $p) {
            if ($cells % 2 === 0) {
                $inner .= '<tr>';
            }
            $img = $p['image'] ? full_url($p['image']) : '';
            $link = full_url('product.php?slug=' . urlencode($p['slug']));
            $price = on_sale($p)
                ? '<span style="color:#b8456f;font-weight:bold;">' . e(money(effective_price($p), false)) . '</span> <span style="text-decoration:line-through;color:#a08c94;font-size:12px;">' . e(money($p['price'], false)) . '</span>'
                : '<span style="color:#b8456f;font-weight:bold;">' . e(money($p['price'], false)) . '</span>';
            $inner .= '<td width="50%" style="padding:8px;vertical-align:top;"><a href="' . e($link) . '" style="text-decoration:none;color:#3a2a31;">'
                . ($img ? '<img src="' . e($img) . '" alt="' . e($p['name']) . '" width="250" style="width:100%;max-width:250px;height:auto;border-radius:8px;display:block;margin-bottom:8px;">' : '')
                . '<div style="font-size:14px;font-weight:bold;margin-bottom:4px;">' . e($p['name']) . '</div>' . $price . '</a></td>';
            $cells++;
            if ($cells % 2 === 0) {
                $inner .= '</tr>';
            }
        }
        if ($cells % 2 !== 0) {
            $inner .= '<td></td></tr>';
        }
        $inner .= '</table>';
    }
    $inner .= email_button(full_url(), 'Visit ' . setting('store_name', 'Shifa'));
    $footer = '<br><br><span style="color:#a08c94;">You received this because you shop at ' . e(setting('store_name', 'Shifa')) . '. <a href="' . e(unsubscribe_url($email)) . '" style="color:#a08c94;">Unsubscribe</a></span>';
    return email_layout($campaign['subject'], $inner, $footer);
}
