<?php

declare(strict_types=1);

/*
| رسائل أخطاء موديول Integrations.
| تُستهلك عبر __('integrations::errors.key') — المفاتيح تصف المعنى لا الصياغة.
*/

return [

    'provider_not_found' => 'مزوّد التكاملات المطلوب غير موجود (:provider_id).',

    'provider_inactive' => 'المزوّد «:key» غير مُفعَّل حاليًا ولا يمكن الارتباط به.',

    'connection_limit_reached' => 'بلغت المؤسسة الحد الأقصى للاتصالات على هذا المزوّد (الحد: :max).',

    'invalid_status_transition' => 'لا يمكن نقل الاتصال من حالة «:from» إلى حالة «:to».',

    'invalid_delivery_transition' => 'لا يمكن نقل الإيصال من حالة «:from» إلى حالة «:to».',

    'connection_not_found' => 'الاتصال المطلوب غير موجود (:connection_id).',

    'connection_not_active' => 'الاتصال ليس في حالة نشطة (الحالة الحالية: :status) ولا يقبل إرسال رسائل.',

    'only_dead_can_requeue' => 'يمكن إعادة الإحياء للإيصالات الميتة فقط (الحالة الحالية: :status).',

];
