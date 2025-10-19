<?php
require_once 'config/database.php';
require_once 'config/functions.php';

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    redirect('login.php');
}

$db = getDB();
$trip_id = getGet('trip_id');
$selected_seats = getGet('selected_seats');
$passengers_count = getGet('passengers', 1);

if (!$trip_id || !$selected_seats) {
    redirect('search.php');
}

// الحصول على تفاصيل الرحلة
$stmt = $db->prepare("
    SELECT t.*, 
           fc.name as from_city_name, 
           tc.name as to_city_name,
           bt.name as bus_type_name
    FROM trips t
    JOIN cities fc ON t.from_city_id = fc.id
    JOIN cities tc ON t.to_city_id = tc.id
    JOIN bus_types bt ON t.bus_type_id = bt.id
    WHERE t.id = ? AND t.status = 'active'
");

$stmt->execute([$trip_id]);
$trip = $stmt->fetch();

if (!$trip) {
    redirect('search.php');
}

$seats_array = explode(',', $selected_seats);
$total_amount = count($seats_array) * $trip['price'];

$error = '';
$success = '';

// معالجة الدفع
if (isPost()) {
    $passenger_names = getPost('passenger_names');
    $passenger_phones = getPost('passenger_phones');
    $passenger_emails = getPost('passenger_emails');
    $payment_method = getPost('payment_method');
    $card_number = getPost('card_number');
    $card_expiry = getPost('card_expiry');
    $card_cvv = getPost('card_cvv');
    $card_name = getPost('card_name');
    
    // التحقق من البيانات
    if (isPost()) {
    $passenger_names = getPost('passenger_names');
    $passenger_phones = getPost('passenger_phones');
    $passenger_emails = getPost('passenger_emails');
    $payment_method = getPost('payment_method');
    $card_number = getPost('card_number');
    $card_expiry = getPost('card_expiry');
    $card_cvv = getPost('card_cvv');
    $card_name = getPost('card_name');

    // التحقق من صحة البيانات
    if (empty($passenger_names) || empty($passenger_phones)) {
        $error = 'يرجى ملء بيانات جميع المسافرين';
    } elseif (empty($payment_method)) {
        $error = 'يرجى اختيار طريقة الدفع';
    } elseif ($payment_method === 'credit_card' && (empty($card_number) || empty($card_expiry) || empty($card_cvv) || empty($card_name))) {
        $error = 'يرجى ملء جميع بيانات البطاقة الائتمانية';
    } else {
        // تجهيز البيانات
        $names_array = array_filter(array_map('trim', explode(',', $passenger_names)));
        $phones_array = array_filter(array_map('trim', explode(',', $passenger_phones)));
        $emails_array = array_filter(array_map('trim', explode(',', $passenger_emails)));

        if (count($names_array) != $passengers_count || count($phones_array) != $passengers_count) {
            $error = 'عدد أسماء أو أرقام هواتف المسافرين غير مطابق للعدد المطلوب';
        } else {
            $booking_reference = generateBookingReference();
            $payment_status = ($payment_method === 'cash') ? 'pending' : 'paid';

            $passenger_names_str = implode(',', $names_array);
            $passenger_phones_str = implode(',', $phones_array);
            $passenger_emails_str = implode(',', $emails_array);

            try {
                $db->beginTransaction();

                // إدخال الحجز
                $stmt = $db->prepare("
                    INSERT INTO bookings 
                    (user_id, trip_id, seats_booked, seat_numbers, total_amount, 
                     payment_method, payment_status, booking_reference, 
                     passenger_names, passenger_phones, passenger_emails, booking_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $stmt->execute([
                    $_SESSION['user_id'],
                    $trip_id,
                    count($seats_array),
                    $selected_seats,
                    $total_amount,
                    $payment_method,
                    $payment_status,
                    $booking_reference,
                    $passenger_names_str,
                    $passenger_phones_str,
                    $passenger_emails_str
                ]);

                $booking_id = $db->lastInsertId();

                // إذا كانت طريقة الدفع بطاقة، أضف إلى جدول المدفوعات
                if ($payment_method === 'credit_card') {
                    $payment_stmt = $db->prepare("
                        INSERT INTO payments 
                        (booking_id, amount, payment_method, transaction_id, payment_status, payment_date)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");

                    $payment_stmt->execute([
                        $booking_id,
                        $total_amount,
                        'credit_card',
                        generateTransactionId(),
                        'completed'
                    ]);
                }

                $db->commit();

                // التوجيه لصفحة التأكيد
                redirect("booking_confirmation.php?reference=$booking_reference");

            } catch (Exception $e) {
                $db->rollBack();
                error_log("Booking error: " . $e->getMessage());
                $error = 'حدث خطأ أثناء معالجة الحجز. يرجى المحاولة مرة أخرى.';
            }
        }
    }
}

}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إكمال الدفع - BusGo</title>
    <link rel="stylesheet" href="css/enhanced-style.css">
    <style>
        .payment-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .payment-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            gap: 20px;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .step.completed {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .step.active {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }

        .step.pending {
            background: #f8f9fa;
            color: #6c757d;
            border: 2px solid #dee2e6;
        }

        .booking-summary {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 2px solid #dee2e6;
        }

        .summary-header {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 18px;
            color: #28a745;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
        }

        .passenger-form {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            border: 2px solid #dee2e6;
        }

        .passenger-header {
            font-weight: bold;
            color: #495057;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: bold;
            color: #495057;
            margin-bottom: 5px;
        }

        .form-input {
            padding: 12px 15px;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .payment-method {
            border: 2px solid #dee2e6;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .payment-method:hover {
            border-color: #007bff;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .payment-method.selected {
            border-color: #007bff;
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
        }

        .payment-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .credit-card-form {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            border: 2px solid #dee2e6;
            display: none;
        }

        .credit-card-form.active {
            display: block;
        }

        .card-input-group {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 15px;
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 18px 30px;
            border-radius: 15px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .payment-container {
                padding: 10px;
            }
            
            .step-indicator {
                flex-direction: column;
                gap: 10px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .payment-methods {
                grid-template-columns: 1fr;
            }
            
            .card-input-group {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <!-- مؤشر الخطوات -->
        <div class="step-indicator">
            <div class="step completed">
                <span>🔍</span>
                <span>البحث</span>
            </div>
            <div class="step completed">
                <span>🪑</span>
                <span>اختيار المقاعد</span>
            </div>
            <div class="step active">
                <span>💳</span>
                <span>الدفع</span>
            </div>
            <div class="step pending">
                <span>✅</span>
                <span>التأكيد</span>
            </div>
        </div>

        <!-- ملخص الحجز -->
        <div class="payment-card">
            <div class="booking-summary">
                <div class="summary-header">ملخص الحجز</div>
                
                <div class="summary-row">
                    <span>الرحلة:</span>
                    <span><?= htmlspecialchars($trip['from_city_name']) ?> ← <?= htmlspecialchars($trip['to_city_name']) ?></span>
                </div>
                
                <div class="summary-row">
                    <span>تاريخ السفر:</span>
                    <span><?= isset($trip["trip_date"]) ? formatDate($trip["trip_date"]) : "N/A" ?></span>
                </div>
                
                <div class="summary-row">
                    <span>وقت المغادرة:</span>
                    <span><?= formatTime($trip['departure_time']) ?></span>
                </div>
                
                <div class="summary-row">
                    <span>المقاعد المختارة:</span>
                    <span><?= htmlspecialchars($selected_seats) ?></span>
                </div>
                
                <div class="summary-row">
                    <span>عدد المسافرين:</span>
                    <span><?= $passengers_count ?> مسافر</span>
                </div>
                
                <div class="summary-row">
                    <span>المجموع الإجمالي:</span>
                    <span><?= formatPrice($total_amount) ?></span>
                </div>
            </div>
        </div>

        <!-- نموذج الدفع -->
        <div class="payment-card">
            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" id="payment-form">
                <!-- بيانات المسافرين -->
                <div class="form-section">
                    <div class="section-title">🧳 بيانات المسافرين</div>
                    
                    <div id="passengers-container">
                        <?php for ($i = 1; $i <= $passengers_count; $i++): ?>
                            <div class="passenger-form">
                                <div class="passenger-header">
                                    <span>👤</span>
                                    <span>المسافر رقم <?= $i ?> - مقعد <?= $seats_array[$i-1] ?></span>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">الاسم الكامل *</label>
                                        <input type="text" class="form-input passenger-name" 
                                               placeholder="أدخل الاسم الكامل" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">رقم الهاتف *</label>
                                        <input type="tel" class="form-input passenger-phone" 
                                               placeholder="مثال: 0123456789" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">البريد الإلكتروني</label>
                                        <input type="email" class="form-input passenger-email" 
                                               placeholder="example@email.com">
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- طرق الدفع -->
                <div class="form-section">
                    <div class="section-title">💳 طريقة الدفع</div>
                    
                    <div class="payment-methods">
                        <div class="payment-method" data-method="cash">
                            <div class="payment-icon">💵</div>
                            <div><strong>الدفع نقداً</strong></div>
                            <div style="font-size: 14px; color: #6c757d;">ادفع عند الصعود للباص</div>
                        </div>
                        
                        <div class="payment-method" data-method="credit_card">
                            <div class="payment-icon">💳</div>
                            <div><strong>بطاقة ائتمانية</strong></div>
                            <div style="font-size: 14px; color: #6c757d;">ادفع الآن بالبطاقة</div>
                        </div>
                    </div>
                    
                    <!-- نموذج البطاقة الائتمانية -->
                    <div class="credit-card-form" id="credit-card-form">
                        <div class="section-title">💳 بيانات البطاقة الائتمانية</div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">اسم حامل البطاقة</label>
                                <input type="text" name="card_name" class="form-input" 
                                       placeholder="الاسم كما هو مكتوب على البطاقة">
                            </div>
                        </div>
                        
                        <div class="card-input-group">
                            <div class="form-group">
                                <label class="form-label">رقم البطاقة</label>
                                <input type="text" name="card_number" class="form-input" 
                                       placeholder="1234 5678 9012 3456" maxlength="19">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">تاريخ الانتهاء</label>
                                <input type="text" name="card_expiry" class="form-input" 
                                       placeholder="MM/YY" maxlength="5">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">CVV</label>
                                <input type="text" name="card_cvv" class="form-input" 
                                       placeholder="123" maxlength="4">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الحقول المخفية -->
                <input type="hidden" name="passenger_names" id="passenger_names">
                <input type="hidden" name="passenger_phones" id="passenger_phones">
                <input type="hidden" name="passenger_emails" id="passenger_emails">
                <input type="hidden" name="payment_method" id="payment_method">

                <button type="submit" class="submit-btn">
                    تأكيد الحجز والدفع 🎫
                </button>
            </form>
        </div>
    </div>

    <script>
        // اختيار طريقة الدفع
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', function() {
                // إزالة التحديد من جميع الطرق
                document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('selected'));
                
                // تحديد الطريقة المختارة
                this.classList.add('selected');
                
                const selectedMethod = this.dataset.method;
                document.getElementById('payment_method').value = selectedMethod;
                
                // إظهار/إخفاء نموذج البطاقة الائتمانية
                const creditCardForm = document.getElementById('credit-card-form');
                if (selectedMethod === 'credit_card') {
                    creditCardForm.classList.add('active');
                } else {
                    creditCardForm.classList.remove('active');
                }
            });
        });

        // تنسيق رقم البطاقة الائتمانية
        document.querySelector('input[name="card_number"]')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });

        // تنسيق تاريخ انتهاء البطاقة
        document.querySelector('input[name="card_expiry"]')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });

        // معالجة إرسال النموذج
        document.getElementById('payment-form').addEventListener('submit', function(e) {
            // جمع بيانات المسافرين
            const names = [];
            const phones = [];
            const emails = [];
            
            document.querySelectorAll('.passenger-name').forEach(input => {
                if (input.value.trim()) names.push(input.value.trim());
            });
            
            document.querySelectorAll('.passenger-phone').forEach(input => {
                if (input.value.trim()) phones.push(input.value.trim());
            });
            
            document.querySelectorAll('.passenger-email').forEach(input => {
                emails.push(input.value.trim());
            });
            
            // التحقق من البيانات
            if (names.length !== <?= $passengers_count ?> || phones.length !== <?= $passengers_count ?>) {
                e.preventDefault();
                alert('يرجى ملء أسماء وأرقام هواتف جميع المسافرين');
                return;
            }
            
            if (!document.getElementById('payment_method').value) {
                e.preventDefault();
                alert('يرجى اختيار طريقة الدفع');
                return;
            }
            
            // تعبئة الحقول المخفية
            document.getElementById('passenger_names').value = names.join(',');
            document.getElementById('passenger_phones').value = phones.join(',');
            document.getElementById('passenger_emails').value = emails.join(',');
        });
    </script>
</body>
</html>

