-- إضافة حالة جديدة لموافقة الجمعية على التبرعات
-- هذا الملف يحدث جدول donations لإضافة حالة pending_charity_approval

ALTER TABLE `donations`
MODIFY COLUMN `status` ENUM(
    'pending_charity_approval',
    'available',
    'reserved',
    'with_charity',
    'delivered',
    'completed',
    'cancelled'
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending_charity_approval';

-- إضافة حقول جديدة للموافقة
ALTER TABLE `donations`
ADD COLUMN `charity_approved_at` TIMESTAMP NULL DEFAULT NULL AFTER `received_by_charity_at`,
ADD COLUMN `charity_approval_notes` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `charity_notes`;
