# Chinemerem Foods Inventory Management System

A comprehensive WordPress plugin for managing inventory, orders, and financial records for Chinemerem Foods.

## Features

### Core Features
- **User Authentication**: Role-based access (Super Admin, Admin, Staff)
- **Responsive Design**: Mobile-first with glassmorphism UI
- **Offline Support**: Service Worker for offline order taking
- **Daily Backups**: Automatic and manual backup functionality

### Pages & Modules

#### Order Management
- **Take Order**: Process cash and transfer/card payments
- **Order History**: View and filter past orders
- **Order Product Summary**: Daily analytics by product
- **Debtor Order Summary**: Debtor order analytics

#### Inventory Management
- **Stock Inventory**: Track opening, closing, imports, sales
- **Packing Store**: Manage packing store transfers
- **Import Record**: Log product deliveries
- **Not Supplied Record**: Track unfulfilled orders
- **Supplied Today**: Record today's supplies

#### Financial Management
- **Financial Summary**: Daily cash flow overview
- **Cash Out Record**: Track bank transfers
- **Transfer History**: All card/transfer payments
- **Expenses Record**: Daily expense tracking
- **Debtors Record**: Credit accounts management

#### Administration
- **Admin Panel**: Product, debtor, and user management
- **Reconciliation Calendar**: Two-admin daily verification
- **Profile**: User account management

## Installation

1. Upload the `chinemerem-foods-inventory` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. All pages are auto-created on activation

## User Roles

| Role | Permissions |
|------|-------------|
| Super Admin (WordPress Admin) | Full access, delete history, manage users |
| CFI Admin | Manage products, debtors, reconciliation |
| CFI Staff | Take orders, record inventory, expenses |

## Design System

### Colors
- **Primary**: #1a365d (Dark Navy Blue)
- **Secondary**: #2c5282 (Navy Blue)
- **Accent**: #3182ce (Blue)
- **Background**: #ffffff (White)

### UI Components
- Glassmorphism cards with blur effect
- Responsive tables (card layout on mobile)
- Real-time calculations
- Toast notifications
- Modal confirmations

## Calculations

### Stock Closing Formula
```
Closing = Opening + Import - Cash Supply - Credit Supply + Not Supplied - Supplied Today - To Packing + From Packing
```

### Cash Left Formula
```
Cash Left = Total Sales - Transfer (Orders) - Transfer (Cash Out) + Debtors Cash - Expenses + Old Cash - Cash to Bank
```

## Database Tables

The plugin creates the following tables:
- `cfi_products` - Product catalog
- `cfi_orders` - Order records
- `cfi_order_items` - Order line items
- `cfi_stock` - Daily stock records
- `cfi_stock_history` - Stock changes
- `cfi_packing_store` - Packing store records
- `cfi_debtors` - Debtor accounts
- `cfi_debtor_transactions` - Debtor activity
- `cfi_expenses` - Expense records
- `cfi_imports` - Import records
- `cfi_not_supplied` - Not supplied items
- `cfi_supplied_today` - Supplied items
- `cfi_cashout` - Cash out records
- `cfi_financial_summary` - Daily financial summary
- `cfi_transfer_history` - Transfer payments
- `cfi_reconciliation` - Reconciliation tracking
- `cfi_backup_log` - Backup history

## API Endpoints (AJAX)

All endpoints use `wp_ajax_cfi_{action}` format:

- `cfi_login` / `cfi_logout`
- `cfi_get_products` / `cfi_add_product` / `cfi_update_product`
- `cfi_submit_order` / `cfi_get_order_history`
- `cfi_get_stock` / `cfi_update_stock`
- `cfi_get_financial_summary` / `cfi_update_financial`
- And many more...

## Offline Support

The plugin uses a Service Worker to:
- Cache static assets
- Queue orders when offline
- Sync data when back online

## Requirements

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+

## Support

Designed by [BendlessTech](https://bendlestech.com)

## License

Copyright © 2024 Chinemerem Foods. All rights reserved.
