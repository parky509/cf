<?php
/**
 * Order Product Summary Page Template - REBUILT WITH PHP DATA LOADING
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get date
$selected_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : current_time('Y-m-d');

// Get product summary
$summary = CFI_Orders::get_product_summary($selected_date, 'cash');

// Calculate total quantity
$grand_total = 0;
foreach ($summary as $item) {
    $grand_total += floatval($item->total_quantity);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #f8fafc; min-height: 100vh; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 1rem; }
        
        .page-header {
            background: linear-gradient(135deg, #001943, #002960);
            color: #ffffff !important;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .page-header h1 { margin: 0; font-size: 0.75rem; display: flex; align-items: center; gap: 0.4rem; color: #ffffff !important; font-weight: 600; }
        .page-header h1 i { color: #ffffff !important; font-size: 0.75rem; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 0.75rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.65rem;
            transition: all 0.3s;
        }
        .btn-primary { background: #001943; color: white; }
        .btn-outline { background: white; border: 2px solid #001943; color: #001943; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        
        .glass {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,25,67,0.1);
            border: 2px solid rgba(0,25,67,0.1);
            margin-bottom: 1.5rem;
        }
        .glass h3 { color: #001943; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem; }
        
        .filters {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-bottom: 1.5rem;
        }
        .filter-group label { display: block; font-weight: 600; color: #001943; font-size: 0.8rem; margin-bottom: 0.25rem; }
        .filter-input {
            padding: 0.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.75rem;
        }
        
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { background: #001943; color: white; padding: 0.75rem 0.5rem; text-align: left; }
        td { padding: 0.6rem 0.5rem; border-bottom: 1px solid #e2e8f0; }
        tr:hover { background: #f8fafc; }
        .product-name { font-weight: 600; color: #001943; display: flex; align-items: center; gap: 0.5rem; }
        .product-icon { 
            width: 32px; 
            height: 32px; 
            background: linear-gradient(135deg, #001943, #002960); 
            border-radius: 8px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
        }
        .product-icon i { color: white; font-size: 0.75rem; }
        .qty-value { font-size: 1.25rem; font-weight: 700; color: #001943; }
        
        tfoot td { background: #001943; color: white; font-weight: 600; }
        tfoot .total-qty { font-size: 1.5rem; font-weight: 800; }
        
        .empty { text-align: center; padding: 2rem; color: #64748b; }
        
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .analytics-card {
            background: linear-gradient(135deg, #001943, #002960);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
        }
        .analytics-card .icon { font-size: 2rem; margin-bottom: 0.5rem; }
        .analytics-card .value { font-size: 2rem; font-weight: 800; }
        .analytics-card .label { font-size: 0.8rem; opacity: 0.9; }
        
        @media (max-width: 768px) {
            .page-header { flex-direction: column; text-align: center; }
            .filters { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-chart-bar"></i> Order Product Summary</h1>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <?php
            $order_history = get_page_by_path('cfi-order-history');
            $order_history_url = $order_history ? get_permalink($order_history->ID) : home_url('/order-history/');
            ?>
            <a href="<?php echo esc_url($order_history_url); ?>" class="btn btn-outline" style="background: white;">
                <i class="fas fa-history"></i> Order History
            </a>
            <?php
            $take_order = get_page_by_path('cfi-take-order');
            $take_order_url = $take_order ? get_permalink($take_order->ID) : home_url('/take-order/');
            ?>
            <a href="<?php echo esc_url($take_order_url); ?>" class="btn btn-outline" style="background: white;">
                <i class="fas fa-cart-plus"></i> Take Order
            </a>
        </div>
    </div>
    
    <div class="glass">
        <form method="GET" class="filters">
            <div class="filter-group">
                <label>Select Date</label>
                <input type="date" name="date" class="filter-input" value="<?php echo esc_attr($selected_date); ?>">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sync"></i> Load Summary
            </button>
        </form>
    </div>
    
    <div class="glass">
        <h3><i class="fas fa-shopping-cart" style="color: #16a34a;"></i> Cash Orders Summary</h3>
        <p style="color: #64748b; margin-bottom: 1rem;">Products ordered on <?php echo esc_html(gmdate('l, F j, Y', strtotime($selected_date))); ?> via cash/transfer payments.</p>
        
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Total Quantity</th>
                        <th>Staff & Times</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($summary)) : ?>
                    <tr><td colspan="3" class="empty">No orders found for this date</td></tr>
                    <?php else : ?>
                    <?php foreach ($summary as $item) : ?>
                    <tr>
                        <td class="product-name">
                            <span class="product-icon"><i class="fas fa-box"></i></span>
                            <?php echo esc_html($item->name); ?>
                        </td>
                        <td class="qty-value"><?php echo number_format($item->total_quantity, 1); ?></td>
                        <td><?php echo esc_html($item->staff_info ?: '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($summary)) : ?>
                <tfoot>
                    <tr>
                        <td><strong>Grand Total</strong></td>
                        <td class="total-qty"><?php echo number_format($grand_total, 1); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        
        <?php if (!empty($summary)) : ?>
        <div class="analytics-grid">
            <div class="analytics-card">
                <div class="icon"><i class="fas fa-boxes"></i></div>
                <div class="value"><?php echo count($summary); ?></div>
                <div class="label">Products</div>
            </div>
            <div class="analytics-card">
                <div class="icon"><i class="fas fa-cubes"></i></div>
                <div class="value"><?php echo number_format($grand_total, 0); ?></div>
                <div class="label">Total Units</div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
