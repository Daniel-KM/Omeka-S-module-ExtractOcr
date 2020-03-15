<?php

namespace ExtractOcr;

return [
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'extractocr' => [
        'config' => [
            'extractocr_content_store' => true,
            'extractocr_content_property' => 'bibo:content',
            'extractocr_content_language' => '',
        ],
    ],
];
