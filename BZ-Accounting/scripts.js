jQuery(document).ready(function($) {
    
    function loadOrders(year, month, search = '') {
        $('#bzc-orders-table-container').html('<div class="loading">در حال بارگذاری سفارش‌ها...</div>');
        
        $.post(bzc_ajax.ajax_url, {
            action: 'bzc_get_orders_data',
            nonce: bzc_ajax.nonce,
            year: year,
            month: month,
            search: search
        }, function(res) {
            if (!res.orders || res.orders.length === 0) {
                $('#bzc-orders-table-container').html('<div class="loading">❌ هیچ سفارشی یافت نشد.</div>');
                $('#bzc-summary').html('');
                $('#bzc-partners-share').html('');
                return;
            }
            
            let html = '<div style="overflow-x: auto;">';
            html += '<table class="bzc-orders-table" style="width: 100%; border-collapse: collapse; direction: rtl;">';
            html += '<thead><tr style="background-color: #2271b1; color: white;">';
            html += '<th style="padding: 12px;">شماره سفارش</th>';
            html += '<th style="padding: 12px;">قلم سفارش</th>';
            html += '<th style="padding: 12px;">تعداد</th>';
            html += '<th style="padding: 12px;">قیمت فروش فی</th>';
            html += '<th style="padding: 12px;">قیمت فروش کل</th>';
            html += '<th style="padding: 12px;">قیمت خرید فی</th>';
            html += '<th style="padding: 12px;">قیمت خرید کل</th>';
            html += '<th style="padding: 12px;">درگاه پرداخت</th>';
            html += '<th style="padding: 12px;">کارمزد</th>';
            html += '<th style="padding: 12px;">سود ناخالص</th>';
            html += '<th style="padding: 12px;">وضعیت</th>';
            html += '<th style="padding: 12px;">عملیات</th>';
            html += '</tr></thead><tbody>';
            
            res.orders.forEach((order, index) => {
                let rowClass = index % 2 === 0 ? 'background-color: #f9f9f9;' : '';
                let profitColor = order.gross_profit >= 0 ? '#2c7a2c' : '#dc3232';
                
                html += `<tr data-order-id="${order.order_id}" data-product-name="${order.product}" style="${rowClass}">`;
                html += `<td style="padding: 10px; text-align: center;">${order.order_id}</td>`;
                html += `<td style="padding: 10px; text-align: right;">${order.product}</td>`;
                html += `<td style="padding: 10px; text-align: center;" class="qty-cell">${numberFormat(order.qty)}</td>`;
                html += `<td style="padding: 10px; text-align: left;">${numberFormat(order.unit_sell_price)}</td>`;
                html += `<td style="padding: 10px; text-align: left;" class="total-sell">${numberFormat(order.total_sell)}</td>`;
                html += `<td style="padding: 10px; text-align: center;"><input type="number" class="buy-price-input" value="${order.unit_buy_price}" step="1000" style="width: 100px; padding: 5px;"></td>`;
                html += `<td style="padding: 10px; text-align: left;" class="total-buy-cell">${numberFormat(order.total_buy)}</td>`;
                html += `<td style="padding: 10px; text-align: center;">${getGatewayName(order.gateway)}</td>`;
                html += `<td style="padding: 10px; text-align: left; color: #ff9800;" class="fee-cell">${numberFormat(order.fee_share)}</td>`;
                html += `<td style="padding: 10px; text-align: left; color: ${profitColor};" class="gross-profit-cell">${numberFormat(order.gross_profit)}</td>`;
                html += `<td style="padding: 10px; text-align: center;">${getStatusText(order.status)}</td>`;
                html += `<td style="padding: 10px; text-align: center;"><button class="save-buy-price" style="background: #46b450; color: white; border: none; padding: 5px 12px; border-radius: 4px; cursor: pointer;">💾 ذخیره</button></td>`;
                html += `</tr>`;
            });
            
            html += '</tbody></table></div>';
            $('#bzc-orders-table-container').html(html);
            
            // جمع کارمزد از جدول
            let totalFee = 0;
            $('.fee-cell').each(function() {
                let val = parseFloat($(this).text().replace(/,/g, ''));
                if (!isNaN(val)) totalFee += val;
            });
            
            let summaryHtml = `
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; margin-top: 20px;">
                    <h3 style="margin-top: 0;">📊 خلاصه کل ماه</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
                        <div style="background: rgba(255,255,255,0.2); padding: 10px; border-radius: 8px;">
                            <strong>فروش کل:</strong><br>${numberFormat(res.summary.total_sales)} تومان
                        </div>
                        <div style="background: rgba(255,255,255,0.2); padding: 10px; border-radius: 8px;">
                            <strong>تعداد اقلام:</strong><br>${numberFormat(res.summary.total_items)}
                        </div>
                        <div style="background: rgba(255,255,255,0.2); padding: 10px; border-radius: 8px;">
                            <strong>خرید کل:</strong><br>${numberFormat(res.summary.total_cost)} تومان
                        </div>
                        <div style="background: rgba(255,255,255,0.2); padding: 10px; border-radius: 8px;">
                            <strong>سود ناخالص کل:</strong><br>${numberFormat(res.summary.total_gross_profit)} تومان
                        </div>
                        <div style="background: rgba(255,255,255,0.2); padding: 10px; border-radius: 8px;">
                            <strong>کارمزد درگاه:</strong><br>${numberFormat(totalFee)} تومان
                        </div>
                    </div>
                    <div style="margin-top: 10px; font-size: 12px; background: rgba(0,0,0,0.2); padding: 8px; border-radius: 6px;">
                        📐 سود خالص کل = سود ناخالص کل (${numberFormat(res.summary.total_gross_profit)}) - کارمزد کل (${numberFormat(totalFee)}) = ${numberFormat(res.summary.total_gross_profit - totalFee)} تومان
                    </div>
                </div>
            `;
            $('#bzc-summary').html(summaryHtml);
            
            let partnersHtml = `<div style="background: white; padding: 20px; border-radius: 12px; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 style="margin-top: 0; color: #2271b1;">👥 سهم شرکا در این ماه</h3>
                <div style="background: #e8f0fe; padding: 10px; border-radius: 8px; margin-bottom: 15px;">
                    📌 سود ناخالص کل: ${numberFormat(res.summary.total_gross_profit)} تومان | 💳 کل کارمزد: ${numberFormat(totalFee)} تومان
                </div>`;
            
            if (res.partners && res.partners.length > 0) {
                res.partners.forEach(p => {
                    let feeText = p.fee_percent ? `${p.fee_percent}%` : 'مساوی';
                    let isRemaining = p.is_remaining === true;
                    let bgColor = isRemaining ? '#fff8e1' : '#f0f8ff';
                    let borderColor = isRemaining ? '#ff9800' : '#2271b1';
                    
                    partnersHtml += `
                        <div style="background: ${bgColor}; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-right: 4px solid ${borderColor};">
                            <h4 style="margin-top: 0; color: ${borderColor};">${p.name}</h4>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                                <div>📊 سهم از سود ناخالص (${p.percent}%): ${numberFormat(p.gross_share)} تومان</div>
                                <div>💳 سهم از کارمزد (${feeText}): ${numberFormat(p.fee_share)} تومان</div>
                                <div>💰 <strong>سود نهایی ${p.name}:</strong> ${numberFormat(p.final_share)} تومان</div>
                            </div>
                            <div style="margin-top: 8px; font-size: 11px; color: #666; background: rgba(0,0,0,0.03); padding: 5px; border-radius: 4px;">
                                محاسبه: (${numberFormat(res.summary.total_gross_profit)} × ${p.percent}%) - (${numberFormat(totalFee)} × ${feeText}) = ${numberFormat(p.final_share)} تومان
                            </div>
                        </div>
                    `;
                });
            } else {
                partnersHtml += '<div style="padding: 12px;">هیچ شریکی تعریف نشده است. لطفاً ابتدا در بخش تنظیمات، شرکا را اضافه کنید.</div>';
            }
            
            partnersHtml += `</div>`;
            $('#bzc-partners-share').html(partnersHtml);
        }).fail(function() {
            $('#bzc-orders-table-container').html('<div class="loading" style="color: #dc3232;">❌ خطا در دریافت اطلاعات از سرور</div>');
        });
    }
    
    function numberFormat(num) {
        if (num === undefined || num === null) return '0';
        return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    function getStatusText(status) {
        const statusMap = {
            'wc-processing': '🟡 درحال آماده سازی',
            'wc-sent': '📦 ارسال شد',
            'wc-pending': '⏳ در انتظار',
            'wc-completed': '✅ تکمیل شده',
            'wc-on-hold': '🔒 در انتظار بررسی',
            'wc-checkout-draft': '📝 پیش‌نویس'
        };
        return statusMap[status] || status;
    }
    
    function getStatusText(status) {
        const statusMap = {
            'wc-processing': '🟡 درحال آماده سازی',
            'wc-sent': '📦 ارسال شد',
            'wc-completed': '✅ تکمیل شده'
        };
        return statusMap[status] || status;
    }
    
    // ذخیره تکی
    $(document).on('click', '.save-buy-price', function() {
        let row = $(this).closest('tr');
        let orderId = row.data('order-id');
        let productName = row.data('product-name');
        let newUnitBuyPrice = parseFloat(row.find('.buy-price-input').val());
        let qty = parseFloat(row.find('.qty-cell').text().replace(/,/g, ''));
        let totalSell = parseFloat(row.find('.total-sell').text().replace(/,/g, ''));
        let fee = parseFloat(row.find('.fee-cell').text().replace(/,/g, ''));
        
        if (isNaN(newUnitBuyPrice) || newUnitBuyPrice < 0) {
            alert('⚠️ لطفاً یک قیمت معتبر وارد کنید');
            return;
        }
        
        let totalBuyPrice = newUnitBuyPrice * qty;
        let newGrossProfit = totalSell - totalBuyPrice;
        
        $.post(bzc_ajax.ajax_url, {
            action: 'bzc_save_buy_price',
            nonce: bzc_ajax.nonce,
            order_id: orderId,
            product_name: productName,
            buy_price: totalBuyPrice
        }, function(response) {
            if (response.success) {
                row.find('.total-buy-cell').text(numberFormat(totalBuyPrice));
                row.find('.gross-profit-cell').text(numberFormat(newGrossProfit)).css('color', newGrossProfit >= 0 ? '#2c7a2c' : '#dc3232');
                alert('✓ قیمت خرید با موفقیت ذخیره شد');
                setTimeout(() => $('#bzc-refresh').click(), 500);
            } else {
                alert('❌ خطا در ذخیره سازی قیمت');
            }
        }).fail(function() {
            alert('❌ خطا در ارتباط با سرور');
        });
    });
    
    // ذخیره همه قیمت‌ها
    $('#bzc-save-all-prices').on('click', function() {
        let allPrices = [];
        let hasError = false;
        
        $('.bzc-orders-table tbody tr').each(function() {
            let row = $(this);
            let newUnitBuyPrice = parseFloat(row.find('.buy-price-input').val());
            let qty = parseFloat(row.find('.qty-cell').text().replace(/,/g, ''));
            
            if (isNaN(newUnitBuyPrice) || newUnitBuyPrice < 0) {
                hasError = true;
                row.find('.buy-price-input').css('border', '2px solid red');
                return false;
            }
            row.find('.buy-price-input').css('border', '');
            
            allPrices.push({
                order_id: row.data('order-id'),
                product_name: row.data('product-name'),
                buy_price: newUnitBuyPrice * qty
            });
        });
        
        if (hasError) {
            alert('⚠️ لطفاً تمام قیمت‌های معتبر را وارد کنید');
            return;
        }
        
        if (allPrices.length === 0) {
            alert('هیچ قیمتی برای ذخیره وجود ندارد');
            return;
        }
        
        let savingBtn = $(this);
        let originalText = savingBtn.text();
        savingBtn.text('در حال ذخیره...').prop('disabled', true);
        
        $.post(bzc_ajax.ajax_url, {
            action: 'bzc_save_all_buy_prices',
            nonce: bzc_ajax.nonce,
            prices: allPrices
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                $('#bzc-refresh').click();
            } else {
                alert('❌ خطا در ذخیره سازی');
            }
        }).fail(function() {
            alert('❌ خطا در ارتباط با سرور');
        }).always(function() {
            savingBtn.text(originalText).prop('disabled', false);
        });
    });
    
    // خروجی CSV
    $('#bzc-export-csv').on('click', function() {
        let csvRows = [];
        
        // هدرها
        let headers = [];
        $('.bzc-orders-table th').each(function() {
            headers.push('"' + $(this).text().replace(/"/g, '""') + '"');
        });
        csvRows.push(headers.join(','));
        
        // داده‌های جدول
        $('.bzc-orders-table tbody tr').each(function() {
            let rowData = [];
            $(this).find('td').each(function(index) {
                if (index === 5) {
                    let val = $(this).find('input').val();
                    rowData.push('"' + val.replace(/"/g, '""') + '"');
                } else {
                    let text = $(this).text().trim();
                    rowData.push('"' + text.replace(/"/g, '""') + '"');
                }
            });
            csvRows.push(rowData.join(','));
        });
        
        // خلاصه
        csvRows.push('""');
        csvRows.push('"*** خلاصه کل ماه ***"');
        let summaryText = $('#bzc-summary').text();
        let summaryLines = summaryText.split('\n');
        summaryLines.forEach(line => {
            if (line.trim()) {
                csvRows.push('"' + line.trim().replace(/"/g, '""') + '"');
            }
        });
        
        // سهم شرکا
        csvRows.push('""');
        csvRows.push('"*** سهم شرکا ***"');
        let partnersText = $('#bzc-partners-share').text();
        let partnersLines = partnersText.split('\n');
        partnersLines.forEach(line => {
            if (line.trim() && !line.includes('سهم شرکا') && !line.includes('اضافه کنید')) {
                csvRows.push('"' + line.trim().replace(/"/g, '""') + '"');
            }
        });
        
        let blob = new Blob(["\uFEFF" + csvRows.join('\n')], {type: 'text/csv;charset=utf-8;'});
        let link = document.createElement('a');
        let url = URL.createObjectURL(blob);
        link.href = url;
        link.download = 'bz_accounting_report_' + new Date().toISOString().slice(0, 19) + '.csv';
        link.click();
        URL.revokeObjectURL(url);
    });
    
    // رویدادها
    $('#bzc-refresh').on('click', function() {
        loadOrders($('#bzc-year').val(), $('#bzc-month').val(), $('#bzc-search').val());
    });
    
    $('#bzc-search').on('keypress', function(e) {
        if (e.which === 13) $('#bzc-refresh').click();
    });
    
    // بخش تنظیمات - شرکا
    $('#add-partner').on('click', function() {
        let template = $('.partner-row.template').clone().removeClass('template').show();
        $('#partners-list').append(template);
    });
    
    $(document).on('click', '.remove-partner', function() {
        $(this).closest('.partner-row').remove();
    });
    
    // تنظیمات - رنج‌ها
    $(document).on('click', '.add-range', function() {
        let gatewayId = $(this).data('gateway');
        let container = $(`.ranges-container[data-gateway="${gatewayId}"]`);
        let rangeCount = container.children('.range-row').length;
        
        let newRange = `
            <div class="range-row">
                <input type="number" name="gateway_ranges[${gatewayId}][${rangeCount}][min]" placeholder="حداقل" style="width:100px">
                <input type="number" name="gateway_ranges[${gatewayId}][${rangeCount}][max]" placeholder="حداکثر" style="width:100px">
                <select name="gateway_ranges[${gatewayId}][${rangeCount}][value_type]">
                    <option value="percent">درصدی</option>
                    <option value="fixed">ثابت</option>
                </select>
                <input type="number" step="0.01" name="gateway_ranges[${gatewayId}][${rangeCount}][value]" placeholder="مقدار" style="width:100px">
                <button type="button" class="remove-range button button-small">❌</button>
            </div>
        `;
        container.append(newRange);
    });
    
    $(document).on('click', '.remove-range', function() {
        if ($(this).closest('.ranges-container').children('.range-row').length > 1) {
            $(this).closest('.range-row').remove();
        } else {
            alert('حداقل یک رنج باید وجود داشته باشد');
        }
    });
    
    $(document).on('change', '.fee-type', function() {
        let gatewayId = $(this).data('gateway');
        let type = $(this).val();
        
        $(`#percent-${gatewayId}, #fixed-${gatewayId}, #equation-${gatewayId}`).hide();
        if (type === 'percent') $(`#percent-${gatewayId}`).show();
        else if (type === 'fixed') $(`#fixed-${gatewayId}`).show();
        else if (type === 'equation') $(`#equation-${gatewayId}`).show();
    });
    
    $('.fee-type').each(function() { $(this).trigger('change'); });
    
    // بارگذاری اولیه
    let now = new Date();
    $('#bzc-year').val(now.getFullYear());
    $('#bzc-month').val(now.getMonth() + 1);
    loadOrders(now.getFullYear(), now.getMonth() + 1, '');
});