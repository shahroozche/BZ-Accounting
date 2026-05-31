<?php
/**
 * Plugin Name: BZ-Accounting
 * Description: افزونه حسابداری پیشرفته وردپرس - ویرایش دستی قیمت خرید، کارمزد درگاه، شرکا
 * Version: 1.51
 * Author: Shahrooz Chegini
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BZC_VERSION', '1.51');
define('BZC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BZC_PLUGIN_DIR', plugin_dir_path(__FILE__));

// افزودن منوی ادمین
add_action('admin_menu', 'bzc_add_admin_menu');
function bzc_add_admin_menu() {
    add_menu_page(
        'BZ-Accounting',
        'BZ-Accounting',
        'manage_options',
        'bz-accounting',
        'bzc_render_main_page',
        'dashicons-chart-area',
        25
    );
    add_submenu_page(
        'bz-accounting',
        'تنظیمات',
        'تنظیمات',
        'manage_options',
        'bz-accounting-settings',
        'bzc_render_settings_page'
    );
}

// بارگذاری JS و CSS
add_action('admin_enqueue_scripts', 'bzc_enqueue_assets');
function bzc_enqueue_assets($hook) {
    if (strpos($hook, 'bz-accounting') === false) {
        return;
    }
    wp_enqueue_style('bzc-styles', BZC_PLUGIN_URL . 'styles.css', [], BZC_VERSION);
    wp_enqueue_script('bzc-scripts', BZC_PLUGIN_URL . 'scripts.js', ['jquery'], BZC_VERSION, true);
    wp_localize_script('bzc-scripts', 'bzc_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bzc_nonce')
    ]);
}

// صفحه اصلی
function bzc_render_main_page() {
    ?>
    <div class="wrap bzc-wrap">
        <h1>BZ-Accounting - داشبورد حسابداری</h1>
        <div class="bzc-filters">
            <select id="bzc-year">
                <option value="2023">2023</option>
                <option value="2024">2024</option>
                <option value="2025" selected>2025</option>
                <option value="2026">2026</option>
            </select>
            <select id="bzc-month">
                <option value="1">ژانویه</option><option value="2">فوریه</option><option value="3">مارس</option>
                <option value="4">آوریل</option><option value="5">می</option><option value="6">ژوئن</option>
                <option value="7">جولای</option><option value="8">آگوست</option><option value="9">سپتامبر</option>
                <option value="10">اکتبر</option><option value="11">نوامبر</option><option value="12" selected>دسامبر</option>
            </select>
            <input type="text" id="bzc-search" placeholder="جستجو در سفارش‌ها (شماره/محصول)...">
            <button id="bzc-refresh" class="button button-primary">🔄 نمایش سفارش‌ها</button>
            <button id="bzc-save-all-prices" class="button button-primary">💾 ذخیره همه قیمت‌ها</button>
            <button id="bzc-export-csv" class="button button-primary">📥 خروجی CSV</button>
        </div>
        <div id="bzc-orders-table-container">
            <div class="loading">لطفاً روی دکمه "نمایش سفارش‌ها" کلیک کنید...</div>
        </div>
        <div id="bzc-summary"></div>
        <div id="bzc-partners-share"></div>
    </div>
    <?php
}

// صفحه تنظیمات
function bzc_render_settings_page() {
    // ذخیره شرکا
    if (isset($_POST['bzc_save_partners']) && check_admin_referer('bzc_settings')) {
        $partners = [];
        if (isset($_POST['partner_name']) && is_array($_POST['partner_name'])) {
            foreach ($_POST['partner_name'] as $i => $name) {
                if (!empty($name)) {
                    $partners[] = [
                        'name' => sanitize_text_field($name),
                        'percent' => floatval($_POST['partner_percent'][$i]),
                        'fee_percent' => isset($_POST['partner_fee_percent'][$i]) && $_POST['partner_fee_percent'][$i] !== '' ? floatval($_POST['partner_fee_percent'][$i]) : null
                    ];
                }
            }
        }
        update_option('bzc_partners', $partners);
        echo '<div class="notice notice-success"><p>شرکا با موفقیت ذخیره شدند.</p></div>';
    }

    // ذخیره کارمزد درگاه‌ها
    if (isset($_POST['bzc_save_gateways']) && check_admin_referer('bzc_settings')) {
        $gateways_fee = [];
        
        if (isset($_POST['gateway_id']) && is_array($_POST['gateway_id'])) {
            foreach ($_POST['gateway_id'] as $index => $gateway_id) {
                $type = $_POST['gateway_fee_type'][$gateway_id] ?? 'percent';
                
                if ($type == 'percent') {
                    $gateways_fee[$gateway_id] = [
                        'type' => 'percent',
                        'value' => floatval($_POST['gateway_percent'][$gateway_id] ?? 0)
                    ];
                } 
                elseif ($type == 'fixed') {
                    $gateways_fee[$gateway_id] = [
                        'type' => 'fixed',
                        'value' => floatval($_POST['gateway_fixed'][$gateway_id] ?? 0)
                    ];
                }
                elseif ($type == 'equation') {
                    $ranges = [];
                    if (isset($_POST['gateway_ranges'][$gateway_id]) && is_array($_POST['gateway_ranges'][$gateway_id])) {
                        foreach ($_POST['gateway_ranges'][$gateway_id] as $range) {
                            if (!empty($range['value']) || $range['value'] == '0') {
                                $ranges[] = [
                                    'min' => !empty($range['min']) ? floatval($range['min']) : 0,
                                    'max' => (!empty($range['max']) || $range['max'] == '0') ? floatval($range['max']) : '',
                                    'value_type' => $range['value_type'],
                                    'value' => floatval($range['value'])
                                ];
                            }
                        }
                    }
                    $gateways_fee[$gateway_id] = [
                        'type' => 'equation',
                        'ranges' => $ranges
                    ];
                }
            }
        }
        
        update_option('bzc_gateways_fee', $gateways_fee);
        echo '<div class="notice notice-success"><p>کارمزد درگاه‌ها با موفقیت ذخیره شد.</p></div>';
    }

    $partners = get_option('bzc_partners', []);
    $gateways_fee = get_option('bzc_gateways_fee', []);
    $active_gateways = bzc_get_active_payment_gateways();
    ?>
    <div class="wrap bzc-settings-wrap">
        <h1>تنظیمات BZ-Accounting</h1>
        
        <form method="post">
            <?php wp_nonce_field('bzc_settings'); ?>
            <h2>👥 مدیریت شرکا</h2>
            <p><small>درصد سود: درصد از سود ناخالص | درصد کارمزد: درصد از کارمزد درگاه (خالی = مساوی)</small></p>
            <div id="partners-list">
                <?php foreach ($partners as $idx => $partner): ?>
                    <div class="partner-row">
                        <input type="text" name="partner_name[]" value="<?php echo esc_attr($partner['name']); ?>" placeholder="نام شریک" style="width: 150px;">
                        <input type="number" step="0.1" name="partner_percent[]" value="<?php echo esc_attr($partner['percent']); ?>" placeholder="درصد سود" style="width: 100px;">
                        <input type="number" step="0.1" name="partner_fee_percent[]" value="<?php echo isset($partner['fee_percent']) ? esc_attr($partner['fee_percent']) : ''; ?>" placeholder="درصد کارمزد (اختیاری)" style="width: 150px;">
                        <button type="button" class="remove-partner">حذف</button>
                    </div>
                <?php endforeach; ?>
                <div class="partner-row template" style="display:none;">
                    <input type="text" name="partner_name[]" placeholder="نام شریک" style="width: 150px;">
                    <input type="number" step="0.1" name="partner_percent[]" placeholder="درصد سود" style="width: 100px;">
                    <input type="number" step="0.1" name="partner_fee_percent[]" placeholder="درصد کارمزد (اختیاری)" style="width: 150px;">
                    <button type="button" class="remove-partner">حذف</button>
                </div>
            </div>
            <button type="button" id="add-partner" class="button">افزودن شریک جدید</button>
            <input type="submit" name="bzc_save_partners" class="button button-primary" value="ذخیره شرکا">
        </form>

        <h2>💰 تنظیم کارمزد درگاه‌های پرداخت</h2>
        <form method="post">
            <?php wp_nonce_field('bzc_settings'); ?>
            <table class="widefat">
                <thead>
                    <tr><th>درگاه</th><th>نوع کارمزد</th><th>تنظیمات</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($active_gateways as $id => $title): 
                        $fee = $gateways_fee[$id] ?? ['type' => 'percent', 'value' => 0, 'ranges' => []];
                    ?>
                        <tr class="gateway-row">
                            <td><?php echo esc_html($title); ?><input type="hidden" name="gateway_id[]" value="<?php echo esc_attr($id); ?>"></td>
                            <td>
                                <select name="gateway_fee_type[<?php echo esc_attr($id); ?>]" class="fee-type" data-gateway="<?php echo esc_attr($id); ?>">
                                    <option value="percent" <?php selected($fee['type'], 'percent'); ?>>درصدی</option>
                                    <option value="fixed" <?php selected($fee['type'], 'fixed'); ?>>ثابت (تومان)</option>
                                    <option value="equation" <?php selected($fee['type'], 'equation'); ?>>رنجی (پلکانی)</option>
                                </select>
                            </td>
                            <td>
                                <div class="fee-percent-section" id="percent-<?php echo esc_attr($id); ?>" style="display: <?php echo ($fee['type'] == 'percent') ? 'block' : 'none'; ?>">
                                    <input type="number" step="0.01" name="gateway_percent[<?php echo esc_attr($id); ?>]" value="<?php echo ($fee['type']=='percent') ? esc_attr($fee['value']) : '0'; ?>" style="width: 150px;"> درصد
                                </div>
                                <div class="fee-fixed-section" id="fixed-<?php echo esc_attr($id); ?>" style="display: <?php echo ($fee['type'] == 'fixed') ? 'block' : 'none'; ?>">
                                    <input type="number" name="gateway_fixed[<?php echo esc_attr($id); ?>]" value="<?php echo ($fee['type']=='fixed') ? esc_attr($fee['value']) : '0'; ?>" style="width: 150px;"> تومان
                                </div>
                                <div class="fee-equation-section" id="equation-<?php echo esc_attr($id); ?>" style="display: <?php echo ($fee['type'] == 'equation') ? 'block' : 'none'; ?>">
                                    <div class="ranges-container" data-gateway="<?php echo esc_attr($id); ?>">
                                        <?php 
                                        $ranges = ($fee['type'] == 'equation' && isset($fee['ranges'])) ? $fee['ranges'] : [];
                                        if (empty($ranges)) $ranges = [['min' => '', 'max' => '', 'value_type' => 'percent', 'value' => '']];
                                        foreach ($ranges as $idx => $range): ?>
                                            <div class="range-row">
                                                <input type="number" name="gateway_ranges[<?php echo esc_attr($id); ?>][<?php echo $idx; ?>][min]" value="<?php echo esc_attr($range['min']); ?>" placeholder="حداقل" style="width:100px">
                                                <input type="number" name="gateway_ranges[<?php echo esc_attr($id); ?>][<?php echo $idx; ?>][max]" value="<?php echo esc_attr($range['max']); ?>" placeholder="حداکثر" style="width:100px">
                                                <select name="gateway_ranges[<?php echo esc_attr($id); ?>][<?php echo $idx; ?>][value_type]">
                                                    <option value="percent" <?php selected($range['value_type'], 'percent'); ?>>درصدی</option>
                                                    <option value="fixed" <?php selected($range['value_type'], 'fixed'); ?>>ثابت</option>
                                                </select>
                                                <input type="number" step="0.01" name="gateway_ranges[<?php echo esc_attr($id); ?>][<?php echo $idx; ?>][value]" value="<?php echo esc_attr($range['value']); ?>" placeholder="مقدار" style="width:100px">
                                                <button type="button" class="remove-range button button-small">❌</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="add-range button button-secondary" data-gateway="<?php echo esc_attr($id); ?>">+ افزودن رنج</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <input type="submit" name="bzc_save_gateways" class="button button-primary" value="ذخیره کارمزد درگاه‌ها">
        </form>
    </div>
    <?php
}

function bzc_get_active_payment_gateways() {
    if (!class_exists('WC_Payment_Gateways')) return [];
    $gateways = WC_Payment_Gateways::instance()->get_available_payment_gateways();
    $list = [];
    foreach ($gateways as $id => $gateway) $list[$id] = $gateway->get_title();
    return $list;
}

// ذخیره قیمت خرید دستی (تکی)
add_action('wp_ajax_bzc_save_buy_price', 'bzc_ajax_save_buy_price');
function bzc_ajax_save_buy_price() {
    check_ajax_referer('bzc_nonce', 'nonce');
    $order_id = intval($_POST['order_id']);
    $product_name = sanitize_text_field($_POST['product_name']);
    $buy_price = floatval($_POST['buy_price']);
    update_post_meta($order_id, '_bzc_buy_price_' . sanitize_title($product_name), $buy_price);
    wp_send_json_success(['message' => 'قیمت خرید با موفقیت ذخیره شد']);
}

// ذخیره همه قیمت‌ها
add_action('wp_ajax_bzc_save_all_buy_prices', 'bzc_ajax_save_all_buy_prices');
function bzc_ajax_save_all_buy_prices() {
    check_ajax_referer('bzc_nonce', 'nonce');
    $prices = $_POST['prices'];
    $saved_count = 0;
    foreach ($prices as $price_data) {
        update_post_meta(intval($price_data['order_id']), '_bzc_buy_price_' . sanitize_title($price_data['product_name']), floatval($price_data['buy_price']));
        $saved_count++;
    }
    wp_send_json_success(['message' => $saved_count . ' قیمت با موفقیت ذخیره شد']);
}

// محاسبه کارمزد درگاه
function bzc_calculate_gateway_fee($amount, $config) {
    if (!$config) return 0;
    if ($config['type'] == 'fixed') return floatval($config['value']);
    if ($config['type'] == 'percent') return $amount * (floatval($config['value']) / 100);
    if ($config['type'] == 'equation' && isset($config['ranges'])) {
        foreach ($config['ranges'] as $range) {
            $min = floatval($range['min'] ?? 0);
            $max = (isset($range['max']) && $range['max'] !== '' && $range['max'] !== '0') ? floatval($range['max']) : PHP_FLOAT_MAX;
            if ($amount >= $min && $amount <= $max) {
                $value = floatval($range['value']);
                if ($range['value_type'] == 'percent') return $amount * ($value / 100);
                else return $value;
            }
        }
    }
    return 0;
}

// دریافت داده‌های سفارش‌ها
add_action('wp_ajax_bzc_get_orders_data', 'bzc_ajax_get_orders_data');
function bzc_ajax_get_orders_data() {
    check_ajax_referer('bzc_nonce', 'nonce');
    
    $year = intval($_POST['year']);
    $month = intval($_POST['month']);
    $search = sanitize_text_field($_POST['search']);
    
    $start_date = date('Y-m-d', strtotime("$year-$month-01"));
    $end_date = date('Y-m-t', strtotime("$year-$month-01"));
    
    // فقط وضعیت‌های درحال آماده سازی و ارسال شد
    // wc-processing = درحال آماده سازی
    // wc-sent = ارسال شد
    $valid_statuses = ['wc-processing', 'wc-sent'];
    
    // دریافت سفارش‌ها بر اساس تاریخ ایجاد و وضعیت
    $args = [
        'limit' => -1,
        'return' => 'objects',
        'date_created' => $start_date . '...' . $end_date,
        'status' => $valid_statuses
    ];
    
    $orders = wc_get_orders($args);
    
    // اگر سفارشی پیدا نشد، همه سفارش‌های با وضعیت معتبر را بدون فیلتر تاریخ برگردان
    if (empty($orders)) {
        $args = [
            'limit' => -1, 
            'return' => 'objects',
            'status' => $valid_statuses
        ];
        $orders = wc_get_orders($args);
    }
    
    $partners = get_option('bzc_partners', []);
    $gateways_fee = get_option('bzc_gateways_fee', []);
    
    $total_sales = 0;
    $total_items = 0;
    $total_cost = 0;
    $total_gross_profit = 0;
    $total_gateway_fee = 0;
    $orders_data = [];
    
    foreach ($orders as $order) {
        $order_status = 'wc-' . $order->get_status();
        if (!in_array($order_status, $valid_statuses)) continue;
        
        // فیلتر جستجو
        if (!empty($search)) {
            $match = false;
            if (strpos(strval($order->get_id()), $search) !== false) $match = true;
            foreach ($order->get_items() as $item) {
                if (strpos($item->get_name(), $search) !== false) $match = true;
            }
            if (!$match) continue;
        }
        
        $order_total = $order->get_total();
        $gateway_id = $order->get_payment_method();
        
        // اگر درگاهی وجود نداشت (سفارش دستی)
        if (empty($gateway_id)) {
            $gateway_id = 'manual';
        }
        
        $fee_config = $gateways_fee[$gateway_id] ?? null;
        $order_gateway_fee = bzc_calculate_gateway_fee($order_total, $fee_config);
        
        // اگر سفارش دستی است و کارمزدی ندارد
        if ($gateway_id == 'manual' && $order_gateway_fee == 0 && $fee_config === null) {
            $order_gateway_fee = 0;
        }
        
        foreach ($order->get_items() as $item) {
            $qty = $item->get_quantity();
            $price_sell_total = floatval($item->get_total());
            
            // اگر قیمت فروش صفر است (سفارش دستی بدون قیمت)
            if ($price_sell_total == 0) {
                $product = $item->get_product();
                if ($product) {
                    $price_sell_total = floatval($product->get_price()) * $qty;
                }
            }
            
            $unit_sell_price = $price_sell_total / $qty;
            $product_name = $item->get_name();
            
            $meta_key = '_bzc_buy_price_' . sanitize_title($product_name);
            $saved_buy_price_total = floatval(get_post_meta($order->get_id(), $meta_key, true));
            
            // اگر قیمت خرید دستی وجود نداشت
            if ($saved_buy_price_total == 0) {
                if ($price_sell_total > 0) {
                    $saved_buy_price_total = $price_sell_total * 0;
                } else {
                    $product = $item->get_product();
                    if ($product) {
                        $product_price = floatval($product->get_price());
                        $saved_buy_price_total = $product_price * $qty * 0;
                        $price_sell_total = $product_price * $qty;
                        $unit_sell_price = $product_price;
                    }
                }
            }
            
            $unit_buy_price = $saved_buy_price_total / $qty;
            
            // سهم کارمزد این قلم
            $item_fee_share = 0;
            if ($order_total > 0 && $order_gateway_fee > 0) {
                $item_fee_share = ($price_sell_total / $order_total) * $order_gateway_fee;
            }
            
            // سود ناخالص این قلم (فروش - قیمت خرید)
            $gross_profit = $price_sell_total - $saved_buy_price_total;
            
            $orders_data[] = [
                'order_id' => $order->get_id(),
                'product' => $product_name,
                'qty' => $qty,
                'unit_sell_price' => round($unit_sell_price),
                'total_sell' => round($price_sell_total),
                'unit_buy_price' => round($unit_buy_price),
                'total_buy' => round($saved_buy_price_total),
                'gateway' => ($gateway_id == 'manual') ? 'ثبت دستی' : $gateway_id,
                'fee_share' => round($item_fee_share),
                'gross_profit' => round($gross_profit),
                'status' => $order_status
            ];
            
            $total_sales += $price_sell_total;
            $total_items += $qty;
            $total_cost += $saved_buy_price_total;
            $total_gross_profit += $gross_profit;
            $total_gateway_fee += $item_fee_share;
        }
    }
    
    // محاسبه سهم شرکا از سود ناخالص
    $partners_share_details = [];
    $total_partner_percent = array_sum(array_column($partners, 'percent'));
    $remaining_percent = max(0, 100 - $total_partner_percent);
    
    foreach ($partners as $partner) {
        // سهم شریک از سود ناخالص کل
        $partner_gross_share = $total_gross_profit * ($partner['percent'] / 100);
        
        // سهم شریک از کارمزد کل
        $partner_fee_percent = isset($partner['fee_percent']) ? $partner['fee_percent'] : null;
        if ($partner_fee_percent !== null) {
            $partner_fee_share = $total_gateway_fee * ($partner_fee_percent / 100);
        } else {
            $partners_without_fee = array_filter($partners, function($p) { return !isset($p['fee_percent']) || $p['fee_percent'] === null; });
            $equal_count = count($partners_without_fee);
            $partner_fee_share = $equal_count > 0 ? $total_gateway_fee / $equal_count : 0;
        }
        
        // سود نهایی شریک
        $partner_final_share = $partner_gross_share - $partner_fee_share;
        
        $partners_share_details[] = [
            'name' => $partner['name'],
            'gross_share' => round($partner_gross_share),
            'fee_share' => round($partner_fee_share),
            'final_share' => round($partner_final_share),
            'percent' => $partner['percent'],
            'fee_percent' => $partner_fee_percent
        ];
    }
    
    // سود انباشته
    if ($remaining_percent > 0) {
        $remaining_gross = $total_gross_profit * ($remaining_percent / 100);
        $remaining_fee = $total_gateway_fee * ($remaining_percent / 100);
        $partners_share_details[] = [
            'name' => 'سود انباشته (حساب اصلی)',
            'gross_share' => round($remaining_gross),
            'fee_share' => round($remaining_fee),
            'final_share' => round($remaining_gross - $remaining_fee),
            'percent' => $remaining_percent,
            'fee_percent' => $remaining_percent,
            'is_remaining' => true
        ];
    }
    
    wp_send_json([
        'orders' => $orders_data,
        'summary' => [
            'total_sales' => round($total_sales),
            'total_items' => $total_items,
            'total_cost' => round($total_cost),
            'total_gross_profit' => round($total_gross_profit),
            'total_gateway_fee' => round($total_gateway_fee)
        ],
        'partners' => $partners_share_details
    ]);
}
?>