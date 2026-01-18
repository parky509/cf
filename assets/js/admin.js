/**
 * Chinemerem Foods Admin Script - Fixed for proper AJAX handling
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Add product form
        $('#cfi-add-product-form').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const btn = form.find('button[type="submit"]');
            
            // Validate fields
            var name = form.find('[name="name"]').val().trim();
            var price = form.find('[name="price"]').val();
            
            if (!name) {
                alert('Product name is required');
                return;
            }
            if (!price || parseFloat(price) <= 0) {
                alert('Price must be greater than 0');
                return;
            }
            
            btn.prop('disabled', true).text('Adding...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cfi_add_product',
                    nonce: cfiData?.nonce || '',
                    name: name,
                    price: price,
                    unit: form.find('[name="unit"]').val(),
                    category: form.find('[name="category"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert('Product added successfully!');
                        location.reload();
                    } else {
                        alert(response.data?.message || 'Failed to add product');
                        btn.prop('disabled', false).text('Add Product');
                    }
                },
                error: function(xhr, status, error) {
                    alert('Network error: ' + error);
                    btn.prop('disabled', false).text('Add Product');
                }
            });
        });

        // Add debtor form
        $('#cfi-add-debtor-form').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const btn = form.find('button[type="submit"]');
            
            var name = form.find('[name="name"]').val().trim();
            if (!name) {
                alert('Debtor name is required');
                return;
            }
            
            btn.prop('disabled', true).text('Adding...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cfi_add_debtor',
                    nonce: cfiData?.nonce || '',
                    name: name,
                    phone: form.find('[name="phone"]').val(),
                    email: form.find('[name="email"]').val(),
                    address: form.find('[name="address"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert('Debtor added successfully!');
                        location.reload();
                    } else {
                        alert(response.data?.message || 'Failed to add debtor');
                        btn.prop('disabled', false).text('Add Debtor');
                    }
                },
                error: function(xhr, status, error) {
                    alert('Network error: ' + error);
                    btn.prop('disabled', false).text('Add Debtor');
                }
            });
        });

        // Add user form
        $('#cfi-add-user-form').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const btn = form.find('button[type="submit"]');
            
            // Validate all required fields
            var username = form.find('[name="username"]').val().trim();
            var email = form.find('[name="email"]').val().trim();
            var password = form.find('[name="password"]').val();
            var name = form.find('[name="name"]').val().trim();
            
            if (!username) {
                alert('Username is required');
                return;
            }
            if (!email) {
                alert('Email is required');
                return;
            }
            if (!password) {
                alert('Password is required');
                return;
            }
            if (!name) {
                alert('Display name is required');
                return;
            }
            
            btn.prop('disabled', true).text('Adding...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cfi_add_user',
                    nonce: cfiData?.nonce || '',
                    username: username,
                    email: email,
                    password: password,
                    name: name,
                    role: form.find('[name="role"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert('User added successfully!');
                        location.reload();
                    } else {
                        alert(response.data?.message || 'Failed to add user');
                        btn.prop('disabled', false).text('Add User');
                    }
                },
                error: function(xhr, status, error) {
                    alert('Network error: ' + error);
                    btn.prop('disabled', false).text('Add User');
                }
            });
        });
        
        // Edit product button click
        $(document).on('click', '.cfi-edit-product', function() {
            var id = $(this).data('id');
            var row = $(this).closest('tr');
            var name = row.find('td:eq(1)').text().trim();
            var price = row.find('td:eq(2)').text().replace(/[₦,]/g, '').trim();
            var unit = row.find('td:eq(3)').text().trim();
            var category = row.find('td:eq(4)').text().trim();
            
            var newName = prompt('Product Name:', name);
            if (newName === null) return;
            var newPrice = prompt('Price:', price);
            if (newPrice === null) return;
            var newUnit = prompt('Unit:', unit);
            if (newUnit === null) return;
            var newCategory = prompt('Category:', category);
            if (newCategory === null) return;
            
            var ajaxUrl = (typeof cfiData !== 'undefined' && cfiData.ajaxUrl) ? cfiData.ajaxUrl : ajaxurl;
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cfi_update_product',
                    nonce: cfiData?.nonce || '',
                    id: id,
                    name: newName,
                    price: newPrice,
                    unit: newUnit,
                    category: newCategory
                },
                success: function(response) {
                    if (response.success) {
                        alert('Product updated successfully!');
                        location.reload();
                    } else {
                        alert(response.data?.message || 'Failed to update product');
                    }
                }
            });
        });
        
        // Edit debtor button click
        $(document).on('click', '.cfi-edit-debtor', function() {
            var id = $(this).data('id');
            var row = $(this).closest('tr');
            var name = row.find('td:eq(1)').text().trim();
            var phone = row.find('td:eq(2)').text().trim();
            
            var newName = prompt('Debtor Name:', name);
            if (newName === null) return;
            var newPhone = prompt('Phone:', phone);
            if (newPhone === null) return;
            
            var ajaxUrl = (typeof cfiData !== 'undefined' && cfiData.ajaxUrl) ? cfiData.ajaxUrl : ajaxurl;
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cfi_update_debtor',
                    nonce: cfiData?.nonce || '',
                    id: id,
                    name: newName,
                    phone: newPhone
                },
                success: function(response) {
                    if (response.success) {
                        alert('Debtor updated successfully!');
                        location.reload();
                    } else {
                        alert(response.data?.message || 'Failed to update debtor');
                    }
                }
            });
        });

        // Delete product
        $(document).on('click', '.cfi-delete-product', function() {
            if (!confirm('Are you sure you want to delete this product?')) return;
            
            const btn = $(this);
            const id = btn.data('id');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cfi_delete_product',
                    nonce: cfiData?.nonce || '',
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        alert('Product deleted successfully!');
                        btn.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data?.message || 'Failed to delete');
                    }
                }
            });
        });

        // Delete debtor
        $(document).on('click', '.cfi-delete-debtor', function() {
            if (!confirm('Are you sure you want to delete this debtor?')) return;
            
            const btn = $(this);
            const id = btn.data('id');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cfi_delete_debtor',
                    nonce: cfiData?.nonce || '',
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        alert('Debtor deleted successfully!');
                        btn.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data?.message || 'Failed to delete');
                    }
                }
            });
        });

        // Delete user
        $(document).on('click', '.cfi-delete-user', function() {
            if (!confirm('Are you sure you want to delete this user?')) return;
            
            const btn = $(this);
            const id = btn.data('id');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cfi_delete_user',
                    nonce: cfiData?.nonce || '',
                    user_id: id
                },
                success: function(response) {
                    if (response.success) {
                        alert('User deleted successfully!');
                        btn.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data?.message || 'Failed to delete');
                    }
                }
            });
        });

        // Create backup
        $('#cfi-create-backup').on('click', function() {
            const btn = $(this);
            btn.prop('disabled', true).text('Creating...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cfi_download_backup',
                    nonce: cfiData?.nonce || ''
                },
                success: function(response) {
                    if (response.success) {
                        alert('Backup created successfully');
                        if (response.data.file_url) {
                            window.open(response.data.file_url);
                        }
                        location.reload();
                    } else {
                        alert(response.data?.message || 'Failed to create backup');
                    }
                    btn.prop('disabled', false).text('Create Full Backup');
                },
                error: function() {
                    alert('Network error');
                    btn.prop('disabled', false).text('Create Full Backup');
                }
            });
        });
    });

})(jQuery);
