<?php declare(strict_types=1);

namespace ExtractOcr\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Omeka\Form\Element\PropertySelect;

class ConfigForm extends Form
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    public function init(): void
    {
        $this
            ->add([
                'name' => 'extractocr_types_files',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Create file for formats', // @translate
                    'info' => 'For more info on formats, see readme.', // @translate
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-ExtractOcr#extract-ocr-modue-for-omeka-s',
                    'value_options' => [
                        'text/tab-separated-values' => 'tsv with original order of words (quick and exact search for iiif search)', // @translate
                        'text/tab-separated-values;by-word' => 'tsv grouped by word (very quick search for iiif search, small index)', // @translate
                        'application/alto+xml' => 'xml alto (slow search, ocr transcription for iiif server)', // @translate
                        'application/vnd.pdf2xml+xml' => 'pdf2xml', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'extractocr_types_files',
                ],
            ])
            ->add([
                'name' => 'extractocr_types_media',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Create media for formats', // @translate
                    'value_options' => [
                        'text/tab-separated-values' => 'tsv with original order of words', // @translate
                        'text/tab-separated-values;by-word' => 'tsv grouped by word', // @translate
                        'application/alto+xml' => 'xml alto', // @translate
                        'application/vnd.pdf2xml+xml' => 'pdf2xml', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'extractocr_types_media',
                ],
            ])
            ->add([
                'name' => 'extractocr_content_store',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Store the raw text in a property of a resource', // @translate
                    'info' => 'Text cannot be stored in item when an item is manually edited.', // @translate
                    'empty_option' => '',
                    'value_options' => [
                        'item' => 'Item', // @translate
                        'media_pdf' => 'Pdf media', // @translate
                        'media_extracted' => 'Tsv or Xml media if any', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'extractocr_content_store',
                ],
            ])
            ->add([
                'name' => 'extractocr_content_property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Property to save pdf raw text (not recommended)', // @translate
                    'info' => 'To save content makes it searchable anywhere. It is recommended to use "bibo:content". Note that it will increase the noise in the results, unless you use a search engine. Furthermore, this option is not recommended when the text is too much big.', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'extractocr_content_property',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a media property…', // @translate
                ],
            ])
            ->add([
                'name' => 'extractocr_content_language',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Language code of the content', // @translate
                ],
                'attributes' => [
                    'id' => 'extractocr_content_language',
                ],
            ])
            ->add([
                'name' => 'extractocr_create_empty_file',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Create xml file even if there is no text content', // @translate
                    'info' => 'This option may be useful to keep the same order and number of pages and extracted texts.', // @translate
                ],
                'attributes' => [
                    'id' => 'extractocr_create_empty_file',
                ],
            ])

            ->add([
                'name' => 'extractocr_extractor',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Extract OCR job', // @translate
                ],
            ])
        ;

        // Adapted in Module.
        $this->get('extractocr_extractor')
            ->add([
                'name' => 'mode',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Extract OCR job', // @translate
                    'value_options' => [
                        'existing' => 'Only already extracted (improve extraction)', // @translate
                        'missing' => 'Only missing extracted medias', // @translate
                        'all' => 'All medias', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'mode',
                    'value' => 'all',
                ],
            ])
            ->add([
                'name' => 'item_ids',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Item ids', // @translate
                ],
                'attributes' => [
                    'id' => 'item_ids',
                    'placeholder' => '2-6 8 38-52 80-', // @ translate
                ],
            ])
            ->add([
                'name' => 'process',
                'type' => Element\Submit::class,
                'options' => [
                    'label' => 'Run in background', // @translate
                ],
                'attributes' => [
                    'id' => 'process',
                    'value' => 'Process', // @translate
                ],
            ]);

        $this->getInputFilter()
            ->add([
                'name' => 'extractocr_types_files',
                'required' => false,
            ])
            ->add([
                'name' => 'extractocr_types_media',
                'required' => false,
            ])
            ->add([
                'name' => 'extractocr_content_store',
                'required' => false,
            ])
            ->add([
                'name' => 'extractocr_content_property',
                'required' => false,
            ])
            ->add([
                'name' => 'extractocr_extractor',
                'required' => false,
            ])
        ;
    }
}
