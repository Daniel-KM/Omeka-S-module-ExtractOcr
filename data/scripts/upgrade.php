<?php declare(strict_types=1);

namespace ExtractOcr;

use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\View\Helper\Url $url
 * @var \Laminas\Log\Logger $logger
 * @var \Omeka\Settings\Settings $settings
 * @var \Laminas\I18n\View\Helper\Translate $translate
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Laminas\Mvc\I18n\Translator $translator
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Settings\SiteSettings $siteSettings
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$url = $services->get('ViewHelperManager')->get('url');
$api = $plugins->get('api');
$logger = $services->get('Omeka\Logger');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$translator = $services->get('MvcTranslator');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$siteSettings = $services->get('Omeka\Settings\Site');
$entityManager = $services->get('Omeka\EntityManager');

if (version_compare((string) $oldVersion, '3.4.5', '<')) {
    $message = new Message('A new option allows to create xml as alto multi-pages.'); // @translate
    // Default is alto on install, but pdf2xml during upgrade.
    $settings->set('extractocr_media_type', 'application/vnd.pdf2xml+xml');
    $messenger->addSuccess($message);
}

if (version_compare((string) $oldVersion, '3.4.6', '<')) {
    $settings->set('extractocr_create_empty_file', $settings->get('extractocr_create_empty_xml', false));
    $settings->delete('extractocr_create_empty_xml');
    $message = new Message('A new option allows to export OCR into tsv format for quicker search results. Data should be reindexed with format TSV.'); // @translate
    $messenger->addSuccess($message);
}

if (version_compare((string) $oldVersion, '3.4.7', '<')) {
    $config = $services->get('Config');
    $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

    if (!$this->checkDestinationDir($basePath . '/temp')) {
        $message = new Message(
            $translator->translate('The directory "%s" is not writeable. Fix rights or create it manually.'), // @translate
            $basePath . '/temp'
        );
        throw new ModuleCannotInstallException($message);
    }

    if (!$this->checkDestinationDir($basePath . '/iiif-search')) {
        $message = new Message(
            $translator->translate('The directory "%s" is not writeable. Fix rights or create it manually.'), // @translate
            $basePath . '/iiif-search'
        );
        throw new ModuleCannotInstallException($message);
    }

    if (!$this->checkDestinationDir($basePath . '/alto')) {
        $message = new Message(
            $translator->translate('The directory "%s" is not writeable. Fix rights or create it manually.'), // @translate
            $basePath . '/alto'
        );
        throw new ModuleCannotInstallException($message);
    }

    if (!$this->checkDestinationDir($basePath . '/pdf2xml')) {
        $message = new Message(
            $translator->translate('The directory "%s" is not writeable. Fix rights or create it manually.'), // @translate
            $basePath . '/pdf2xml'
        );
        throw new ModuleCannotInstallException($message);
    }

    $contentStore = $settings->get('extractocr_content_store', []);
    $pos = array_search('media_xml', $contentStore);
    if ($pos !== false) {
        unset($contentStore[$pos]);
        $contentStore[] = 'media_extracted';
        $settings->set('extractocr_content_store', array_values($contentStore));
    }

    $settings->set('extractocr_create_media', true);
    $message = new Message(
        'A new option allows to store the file separately of the item. You can enable it by default.' // @translate
    );
    $messenger->addSuccess($message);

    $extractMediaType = $settings->get('extractocr_media_type', 'text/tab-separated-values') ?: 'text/tab-separated-values';
    $settings->set('extractocr_media_types', [$extractMediaType]);
    $settings->delete('extractocr_media_type');

    // The option is set true above during upgrade to keep old process.
    $settings->set('extractocr_types_files', []);
    $settings->set('extractocr_types_media', [$extractMediaType]);
    $settings->delete('extractocr_media_types');
    $settings->delete('extractocr_create_media');

    $message = new Message(
        'It is now possible to store multiple extracted files and medias, for example one for quick search and another one to display transcription.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new Message(
        'In order to manage multiple derivative files and to avoid collisions with native files, the names of the file were updated. You should remove all existing created files (via search media by media type then delete) then recreate them all (via the job in config form).' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare((string) $oldVersion, '3.4.8', '<')) {
    $config = $services->get('Config');
    $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

    // Rename all files inside /iiif-search with extension "by-word.tsv".
    $dirpath = $basePath . '/iiif-search';
    if (!$this->checkDestinationDir($dirpath)) {
        $message = new Message(
            $translator->translate('The directory "%s" is not writeable. Fix rights or create it manually.'), // @translate
            $dirpath
        );
        throw new ModuleCannotInstallException($message);
    }

    foreach (scandir($dirpath) ?: [] as $filename) {
        if ($filename === '.'
            || $filename === '..'
            || mb_substr($filename, -12) === '.by-word.tsv'
            || mb_substr($filename, -4) !== '.tsv'
        ) {
            continue;
        }
        $filepath = $dirpath . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($filepath)) {
            continue;
        }
        $newFilepath = substr_replace($filepath, '.by-word.tsv', -4);
        $result = @rename($filepath, $newFilepath);
        if (!$result) {
            $message = new Message(
                $translator->translate('The file "%s" cannot be renamed.'), // @translate
                $filename
            );
        }
    }

    // Rename all files inside /pdf2xml with extension "pdf2xml.xml".
    $dirpath = $basePath . '/pdf2xml';
    if (!$this->checkDestinationDir($dirpath)) {
        $message = new Message(
            $translator->translate('The directory "%s" is not writeable. Fix rights or create it manually.'), // @translate
            $dirpath
        );
        throw new ModuleCannotInstallException($message);
    }

    foreach (scandir($dirpath) ?: [] as $filename) {
        if ($filename === '.'
            || $filename === '..'
            || mb_substr($filename, -12) === '.pdf2xml.xml'
            || mb_substr($filename, -4) !== '.xml'
        ) {
            continue;
        }
        $filepath = $dirpath . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($filepath)) {
            continue;
        }
        $newFilepath = substr_replace($filepath, '.pdf2xml.xml', -4);
        $result = @rename($filepath, $newFilepath);
        if (!$result) {
            $message = new Message(
                $translator->translate('The file "%s" cannot be renamed.'), // @translate
                $filename
            );
        }
    }

    // Rename all stored files for formats tsv and pdf2xml.
    /** @var \Doctrine\DBAL\Connection $connection */
    $connection = $services->get('Omeka\Connection');
    $connection->executeStatement(<<<'SQL'
        UPDATE `media`
        SET `source` = CONCAT(LEFT(`source`, LENGTH(`source`) - 4), ".by-word.tsv")
        WHERE
            `media_type` = "text/tab-separated-values"
            AND `extension` = "tsv"
            AND RIGHT(`source`, 4) = ".tsv"
            AND RIGHT(`source`, 12) != ".by-word.tsv"
            AND `source` NOT LIKE "%/%"
        ;
        SQL);
    $connection->executeStatement(<<<'SQL'
        UPDATE `media`
        SET `source` = CONCAT(LEFT(`source`, LENGTH(`source`) - 4), ".pdf2xml.xml")
        WHERE
            `media_type` = "application/vnd.pdf2xml+xml"
            AND `extension` = "xml"
            AND RIGHT(`source`, 4) = ".xml"
            AND RIGHT(`source`, 12) != ".pdf2xml.xml"
            AND `source` NOT LIKE "%/%"
        ;
        SQL);

    $types = $settings->get('extractocr_types_files', []);
    $key = array_search('text/tab-separated-values', $types);
    if ($key !== false) {
        $types[$key] = 'text/tab-separated-values;by-word';
        $settings->set('extractocr_types_files', $types);
    }

    $types = $settings->get('extractocr_types_media', []);
    $key = array_search('text/tab-separated-values', $types);
    if ($key !== false) {
        $types[$key] = 'text/tab-separated-values;by-word';
        $settings->set('extractocr_types_media', $types);
    }

    $message = new Message(
        'A new extract format was added as tsv to allow quick and exact search, but with larger files.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new Message(
        'You may update the settings in the %1$sconfig form%2$s.', // @translate
        '<a href="' . $url('admin/default', ['controller' => 'module', 'action' => 'configure'], ['query' => ['id' => 'ExtractOcr']]) . '">',
        '</a>'
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}

$this->allowFileFormats();
