<?php

return [
    'pied' => 'أُرسلت هذه الرسالة آلياً، يُرجى عدم الرد عليها. تُستخدم بياناتكم فقط لمعالجة طلبكم.',
    'confirmation_depot' => [
        'sujet' => 'ITASSEL — تم تسجيل تظلّمكم :reference',
        'salutation' => 'مرحباً :prenom :nom،',
        'intro' => 'تم تسجيل تظلّمكم وإحالته إلى المصلحة المعنية.',
        'reference_label' => 'مرجع المتابعة',
        'objet' => 'الموضوع',
        'suite' => 'احتفظوا بهذا المرجع. لمتابعة الملف، افتحوا « متابعة طلبي »: سيُرسل رمز تحقق إلى هذا العنوان.',
    ],
    'code_verification' => [
        'sujet' => 'ITASSEL — رمز التحقق',
        'salutation' => 'مرحباً،',
        'intro' => 'هذا رمز الاطلاع على الملف :reference:',
        'validite' => 'يصلح هذا الرمز لمدة :minutes دقائق ولا يُستخدم إلا مرة واحدة.',
        'ignore' => 'إذا لم تطلبوا ذلك، تجاهلوا هذه الرسالة.',
    ],
    'changement_statut' => [
        'sujet' => 'ITASSEL — تحديث ملفكم :reference',
        'salutation' => 'مرحباً :prenom :nom،',
        'intro' => 'تغيّرت حالة الملف :reference.',
        'statut_label' => 'الحالة الجديدة',
        'consulter' => 'يمكنكم الاطلاع على الملف في أي وقت:',
        'bouton' => 'متابعة طلبي',
    ],
    'demande_complement' => [
        'sujet' => 'ITASSEL — معلومات إضافية مطلوبة للملف :reference',
        'salutation' => 'مرحباً :prenom :nom،',
        'intro' => 'تحتاج المصلحة التي تعالج الملف :reference إلى معلومات إضافية لمواصلة دراسة الطلب.',
        'question_label' => 'سؤال المصلحة',
        'piece' => 'وثيقة مطلوبة',
        'piece_aide' => 'هذا الملف إلزامي للرد (PDF أو JPG أو PNG، 5 ميغابايت كحد أقصى).',
        'consigne' => 'للرد، افتحوا الرابط أدناه (المرجع مُدرج مسبقاً):',
        'bouton' => 'الرد على الطلب',
        'code' => 'سيُرسل رمز تحقق بالبريد للوصول إلى الملف.',
    ],
    'complement_annule' => [
        'sujet' => 'ITASSEL — الملف :reference: المعلومة لم تعد مطلوبة',
        'salutation' => 'مرحباً :prenom :nom،',
        'intro' => 'المعلومة المطلوبة للملف :reference لم تعد ضرورية. لا يُنتظر منكم أي إجراء، وتتواصل المعالجة.',
        'bouton' => 'متابعة طلبي',
    ],
    'reponse_service' => [
        'sujet' => 'ITASSEL — رد المصلحة على الملف :reference',
        'salutation' => 'مرحباً :prenom :nom،',
        'intro' => 'ردّت المصلحة على الملف :reference:',
        'historique' => 'هذا الرد متاح أيضاً في سجل الملف:',
        'bouton' => 'متابعة طلبي',
    ],
    'accuse_complement' => [
        'sujet' => 'ITASSEL — تم استلام ردكم على الملف :reference',
        'salutation' => 'مرحباً :prenom :nom،',
        'intro' => 'تم استلام ردكم على طلب المعلومات الإضافية للملف :reference. ستواصل المصلحة دراسة الملف.',
        'suite' => 'ستُبلَّغون بالبريد الإلكتروني بمتابعة طلبكم.',
    ],
];
