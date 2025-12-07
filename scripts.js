// الدوال الأساسية للموقع
document.addEventListener('DOMContentLoaded', function() {
    initializeComponents();
    setupFormValidation();
    setupNotifications();
    setupImagePreviews();
    setupModals();
    setupMobileSidebar();
});

// تهيئة المكونات
function initializeComponents() {
    // إخفاء رسائل التنبيه تلقائياً
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            fadeOut(alert);
        }, 5000);
    });

    // تفعيل التولتيب
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(tooltip => {
        tooltip.addEventListener('mouseenter', showTooltip);
        tooltip.addEventListener('mouseleave', hideTooltip);
    });

    // تفعيل أزرار التأكيد
    const confirmButtons = document.querySelectorAll('.btn-confirm');
    confirmButtons.forEach(btn => {
        btn.addEventListener('click', confirmAction);
    });
}

// إعداد التحقق من النماذج
function setupFormValidation() {
    const forms = document.querySelectorAll('form[data-validate="true"]');
    forms.forEach(form => {
        form.addEventListener('submit', validateForm);
        
        // التحقق من الحقول أثناء الكتابة
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('blur', validateField);
            input.addEventListener('input', clearFieldError);
        });
    });
}

// التحقق من النموذج
function validateForm(e) {
    e.preventDefault();
    const form = e.target;
    let isValid = true;
    
    // التحقق من جميع الحقول المطلوبة
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        if (!validateField({ target: field })) {
            isValid = false;
        }
    });
    
    // التحقق من كلمة المرور
    const password = form.querySelector('#password');
    const confirmPassword = form.querySelector('#confirm_password');
    if (password && confirmPassword) {
        if (password.value !== confirmPassword.value) {
            showFieldError(confirmPassword, 'كلمات المرور غير متطابقة');
            isValid = false;
        }
    }
    
    // التحقق من البريد الإلكتروني
    const email = form.querySelector('#email');
    if (email && !isValidEmail(email.value)) {
        showFieldError(email, 'يرجى إدخال بريد إلكتروني صحيح');
        isValid = false;
    }
    
    // التحقق من رقم الهاتف
    const phone = form.querySelector('#phone');
    if (phone && !isValidPhone(phone.value)) {
        showFieldError(phone, 'يرجى إدخال رقم هاتف صحيح');
        isValid = false;
    }
    
    if (isValid) {
        // إضافة مؤشر التحميل
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading"></span> جاري الإرسال...';
        }
        
        form.submit();
    }
    
    return isValid;
}

// التحقق من حقل واحد
function validateField(e) {
    const field = e.target;
    const value = field.value.trim();
    
    clearFieldError(field);
    
    if (field.hasAttribute('required') && !value) {
        showFieldError(field, 'هذا الحقل مطلوب');
        return false;
    }
    
    if (field.type === 'email' && value && !isValidEmail(value)) {
        showFieldError(field, 'يرجى إدخال بريد إلكتروني صحيح');
        return false;
    }
    
    if (field.type === 'tel' && value && !isValidPhone(value)) {
        showFieldError(field, 'يرجى إدخال رقم هاتف صحيح');
        return false;
    }
    
    if (field.hasAttribute('minlength') && value.length < field.getAttribute('minlength')) {
        showFieldError(field, `يجب أن يكون طول النص على الأقل ${field.getAttribute('minlength')} أحرف`);
        return false;
    }
    
    return true;
}

// إظهار خطأ الحقل
function showFieldError(field, message) {
    field.classList.add('is-invalid');
    
    let errorElement = field.parentNode.querySelector('.invalid-feedback');
    if (!errorElement) {
        errorElement = document.createElement('div');
        errorElement.className = 'invalid-feedback';
        field.parentNode.appendChild(errorElement);
    }
    
    errorElement.textContent = message;
    errorElement.style.display = 'block';
}

// إخفاء خطأ الحقل
function clearFieldError(field) {
    if (typeof field === 'object' && field.target) {
        field = field.target;
    }
    
    field.classList.remove('is-invalid');
    const errorElement = field.parentNode.querySelector('.invalid-feedback');
    if (errorElement) {
        errorElement.style.display = 'none';
    }
}

// التحقق من صحة البريد الإلكتروني
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// التحقق من صحة رقم الهاتف
function isValidPhone(phone) {
    const phoneRegex = /^[0-9+\-\s()]{10,}$/;
    return phoneRegex.test(phone);
}

// إعداد الإشعارات
function setupNotifications() {
    // إنشاء حاوية الإشعارات
    if (!document.querySelector('.notifications-container')) {
        const container = document.createElement('div');
        container.className = 'notifications-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1060;
            max-width: 300px;
        `;
        document.body.appendChild(container);
    }
}

// إظهار إشعار
function showNotification(message, type = 'info', duration = 5000) {
    const container = document.querySelector('.notifications-container');
    const notification = document.createElement('div');
    
    notification.className = `notification alert alert-${type} fade-in`;
    notification.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span>${message}</span>
            <button type="button" class="close" onclick="this.parentElement.parentElement.remove()">
                <span>&times;</span>
            </button>
        </div>
    `;
    
    container.appendChild(notification);
    
    // إزالة الإشعار تلقائياً
    setTimeout(() => {
        if (notification.parentNode) {
            fadeOut(notification);
        }
    }, duration);
}

// إعداد معاينة الصور
function setupImagePreviews() {
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    imageInputs.forEach(input => {
        input.addEventListener('change', handleImagePreview);
    });
}

// معالجة معاينة الصور
function handleImagePreview(e) {
    const input = e.target;
    const previewContainer = input.parentNode.querySelector('.image-preview');
    
    if (!previewContainer) {
        const container = document.createElement('div');
        container.className = 'image-preview mt-2';
        input.parentNode.appendChild(container);
    }
    
    const container = input.parentNode.querySelector('.image-preview');
    container.innerHTML = '';
    
    const files = Array.from(input.files);
    files.forEach((file, index) => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const imgWrapper = document.createElement('div');
                imgWrapper.className = 'image-preview-item';
                imgWrapper.style.cssText = `
                    display: inline-block;
                    position: relative;
                    margin: 5px;
                    border: 2px solid #dee2e6;
                    border-radius: 8px;
                    overflow: hidden;
                `;
                
                imgWrapper.innerHTML = `
                    <img src="${e.target.result}" style="width: 100px; height: 100px; object-fit: cover;">
                    <button type="button" class="btn btn-danger btn-sm" 
                            style="position: absolute; top: 5px; right: 5px; padding: 2px 6px;"
                            onclick="removeImagePreview(this, ${index})">×</button>
                `;
                
                container.appendChild(imgWrapper);
            };
            reader.readAsDataURL(file);
        }
    });
}

// إزالة معاينة صورة
function removeImagePreview(button, index) {
    const input = button.closest('.form-group').querySelector('input[type="file"]');
    const dt = new DataTransfer();
    
    Array.from(input.files).forEach((file, i) => {
        if (i !== index) {
            dt.items.add(file);
        }
    });
    
    input.files = dt.files;
    button.parentElement.remove();
}

// إعداد النوافذ المنبثقة
function setupModals() {
    const modalTriggers = document.querySelectorAll('[data-modal]');
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const modalId = this.getAttribute('data-modal');
            openModal(modalId);
        });
    });
    
    // إغلاق النوافذ المنبثقة
    const closeButtons = document.querySelectorAll('.modal .close, .modal [data-dismiss="modal"]');
    closeButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            closeModal(modal.id);
        });
    });
    
    // إغلاق النافذة عند النقر خارجها
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
}

// فتح نافذة منبثقة
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        
        // إضافة تأثير الظهور
        setTimeout(() => {
            modal.classList.add('fade-in');
        }, 10);
    }
}

// إغلاق نافذة منبثقة
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
        modal.classList.remove('fade-in');
    }
}

// تأكيد الإجراء
function confirmAction(e) {
    e.preventDefault();
    const message = this.getAttribute('data-message') || 'هل أنت متأكد من هذا الإجراء؟';
    
    if (confirm(message)) {
        const href = this.getAttribute('href');
        const form = this.getAttribute('data-form');
        
        if (href) {
            window.location.href = href;
        } else if (form) {
            document.getElementById(form).submit();
        }
    }
}

// إخفاء العنصر تدريجياً
function fadeOut(element) {
    element.style.transition = 'opacity 0.5s';
    element.style.opacity = '0';
    setTimeout(() => {
        if (element.parentNode) {
            element.parentNode.removeChild(element);
        }
    }, 500);
}

// إظهار العنصر تدريجياً
function fadeIn(element) {
    element.style.opacity = '0';
    element.style.transition = 'opacity 0.5s';
    setTimeout(() => {
        element.style.opacity = '1';
    }, 10);
}

// البحث المباشر
function setupLiveSearch() {
    const searchInputs = document.querySelectorAll('.live-search');
    searchInputs.forEach(input => {
        let timeout;
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                performLiveSearch(this.value, this.getAttribute('data-target'));
            }, 300);
        });
    });
}

// تنفيذ البحث المباشر
function performLiveSearch(query, target) {
    const resultsContainer = document.querySelector(target);
    if (!resultsContainer) return;
    
    // إظهار مؤشر التحميل
    resultsContainer.innerHTML = '<div class="text-center p-3"><span class="loading"></span> جاري البحث...</div>';
    
    // إرسال طلب AJAX
    fetch(`search.php?q=${encodeURIComponent(query)}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.text())
    .then(data => {
        resultsContainer.innerHTML = data;
    })
    .catch(error => {
        resultsContainer.innerHTML = '<div class="alert alert-danger">حدث خطأ في البحث</div>';
    });
}

// إعداد التحديث التلقائي
function setupAutoRefresh() {
    const autoRefreshElements = document.querySelectorAll('[data-auto-refresh]');
    autoRefreshElements.forEach(element => {
        const interval = parseInt(element.getAttribute('data-auto-refresh')) * 1000;
        const url = element.getAttribute('data-url');
        
        setInterval(() => {
            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(data => {
                element.innerHTML = data;
            });
        }, interval);
    });
}

// إعداد رفع الملفات بالسحب والإفلات
function setupDragAndDrop() {
    const dropZones = document.querySelectorAll('.drop-zone');
    dropZones.forEach(zone => {
        const input = zone.querySelector('input[type="file"]');
        
        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });
        
        zone.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });
        
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            
            const files = e.dataTransfer.files;
            if (input && files.length > 0) {
                input.files = files;
                input.dispatchEvent(new Event('change'));
            }
        });
    });
}

// دالة مساعدة لإرسال طلبات AJAX
function sendAjaxRequest(url, method = 'GET', data = null) {
    return fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': getCSRFToken()
        },
        body: data ? JSON.stringify(data) : null
    });
}

// الحصول على رمز CSRF
function getCSRFToken() {
    const tokenElement = document.querySelector('meta[name="csrf-token"]');
    return tokenElement ? tokenElement.getAttribute('content') : '';
}

// تحويل النموذج إلى كائن
function formToObject(form) {
    const formData = new FormData(form);
    const object = {};
    
    for (let [key, value] of formData.entries()) {
        if (object[key]) {
            if (Array.isArray(object[key])) {
                object[key].push(value);
            } else {
                object[key] = [object[key], value];
            }
        } else {
            object[key] = value;
        }
    }
    
    return object;
}

// إعداد العد التنازلي
function setupCountdown(element, endDate) {
    const countdownInterval = setInterval(() => {
        const now = new Date().getTime();
        const distance = endDate - now;
        
        if (distance < 0) {
            clearInterval(countdownInterval);
            element.innerHTML = 'انتهت';
            return;
        }
        
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
        element.innerHTML = `${days} يوم ${hours} ساعة ${minutes} دقيقة ${seconds} ثانية`;
    }, 1000);
}

// دوال الأدوات المساعدة
const utils = {
    // تنسيق التاريخ
    formatDate: function(date, format = 'Y-m-d') {
        const d = new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        
        return format
            .replace('Y', year)
            .replace('m', month)
            .replace('d', day);
    },
    
    // تنسيق الأرقام
    formatNumber: function(num) {
        return new Intl.NumberFormat('ar-SA').format(num);
    },
    
    // اختصار النص
    truncateText: function(text, length = 100) {
        return text.length > length ? text.substring(0, length) + '...' : text;
    },
    
    // تأخير التنفيذ
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

// إعداد السايد بار للجوال
function setupMobileSidebar() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileSidebar = document.getElementById('mobileSidebar');
    const mobileSidebarClose = document.getElementById('mobileSidebarClose');
    const mobileSidebarOverlay = document.getElementById('mobileSidebarOverlay');

    if (!mobileMenuToggle || !mobileSidebar || !mobileSidebarClose || !mobileSidebarOverlay) {
        return;
    }

    // فتح السايد بار
    function openSidebar() {
        mobileSidebar.classList.add('active');
        mobileSidebarOverlay.classList.add('active');
        mobileMenuToggle.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // إغلاق السايد بار
    function closeSidebar() {
        mobileSidebar.classList.remove('active');
        mobileSidebarOverlay.classList.remove('active');
        mobileMenuToggle.classList.remove('active');
        document.body.style.overflow = '';
    }

    // عند الضغط على زر القائمة
    mobileMenuToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        if (mobileSidebar.classList.contains('active')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    // عند الضغط على زر الإغلاق
    mobileSidebarClose.addEventListener('click', closeSidebar);

    // عند الضغط على الخلفية المعتمة
    mobileSidebarOverlay.addEventListener('click', closeSidebar);

    // إغلاق السايد بار عند الضغط على أي رابط داخله
    const sidebarLinks = mobileSidebar.querySelectorAll('a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            // إضافة تأخير بسيط لتحسين التجربة
            setTimeout(closeSidebar, 200);
        });
    });

    // إغلاق السايد بار عند الضغط على مفتاح ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && mobileSidebar.classList.contains('active')) {
            closeSidebar();
        }
    });
}

// تصدير الدوال للاستخدام العام
window.DonationSystem = {
    showNotification,
    openModal,
    closeModal,
    confirmAction,
    fadeIn,
    fadeOut,
    sendAjaxRequest,
    utils
};
