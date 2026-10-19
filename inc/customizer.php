<?php
/** Presentation settings; content and account logic remain in Donghua Core. */
declare(strict_types=1);
defined('ABSPATH') || exit;

add_action('customize_register', function (WP_Customize_Manager $manager): void {
    $manager->add_section('donghua_homepage', [
        'title' => __('واجهة الدونغهوا', 'donghua-theme'),
        'priority' => 35,
    ]);
    $fields = [
        'donghua_home_title' => ['عنوان الرئيسية', 'عالم الدونغهوا، أقرب إليك.'],
        'donghua_home_description' => ['وصف الرئيسية', 'اكتشف حكايتك القادمة، تابع أحدث الحلقات، وتعرّف على العوالم والشخصيات.'],
    ];
    foreach ($fields as $key => [$label, $default]) {
        $manager->add_setting($key, [
            'default' => $default,
            'sanitize_callback' => 'sanitize_text_field',
            'transport' => 'refresh',
        ]);
        $manager->add_control($key, [
            'label' => $label,
            'section' => 'donghua_homepage',
            'type' => 'text',
        ]);
    }
});
