<?php

declare(strict_types=1);

/**
 * البيانات المرجعية للجغرافيا — 22 دولة عربية + محافظات مصر الـ27 كمثال مكتمل.
 */
return [
    'countries' => [
        ['iso2' => 'EG', 'iso3' => 'EGY', 'phone_code' => '+20', 'name' => ['ar' => 'مصر', 'en' => 'Egypt', 'fr' => 'Égypte'], 'sort_order' => 1],
        ['iso2' => 'SA', 'iso3' => 'SAU', 'phone_code' => '+966', 'name' => ['ar' => 'المملكة العربية السعودية', 'en' => 'Saudi Arabia', 'fr' => 'Arabie Saoudite'], 'sort_order' => 2],
        ['iso2' => 'AE', 'iso3' => 'ARE', 'phone_code' => '+971', 'name' => ['ar' => 'الإمارات العربية المتحدة', 'en' => 'United Arab Emirates', 'fr' => 'Émirats Arabes Unis'], 'sort_order' => 3],
        ['iso2' => 'KW', 'iso3' => 'KWT', 'phone_code' => '+965', 'name' => ['ar' => 'الكويت', 'en' => 'Kuwait', 'fr' => 'Koweït'], 'sort_order' => 4],
        ['iso2' => 'QA', 'iso3' => 'QAT', 'phone_code' => '+974', 'name' => ['ar' => 'قطر', 'en' => 'Qatar', 'fr' => 'Qatar'], 'sort_order' => 5],
        ['iso2' => 'BH', 'iso3' => 'BHR', 'phone_code' => '+973', 'name' => ['ar' => 'البحرين', 'en' => 'Bahrain', 'fr' => 'Bahreïn'], 'sort_order' => 6],
        ['iso2' => 'OM', 'iso3' => 'OMN', 'phone_code' => '+968', 'name' => ['ar' => 'عُمان', 'en' => 'Oman', 'fr' => 'Oman'], 'sort_order' => 7],
        ['iso2' => 'JO', 'iso3' => 'JOR', 'phone_code' => '+962', 'name' => ['ar' => 'الأردن', 'en' => 'Jordan', 'fr' => 'Jordanie'], 'sort_order' => 8],
        ['iso2' => 'PS', 'iso3' => 'PSE', 'phone_code' => '+970', 'name' => ['ar' => 'فلسطين', 'en' => 'Palestine', 'fr' => 'Palestine'], 'sort_order' => 9],
        ['iso2' => 'LB', 'iso3' => 'LBN', 'phone_code' => '+961', 'name' => ['ar' => 'لبنان', 'en' => 'Lebanon', 'fr' => 'Liban'], 'sort_order' => 10],
        ['iso2' => 'SY', 'iso3' => 'SYR', 'phone_code' => '+963', 'name' => ['ar' => 'سوريا', 'en' => 'Syria', 'fr' => 'Syrie'], 'sort_order' => 11],
        ['iso2' => 'IQ', 'iso3' => 'IRQ', 'phone_code' => '+964', 'name' => ['ar' => 'العراق', 'en' => 'Iraq', 'fr' => 'Irak'], 'sort_order' => 12],
        ['iso2' => 'YE', 'iso3' => 'YEM', 'phone_code' => '+967', 'name' => ['ar' => 'اليمن', 'en' => 'Yemen', 'fr' => 'Yémen'], 'sort_order' => 13],
        ['iso2' => 'SD', 'iso3' => 'SDN', 'phone_code' => '+249', 'name' => ['ar' => 'السودان', 'en' => 'Sudan', 'fr' => 'Soudan'], 'sort_order' => 14],
        ['iso2' => 'LY', 'iso3' => 'LBY', 'phone_code' => '+218', 'name' => ['ar' => 'ليبيا', 'en' => 'Libya', 'fr' => 'Libye'], 'sort_order' => 15],
        ['iso2' => 'TN', 'iso3' => 'TUN', 'phone_code' => '+216', 'name' => ['ar' => 'تونس', 'en' => 'Tunisia', 'fr' => 'Tunisie'], 'sort_order' => 16],
        ['iso2' => 'DZ', 'iso3' => 'DZA', 'phone_code' => '+213', 'name' => ['ar' => 'الجزائر', 'en' => 'Algeria', 'fr' => 'Algérie'], 'sort_order' => 17],
        ['iso2' => 'MA', 'iso3' => 'MAR', 'phone_code' => '+212', 'name' => ['ar' => 'المغرب', 'en' => 'Morocco', 'fr' => 'Maroc'], 'sort_order' => 18],
        ['iso2' => 'MR', 'iso3' => 'MRT', 'phone_code' => '+222', 'name' => ['ar' => 'موريتانيا', 'en' => 'Mauritania', 'fr' => 'Mauritanie'], 'sort_order' => 19],
        ['iso2' => 'SO', 'iso3' => 'SOM', 'phone_code' => '+252', 'name' => ['ar' => 'الصومال', 'en' => 'Somalia', 'fr' => 'Somalie'], 'sort_order' => 20],
        ['iso2' => 'DJ', 'iso3' => 'DJI', 'phone_code' => '+253', 'name' => ['ar' => 'جيبوتي', 'en' => 'Djibouti', 'fr' => 'Djibouti'], 'sort_order' => 21],
        ['iso2' => 'KM', 'iso3' => 'COM', 'phone_code' => '+269', 'name' => ['ar' => 'جزر القمر', 'en' => 'Comoros', 'fr' => 'Comores'], 'sort_order' => 22],
    ],
    'regions' => [
        'EG' => [
            ['code' => 'CAI', 'name' => ['ar' => 'القاهرة', 'en' => 'Cairo', 'fr' => 'Le Caire'], 'sort_order' => 1],
            ['code' => 'GZ',  'name' => ['ar' => 'الجيزة', 'en' => 'Giza', 'fr' => 'Gizeh'], 'sort_order' => 2],
            ['code' => 'ALX', 'name' => ['ar' => 'الإسكندرية', 'en' => 'Alexandria', 'fr' => 'Alexandrie'], 'sort_order' => 3],
            ['code' => 'QALY', 'name' => ['ar' => 'القليوبية', 'en' => 'Qalyubia', 'fr' => 'Qalyubiyya'], 'sort_order' => 4],
            ['code' => 'SHR', 'name' => ['ar' => 'الشرقية', 'en' => 'Sharqia', 'fr' => 'Al-Charqiya'], 'sort_order' => 5],
            ['code' => 'DKH', 'name' => ['ar' => 'الدقهلية', 'en' => 'Dakahlia', 'fr' => 'Daqahliyya'], 'sort_order' => 6],
            ['code' => 'GH',  'name' => ['ar' => 'الغربية', 'en' => 'Gharbia', 'fr' => 'Gharbiya'], 'sort_order' => 7],
            ['code' => 'MNF', 'name' => ['ar' => 'المنوفية', 'en' => 'Monufia', 'fr' => 'Menoufia'], 'sort_order' => 8],
            ['code' => 'BH',  'name' => ['ar' => 'البحيرة', 'en' => 'Beheira', 'fr' => 'Beheira'], 'sort_order' => 9],
            ['code' => 'KFS', 'name' => ['ar' => 'كفر الشيخ', 'en' => 'Kafr El Sheikh', 'fr' => 'Kafr el-Cheikh'], 'sort_order' => 10],
            ['code' => 'DAM', 'name' => ['ar' => 'دمياط', 'en' => 'Damietta', 'fr' => 'Damiette'], 'sort_order' => 11],
            ['code' => 'PTS', 'name' => ['ar' => 'بورسعيد', 'en' => 'Port Said', 'fr' => 'Port-Saïd'], 'sort_order' => 12],
            ['code' => 'ISM', 'name' => ['ar' => 'الإسماعيلية', 'en' => 'Ismailia', 'fr' => 'Ismaïlia'], 'sort_order' => 13],
            ['code' => 'SUZ', 'name' => ['ar' => 'السويس', 'en' => 'Suez', 'fr' => 'Suez'], 'sort_order' => 14],
            ['code' => 'SIN', 'name' => ['ar' => 'شمال سيناء', 'en' => 'North Sinai', 'fr' => 'Sinaï du Nord'], 'sort_order' => 15],
            ['code' => 'JSIN', 'name' => ['ar' => 'جنوب سيناء', 'en' => 'South Sinai', 'fr' => 'Sinaï du Sud'], 'sort_order' => 16],
            ['code' => 'FYM', 'name' => ['ar' => 'الفيوم', 'en' => 'Faiyum', 'fr' => 'Fayoum'], 'sort_order' => 17],
            ['code' => 'BNS', 'name' => ['ar' => 'بني سويف', 'en' => 'Beni Suef', 'fr' => 'Beni Suef'], 'sort_order' => 18],
            ['code' => 'MNY', 'name' => ['ar' => 'المنيا', 'en' => 'Minya', 'fr' => 'Minya'], 'sort_order' => 19],
            ['code' => 'ASY', 'name' => ['ar' => 'أسيوط', 'en' => 'Asyut', 'fr' => 'Assiout'], 'sort_order' => 20],
            ['code' => 'SHG', 'name' => ['ar' => 'سوهاج', 'en' => 'Sohag', 'fr' => 'Sohag'], 'sort_order' => 21],
            ['code' => 'QNA', 'name' => ['ar' => 'قنا', 'en' => 'Qena', 'fr' => 'Qena'], 'sort_order' => 22],
            ['code' => 'LXR', 'name' => ['ar' => 'الأقصر', 'en' => 'Luxor', 'fr' => 'Louxor'], 'sort_order' => 23],
            ['code' => 'ASN', 'name' => ['ar' => 'أسوان', 'en' => 'Aswan', 'fr' => 'Assouan'], 'sort_order' => 24],
            ['code' => 'RSEA', 'name' => ['ar' => 'البحر الأحمر', 'en' => 'Red Sea', 'fr' => 'Mer Rouge'], 'sort_order' => 25],
            ['code' => 'WAD', 'name' => ['ar' => 'الوادي الجديد', 'en' => 'New Valley', 'fr' => 'Nouvelle-Vallée'], 'sort_order' => 26],
            ['code' => 'MAT', 'name' => ['ar' => 'مطروح', 'en' => 'Matrouh', 'fr' => 'Marsa Matrouh'], 'sort_order' => 27],
        ],
    ],
];
