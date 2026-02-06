<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/db_connection.php';
require_once 'includes/auth_check.php';

$page_title = "إضافة حركة جديدة";

// جلب البيانات المطلوبة
try {
    // جلب جهات الاتصال
    $contacts = $pdo->query("SELECT contact_id, contact_name, contact_type FROM contacts ORDER BY contact_name")->fetchAll();
    
    // جلب المشاريع النشطة
    $projects = $pdo->query("SELECT project_id, project_name FROM projects WHERE project_status = 'active' ORDER BY project_name")->fetchAll();
    
    // جلب المنتجات مع وحداتها
    $products_query = "
        SELECT p.product_id, p.product_name, p.product_code, p.is_cable,
               pu.unit_id, pu.unit_name, pu.conversion_factor, pu.unit_price, pu.is_base_unit
        FROM products p
        LEFT JOIN product_units pu ON p.product_id = pu.product_id
        WHERE p.product_id IS NOT NULL
        ORDER BY p.product_name, pu.conversion_factor
    ";
    $products_result = $pdo->query($products_query);
    
    // تنظيم البيانات لتسهيل استخدامها في JavaScript
    $products_data = [];
    while($row = $products_result->fetch(PDO::FETCH_ASSOC)) {
        $product_id = $row['product_id'];
        if(!isset($products_data[$product_id])) {
            $products_data[$product_id] = [
                'id' => $product_id,
                'name' => $row['product_name'],
                'code' => $row['product_code'],
                'is_cable' => $row['is_cable'],
                'units' => []
            ];
        }
        if($row['unit_id']) {
            $products_data[$product_id]['units'][] = [
                'unit_id' => $row['unit_id'],
                'unit_name' => $row['unit_name'],
                'conversion_factor' => (float)$row['conversion_factor'],
                'unit_price' => (float)$row['unit_price'],
                'is_base_unit' => $row['is_base_unit']
            ];
        }
    }
    
    // جلب المستودعات
    $warehouses = $pdo->query("SELECT warehouse_id, warehouse_name FROM warehouses WHERE is_active = 1 ORDER BY warehouse_name")->fetchAll();
    
} catch (PDOException $e) {
    die("<div class='alert alert-danger'>خطأ في جلب البيانات: " . $e->getMessage() . "</div>");
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - StoreMate</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap.rtl.min.css" 
          onerror="this.onerror=null;this.href='assets/css/bootstrap.min.css';">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Select2 for better dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <!-- الأنماط العامة -->
    <?php include 'includes/styles.php'; ?>
    
    <style>
        .transaction-card {
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .item-row {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
            transition: all 0.3s;
        }
        
        .item-row:hover {
            background-color: #f9f9f9;
        }
        
        .item-row:last-child {
            border-bottom: none;
        }
        
        .unit-price-display {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .total-price {
            font-weight: bold;
            color: #2d8a39;
        }
        
        .cable-readings {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            border: 1px solid #dee2e6;
        }
        
        .required-star {
            color: #dc3545;
        }
        
        .transaction-type-badge {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 4px;
        }
        
        .btn-add-item {
            border: 2px dashed #6c757d;
            color: #6c757d;
            transition: all 0.3s;
        }
        
        .btn-add-item:hover {
            border-color: #0d6efd;
            color: #0d6efd;
            background-color: #f8f9fa;
        }
        
        .form-select option[value=""] {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- السايدبار -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- المحتوى الرئيسي -->
    <div class="main-content">
        <!-- الشريط العلوي -->
        <?php include 'includes/header.php'; ?>
        
        <!-- محتوى الصفحة -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <!-- عنوان الصفحة -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">إضافة حركة جديدة</h2>
                            <p class="text-muted mb-0">تسجيل حركة وارد/صادر/صرف/مرتجع/تحويل</p>
                        </div>
                        <div>
                            <button type="button" onclick="window.history.back()" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-arrow-right me-1"></i> رجوع
                            </button>
                            <button type="button" onclick="window.location.href='transactions.php'" class="btn btn-outline-primary">
                                <i class="bi bi-list-ul me-1"></i> عرض جميع الحركات
                            </button>
                        </div>
                    </div>
                    
                    <!-- تحذيرات البيانات المطلوبة -->
                    <?php if(empty($contacts)): ?>
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>تحذير:</strong> لا توجد جهات اتصال مسجلة. الرجاء <a href="contacts.php" class="alert-link">إضافة جهات اتصال</a> أولاً.
                    </div>
                    <?php endif; ?>
                    
                    <?php if(empty($products_data)): ?>
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>تحذير:</strong> لا توجد منتجات مسجلة. الرجاء <a href="products.php" class="alert-link">إضافة منتجات</a> أولاً.
                    </div>
                    <?php endif; ?>
                    
                    <!-- نموذج إضافة الحركة -->
                    <form id="transactionForm" action="api/save_transaction.php" method="POST">
                        <!-- حقل مخفي للإجمالي -->
                        <input type="hidden" id="total_amount_input" name="total_amount" value="0">
                        
                        <div class="row">
                            <div class="col-lg-8">
                                <!-- تفاصيل الحركة الأساسية -->
                                <div class="card transaction-card mb-4">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">
                                            <i class="bi bi-card-checklist me-2"></i> معلومات الحركة الأساسية
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <!-- نوع الحركة -->
                                            <div class="col-md-6">
                                                <label class="form-label">نوع الحركة <span class="required-star">*</span></label>
                                                <select class="form-select" id="trans_type" name="trans_type" required>
                                                    <option value="">-- اختر نوع الحركة --</option>
                                                    <option value="in">وارد (شراء من مورد)</option>
                                                    <option value="out">صادر (بيع للعميل)</option>
                                                    <option value="issue">صرف (استخدام لمشروع)</option>
                                                    <option value="return">مرتجع (إرجاع من عميل)</option>
                                                    <option value="transfer">تحويل بين المخازن</option>
                                                </select>
                                            </div>
                                            
                                            <!-- التاريخ -->
                                            <div class="col-md-6">
                                                <label class="form-label">تاريخ الحركة <span class="required-star">*</span></label>
                                                <input type="datetime-local" class="form-control" id="trans_date" name="trans_date" 
                                                       value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                                            </div>
                                            
                                            <!-- جهة الاتصال -->
                                            <div class="col-md-6">
                                                <label class="form-label" id="contact_label">الجهة <span class="required-star">*</span></label>
                                                <div class="input-group">
                                                    <select class="form-select" id="contact_id" name="contact_id">
                                                        <option value="">-- اختر الجهة --</option>
                                                        <?php foreach($contacts as $contact): ?>
                                                        <option value="<?php echo $contact['contact_id']; ?>"
                                                                data-type="<?php echo $contact['contact_type']; ?>">
                                                            <?php echo htmlspecialchars($contact['contact_name']); ?>
                                                            (<?php echo $contact['contact_type'] == 'supplier' ? 'مورد' : 
                                                                   ($contact['contact_type'] == 'customer' ? 'عميل' : 'مقاول'); ?>)
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addContactModal">
                                                        <i class="bi bi-plus-lg"></i>
                                                    </button>
                                                </div>
                                                <small class="text-muted" id="contact_note">مطلوب لجميع الحركات ماعدا التحويل</small>
                                            </div>
                                            
                                            <!-- المشروع -->
                                            <div class="col-md-6">
                                                <label class="form-label">المشروع (اختياري)</label>
                                                <select class="form-select" id="project_id" name="project_id">
                                                    <option value="">-- لا يوجد مشروع --</option>
                                                    <?php foreach($projects as $project): ?>
                                                    <option value="<?php echo $project['project_id']; ?>">
                                                        <?php echo htmlspecialchars($project['project_name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <!-- المخازن (للتحويل) -->
                                            <div class="col-md-6 d-none" id="from_warehouse_field">
                                                <label class="form-label">من مخزن <span class="required-star">*</span></label>
                                                <select class="form-select" id="from_warehouse_id" name="from_warehouse_id">
                                                    <option value="">-- اختر المخزن المصدر --</option>
                                                    <?php foreach($warehouses as $warehouse): ?>
                                                    <option value="<?php echo $warehouse['warehouse_id']; ?>">
                                                        <?php echo htmlspecialchars($warehouse['warehouse_name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-6 d-none" id="to_warehouse_field">
                                                <label class="form-label">إلى مخزن <span class="required-star">*</span></label>
                                                <select class="form-select" id="to_warehouse_id" name="to_warehouse_id">
                                                    <option value="">-- اختر المخزن الهدف --</option>
                                                    <?php foreach($warehouses as $warehouse): ?>
                                                    <option value="<?php echo $warehouse['warehouse_id']; ?>">
                                                        <?php echo htmlspecialchars($warehouse['warehouse_name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <!-- المخازن (للأنواع الأخرى) -->
                                            <div class="col-md-6" id="warehouse_field">
                                                <label class="form-label">المخزن</label>
                                                <select class="form-select" id="warehouse_id" name="warehouse_id">
                                                    <option value="">-- المخزن الرئيسي --</option>
                                                    <?php foreach($warehouses as $warehouse): ?>
                                                    <option value="<?php echo $warehouse['warehouse_id']; ?>">
                                                        <?php echo htmlspecialchars($warehouse['warehouse_name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <!-- الملاحظات -->
                                            <div class="col-12">
                                                <label class="form-label">ملاحظات (اختياري)</label>
                                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                                          placeholder="أي ملاحظات إضافية حول الحركة..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- تفاصيل الأصناف -->
                                <div class="card transaction-card">
                                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <i class="bi bi-box-seam me-2"></i> تفاصيل الأصناف
                                        </h5>
                                        <span class="badge bg-info" id="items_count">0 أصناف</span>
                                    </div>
                                    <div class="card-body">
                                        <!-- عناصر الأصناف المضاف -->
                                        <div id="items_container">
                                            <!-- سيتم إضافة الصفوف هنا ديناميكياً -->
                                        </div>
                                        
                                        <!-- زر إضافة صنف -->
                                        <div class="text-center mt-4">
                                            <button type="button" class="btn btn-add-item w-100 py-3" onclick="addItemRow()" id="add_item_btn">
                                                <i class="bi bi-plus-circle me-2"></i> إضافة صنف جديد
                                            </button>
                                        </div>
                                        
                                        <!-- ملخص الحركة -->
                                        <div class="row mt-4 pt-3 border-top">
                                            <div class="col-md-6">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span>عدد الأصناف:</span>
                                                    <strong id="total_items">0</strong>
                                                </div>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span>إجمالي الكمية:</span>
                                                    <strong id="total_quantity">0</strong>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span>الإجمالي الفرعي:</span>
                                                    <strong id="subtotal">0.00</strong>
                                                </div>
                                                <div class="d-flex justify-content-between mb-3">
                                                    <span>الضريبة (0%):</span>
                                                    <strong id="tax">0.00</strong>
                                                </div>
                                                <div class="d-flex justify-content-between fs-5 fw-bold text-primary">
                                                    <span>الإجمالي النهائي:</span>
                                                    <strong id="total_amount">0.00</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- اللوحة الجانبية -->
                            <div class="col-lg-4">
                                <!-- ملخص الإجراءات -->
                                <div class="card transaction-card mb-4">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">
                                            <i class="bi bi-lightning-charge me-2"></i> إجراءات سريعة
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-primary btn-lg" id="submit_btn">
                                                <i class="bi bi-check-circle me-2"></i> حفظ الحركة
                                            </button>
                                            <button type="button" onclick="resetForm()" class="btn btn-outline-secondary">
                                                <i class="bi bi-arrow-clockwise me-2"></i> مسح النموذج
                                            </button>
                                            <button type="button" onclick="printTransaction()" class="btn btn-outline-success" id="print_btn" disabled>
                                                <i class="bi bi-printer me-2"></i> طباعة الفاتورة
                                            </button>
                                        </div>
                                        
                                        <div class="mt-4">
                                            <h6 class="border-bottom pb-2">نصائح سريعة:</h6>
                                            <ul class="list-unstyled small">
                                                <li class="mb-2"><i class="bi bi-info-circle text-info me-2"></i> تأكد من اختيار نوع الحركة أولاً</li>
                                                <li class="mb-2"><i class="bi bi-info-circle text-info me-2"></i> بالنسبة للكابلات، أدخل قراءة العداد</li>
                                                <li class="mb-2"><i class="bi bi-info-circle text-info me-2"></i> يمكنك إضافة أكثر من صنف للحركة</li>
                                                <li><i class="bi bi-info-circle text-info me-2"></i> سيتم تحديث المخزون تلقائياً بعد الحفظ</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- معلومات حول الحركة -->
                                <div class="card transaction-card">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">
                                            <i class="bi bi-info-circle me-2"></i> معلومات حول أنواع الحركات
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <span class="badge bg-success transaction-type-badge">وارد</span>
                                            <p class="small mb-2 mt-1">شراء منتجات من مورد (يزيد المخزون)</p>
                                        </div>
                                        <div class="mb-3">
                                            <span class="badge bg-danger transaction-type-badge">صادر</span>
                                            <p class="small mb-2">بيع منتجات للعميل (ينقص المخزون)</p>
                                        </div>
                                        <div class="mb-3">
                                            <span class="badge bg-warning text-dark transaction-type-badge">صرف</span>
                                            <p class="small mb-2">استخدام منتجات في مشروع (ينقص المخزون)</p>
                                        </div>
                                        <div class="mb-3">
                                            <span class="badge bg-info transaction-type-badge">مرتجع</span>
                                            <p class="small mb-2">إرجاع منتجات من العميل (يزيد المخزون)</p>
                                        </div>
                                        <div>
                                            <span class="badge bg-secondary transaction-type-badge">تحويل</span>
                                            <p class="small mb-0">نقل منتجات بين مخزنين (لا يؤثر على إجمالي المخزون)</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- الفوتر -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- مودال إضافة جهة اتصال جديدة -->
    <div class="modal fade" id="addContactModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة جهة اتصال جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="quickContactForm">
                        <div class="mb-3">
                            <label class="form-label">اسم الجهة <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="contact_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">نوع الجهة <span class="required-star">*</span></label>
                            <select class="form-select" id="contact_type" required>
                                <option value="">-- اختر النوع --</option>
                                <option value="supplier">مورد</option>
                                <option value="customer">عميل</option>
                                <option value="contractor">مقاول</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">رقم الهاتف</label>
                            <input type="tel" class="form-control" id="contact_phone">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" onclick="saveQuickContact()">حفظ</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
    const products = <?php echo json_encode(array_values($products_data), JSON_UNESCAPED_UNICODE); ?>;
    
    let itemCounter = 0;
    let currentTransactionId = null;
    
    // تهيئة الصفحة
    document.addEventListener('DOMContentLoaded', function() {
        // إضافة أول صف تلقائياً
        addItemRow();
        
        // تغيير تسمية جهة الاتصال حسب نوع الحركة
        document.getElementById('trans_type').addEventListener('change', function() {
            updateContactLabel();
            toggleWarehouseFields();
        });
        
        // التحقق من البيانات عند التحميل
        if(products.length === 0) {
            document.getElementById('add_item_btn').disabled = true;
            document.getElementById('add_item_btn').innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>لا توجد منتجات';
        }
        
        // تحديث الملصق عند التحميل
        updateContactLabel();
    });
    
    // تحديث تسمية جهة الاتصال
    function updateContactLabel() {
        const type = document.getElementById('trans_type').value;
        let label = "الجهة";
        
        switch(type) {
            case 'in': label = "المورد"; break;
            case 'out': label = "العميل"; break;
            case 'issue': label = "المقاول / المشروع"; break;
            case 'return': label = "العميل (مرتجع)"; break;
            case 'transfer': label = "الموظف / القسم"; break;
        }
        
        document.getElementById('contact_label').innerHTML = `${label} <span class="required-star">*</span>`;
        
        // تحديث الملاحظة
        const note = document.getElementById('contact_note');
        if(type === 'transfer') {
            note.textContent = 'غير مطلوب لنوع التحويل';
            note.className = 'text-success';
        } else {
            note.textContent = 'مطلوب لجميع الحركات ماعدا التحويل';
            note.className = 'text-muted';
        }
    }
    
    // إظهار/إخفاء حقول المخازن
    function toggleWarehouseFields() {
        const type = document.getElementById('trans_type').value;
        const fromField = document.getElementById('from_warehouse_field');
        const toField = document.getElementById('to_warehouse_field');
        const warehouseField = document.getElementById('warehouse_field');
        const contactSelect = document.getElementById('contact_id');
        
        if(type === 'transfer') {
            fromField.classList.remove('d-none');
            toField.classList.remove('d-none');
            warehouseField.classList.add('d-none');
            contactSelect.required = false;
        } else {
            fromField.classList.add('d-none');
            toField.classList.add('d-none');
            warehouseField.classList.remove('d-none');
            contactSelect.required = true;
        }
    }
    
    // إضافة صف جديد لصنف
    function addItemRow(product_id = '', unit_id = '', quantity = 1) {
        itemCounter++;
        const itemId = `item_${itemCounter}`;
        
        const html = `
        <div class="item-row" id="${itemId}">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small">الصنف <span class="required-star">*</span></label>
                    <select class="form-select product-select" name="items[${itemCounter}][product_id]" required
                            onchange="updateUnits(${itemCounter}, this.value)">
                        <option value="">-- اختر الصنف --</option>
                        ${products.map(p => `
                            <option value="${p.id}" ${p.id == product_id ? 'selected' : ''}>
                                ${p.name} ${p.code ? `(${p.code})` : ''}
                            </option>
                        `).join('')}
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label small">الوحدة</label>
                    <select class="form-select unit-select" name="items[${itemCounter}][unit_id]"
                            onchange="updatePrice(${itemCounter}, this.value)">
                        <option value="">-- اختر الوحدة --</option>
                        <!-- سيتم ملؤها بالوحدات -->
                    </select>
                    <div class="unit-price-display" id="price_display_${itemCounter}"></div>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label small">الكمية <span class="required-star">*</span></label>
                    <input type="number" class="form-control" name="items[${itemCounter}][quantity]" 
                           step="0.001" min="0.001" value="${quantity}" required
                           onchange="calculateItemTotal(${itemCounter})">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label small">الإجمالي</label>
                    <div class="input-group">
                        <input type="number" class="form-control total-price" 
                               id="total_${itemCounter}" readonly value="0">
                        <span class="input-group-text">ج.م</span>
                    </div>
                </div>
                
                <div class="col-md-12 mt-2" id="cable_fields_${itemCounter}" style="display:none;">
                    <div class="cable-readings">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small">قراءة البداية (متر)</label>
                                <input type="number" class="form-control cable-start" 
                                       name="items[${itemCounter}][start_read]" step="0.01" value="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">قراءة النهاية (متر)</label>
                                <input type="number" class="form-control cable-end" 
                                       name="items[${itemCounter}][end_read]" step="0.01" value="0"
                                       onchange="calculateCableLength(${itemCounter})">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-12 mt-2">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItemRow('${itemId}')">
                        <i class="bi bi-trash me-1"></i> حذف الصنف
                    </button>
                </div>
            </div>
        </div>
        `;
        
        document.getElementById('items_container').insertAdjacentHTML('beforeend', html);
        
        // إذا كان هناك منتج محدد، تحديث وحداته
        if(product_id) {
            updateUnits(itemCounter, product_id);
            if(unit_id) {
                setTimeout(() => {
                    document.querySelector(`#${itemId} .unit-select`).value = unit_id;
                    updatePrice(itemCounter, unit_id);
                }, 100);
            }
        }
        
        updateCounters();
    }
    
    // تحديث قائمة الوحدات
    function updateUnits(itemId, productId) {
        const product = products.find(p => p.id == productId);
        const unitSelect = document.querySelector(`#item_${itemId} .unit-select`);
        const cableFields = document.getElementById(`cable_fields_${itemId}`);
        
        unitSelect.innerHTML = '<option value="">-- اختر الوحدة --</option>';
        
        if(product && product.units.length > 0) {
            product.units.forEach(unit => {
                const option = document.createElement('option');
                option.value = unit.unit_id;
                option.textContent = `${unit.unit_name} (${unit.conversion_factor})`;
                option.dataset.price = unit.unit_price;
                unitSelect.appendChild(option);
            });
            
            // تحديد الوحدة الأساسية
            const baseUnit = product.units.find(u => u.is_base_unit == 1);
            if(baseUnit) {
                unitSelect.value = baseUnit.unit_id;
                updatePrice(itemId, baseUnit.unit_id);
            }
            
            // إظهار/إخفاء حقول الكابل
            cableFields.style.display = product.is_cable == 1 ? 'block' : 'none';
        }
    }
    
    // تحديث السعر
    function updatePrice(itemId, unitId) {
        const productSelect = document.querySelector(`#item_${itemId} .product-select`);
        const productId = productSelect.value;
        const product = products.find(p => p.id == productId);
        
        if(product && unitId) {
            const unit = product.units.find(u => u.unit_id == unitId);
            if(unit) {
                document.getElementById(`price_display_${itemId}`).innerHTML = 
                    `السعر: <strong>${unit.unit_price.toFixed(2)} ج.م</strong>`;
                document.querySelector(`#item_${itemId} .unit-select`).dataset.price = unit.unit_price;
                calculateItemTotal(itemId);
            }
        }
    }
    
    // حساب إجمالي الصنف
    function calculateItemTotal(itemId) {
        const quantityInput = document.querySelector(`#item_${itemId} input[name="items[${itemId}][quantity]"]`);
        const unitSelect = document.querySelector(`#item_${itemId} .unit-select`);
        const totalInput = document.getElementById(`total_${itemId}`);
        
        const quantity = parseFloat(quantityInput.value) || 0;
        const unitPrice = parseFloat(unitSelect.dataset.price) || 0;
        totalInput.value = (quantity * unitPrice).toFixed(2);
        updateTotals();
    }
    
    // حساب طول الكابل
    function calculateCableLength(itemId) {
        const startInput = document.querySelector(`#item_${itemId} .cable-start`);
        const endInput = document.querySelector(`#item_${itemId} .cable-end`);
        const quantityInput = document.querySelector(`#item_${itemId} input[name="items[${itemId}][quantity]"]`);
        
        const start = parseFloat(startInput.value) || 0;
        const end = parseFloat(endInput.value) || 0;
        
        if(end > start) {
            quantityInput.value = (end - start).toFixed(3);
            calculateItemTotal(itemId);
        }
    }
    
    // حذف صف الصنف
    function removeItemRow(itemId) {
        if(document.querySelectorAll('.item-row').length > 1) {
            document.getElementById(itemId).remove();
            updateCounters();
            updateTotals();
        } else {
            alert('يجب أن تحتوي الحركة على صنف واحد على الأقل');
        }
    }
    
    // تحديث العدادات
    function updateCounters() {
        const items = document.querySelectorAll('.item-row');
        document.getElementById('items_count').textContent = `${items.length} أصناف`;
        document.getElementById('total_items').textContent = items.length;
    }
    
    // تحديث الإجماليات
    function updateTotals() {
        let totalQuantity = 0;
        let subtotal = 0;
        
        document.querySelectorAll('.item-row').forEach(row => {
            const quantity = parseFloat(row.querySelector('input[name*="quantity"]').value) || 0;
            const total = parseFloat(row.querySelector('.total-price').value) || 0;
            totalQuantity += quantity;
            subtotal += total;
        });
        
        const tax = 0;
        const totalAmount = subtotal + tax;
        
        document.getElementById('total_quantity').textContent = totalQuantity.toFixed(3);
        document.getElementById('subtotal').textContent = subtotal.toFixed(2);
        document.getElementById('tax').textContent = tax.toFixed(2);
        document.getElementById('total_amount').textContent = totalAmount.toFixed(2);
        document.getElementById('total_amount_input').value = totalAmount;
    }
    
    // حفظ جهة الاتصال السريعة
    function saveQuickContact() {
        const name = document.getElementById('contact_name').value.trim();
        const type = document.getElementById('contact_type').value;
        const phone = document.getElementById('contact_phone').value.trim();
        
        if(!name || !type) {
            alert('الرجاء إدخال اسم ونوع الجهة');
            return;
        }
        
        fetch('api/save_quick_contact.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({contact_name: name, contact_type: type, phone_number: phone})
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                const select = document.getElementById('contact_id');
                const option = document.createElement('option');
                option.value = data.contact_id;
                option.text = `${name} (${type == 'supplier' ? 'مورد' : type == 'customer' ? 'عميل' : 'مقاول'})`;
                option.dataset.type = type;
                select.appendChild(option);
                select.value = data.contact_id;
                
                bootstrap.Modal.getInstance(document.getElementById('addContactModal')).hide();
                document.getElementById('quickContactForm').reset();
                alert('تم إضافة الجهة بنجاح');
            } else {
                alert('حدث خطأ: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ في الاتصال بالخادم');
        });
    }
    
    // معالجة إرسال النموذج
    document.getElementById('transactionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const transType = document.getElementById('trans_type').value;
        if (!transType) {
            alert('الرجاء اختيار نوع الحركة');
            document.getElementById('trans_type').focus();
            return;
        }
        
        if (transType !== 'transfer') {
            const contactId = document.getElementById('contact_id').value;
            if (!contactId) {
                alert('الرجاء اختيار جهة الاتصال');
                document.getElementById('contact_id').focus();
                return;
            }
        }
        
        const items = document.querySelectorAll('.item-row');
        if(items.length === 0) {
            alert('يجب إضافة صنف واحد على الأقل');
            return;
        }
        
        let valid = true;
        items.forEach(row => {
            const productSelect = row.querySelector('.product-select');
            const quantityInput = row.querySelector('input[name*="quantity"]');
            
            if(!productSelect.value) {
                valid = false;
                productSelect.focus();
            }
            
            const quantity = parseFloat(quantityInput.value);
            if(!quantity || quantity <= 0) {
                valid = false;
                quantityInput.focus();
            }
        });
        
        if(!valid) {
            alert('الرجاء التأكد من اختيار جميع الأصناف وإدخال كميات صحيحة');
            return;
        }
        
        // تحديث الإجمالي قبل الإرسال
        updateTotals();
        
        const submitBtn = document.getElementById('submit_btn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="bi bi-hourglass me-2"></i>جارٍ الحفظ...';
        submitBtn.disabled = true;
        
        const formData = new FormData(this);
        
        fetch('api/save_transaction.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('تم حفظ الحركة بنجاح');
                document.getElementById('print_btn').disabled = false;
                currentTransactionId = data.trans_id;
                
                if(confirm('تم حفظ الحركة بنجاح. هل تريد إضافة حركة جديدة؟')) {
                    resetForm();
                } else {
                    window.location.href = 'transactions.php';
                }
            } else {
                alert('حدث خطأ: ' + (data.message || 'غير معروف'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ في الاتصال بالخادم');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
    
    // طباعة الفاتورة
    function printTransaction() {
        if(currentTransactionId) {
            window.open('print_transaction.php?id=' + currentTransactionId, '_blank');
        }
    }
    
    // مسح النموذج
    function resetForm() {
        if(confirm('هل تريد مسح جميع البيانات؟ سيتم فقدان كل ما أدخلته.')) {
            document.getElementById('transactionForm').reset();
            document.getElementById('items_container').innerHTML = '';
            addItemRow();
            updateCounters();
            updateTotals();
            document.getElementById('print_btn').disabled = true;
            currentTransactionId = null;
            document.getElementById('trans_date').value = '<?php echo date('Y-m-d\TH:i'); ?>';
        }
    }
    </script>
</body>
</html>