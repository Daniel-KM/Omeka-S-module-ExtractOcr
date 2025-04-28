<?php declare(strict_types=1);

namespace ExtractOcr;

use ExtractOcr\Form\ConfigForm;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Stdlib\Message;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $services): void
    {
        $this->setServiceLocator($services);
        $t = $services->get('MvcTranslator');

        // Don't install if the pdftotext command doesn't exist.
        // See: http://stackoverflow.com/questions/592620/check-if-a-program-exists-from-a-bash-script
        if ((int) shell_exec('hash pdftotext 2>&- || echo 1')) {
            throw new ModuleCannotInstallException(
                'The command-line utility pdftotext is not available. Install the package poppler-utils.' //@translate
            );
        }

        if ((int) shell_exec('hash pdftohtml 2>&- || echo 1')) {
            throw new ModuleCannotInstallException(
                'The command-line utility pdftohtml is not available. Install the package poppler-utils.' //@translate
            );
        }

        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        if (!$this->checkDestinationDir($basePath . '/temp')) {
            $message = new Message(
                $t->translate('The directory "%s" is not writeable. Fix rights or create it manually.'), // @translate
                $basePath . '/temp'
            );
            throw new ModuleCannotInstallException($message);
        }

        if (!$this->checkDestinationDir($basePath . '/iiif-search')) {
            $message = new Message(
                $t->translate('The directory "%s" is not writeable. Fix rights or create it manually.'), // @translate
                $basePath . '/iiif-search'
                );
            throw new ModuleCannotInstallException($message);
        }

        if (!$this->checkDestinationDir($basePath . '/alto')) {
            $message = new Message(
                $t->translate('The directory "%s" is not writeable. Fix rights or create it manually.'), // @translate
                $basePath . '/alto'
            );
            throw new ModuleCannotInstallException($message);
        }

        if (!$this->checkDestinationDir($basePath . '/pdf2xml')) {
            $message = new Message(
                $t->translate('The directory "%s" is not writeable. Fix rights or create it manually.'), // @translate
                $basePath . '/pdf2xml'
            );
            throw new ModuleCannotInstallException($message);
        }

        $isOldOmeka = version_compare(\Omeka\Module::VERSION, '3.1', '<');
        $baseUri = $config['file_store']['local']['base_uri'];
        if (!$baseUri && $isOldOmeka) {
            $this->setServiceLocator($services);
            $baseUri = $this->getBaseUri();
            $message = new Message(
                $t->translate('The base uri "%s" is not set in the config file of Omeka "config/local.config.php". It must be set for technical reasons for now.'), //@translate
                $baseUri
            );
            throw new ModuleCannotInstallException($message);
        }

        $settings = $services->get('Omeka\Settings');
        $config = require __DIR__ . '/config/module.config.php';
        $config = $config['extractocr']['config'];
        foreach ($config as $name => $value) {
            $settings->set($name, $value);
        }

        $settings->set('extractocr_types_files', [
            'text/tab-separated-values',
            'application/alto+xml',
        ]);

        $settings->set('extractocr_content_store', [
            'media_pdf',
        ]);

        $this->allowFileFormats();
    }

    public function uninstall(ServiceLocatorInterface $services): void
    {
        $settings = $services->get('Omeka\Settings');
        $config = require __DIR__ . '/config/module.config.php';
        $config = $config['extractocr']['config'];
        foreach (array_keys($config) as $name) {
            $settings->delete($name);
        }
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $services)
    {
        $this->setServiceLocator($services);

        $plugins = $services->get('ControllerPluginManager');
        $settings = $services->get('Omeka\Settings');
        $translator = $services->get('MvcTranslator');
        $messenger = $plugins->get('messenger');

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

        $this->allowFileFormats();
    }

    /**
     * Attach listeners to events.
     *
     * @param SharedEventManagerInterface $sharedEventManager
     */
    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.create.post',
            [$this, 'extractOcr']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.update.post',
            [$this, 'extractOcr']
        );

        // Add a job to upgrade structures once from v3.
        $sharedEventManager->attach(
            \EasyAdmin\Form\CheckAndFixForm::class,
            'form.add_elements',
            [$this, 'handleEasyAdminJobsForm']
        );
        $sharedEventManager->attach(
            \EasyAdmin\Controller\Admin\CheckAndFixController::class,
            'easyadmin.job',
            [$this, 'handleEasyAdminJobs']
        );
    }

    /**
     * Allow TSV and XML extensions and media types in omeka settings.
     */
    protected function allowFileFormats(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');

        $extensionWhitelist = $settings->get('extension_whitelist', []);
        $extensions = [
            'tsv',
            'xml',
        ];
        $extensionWhitelist = array_unique(array_merge($extensionWhitelist, $extensions));
        $settings->set('extension_whitelist', $extensionWhitelist);

        $mediaTypeWhitelist = $settings->get('media_type_whitelist');
        $xmlMediaTypes = [
            'application/xml',
            'text/xml',
            'application/alto+xml',
            'application/vnd.pdf2xml+xml',
            'application/x-empty',
            'text/tab-separated-values',
        ];
        $mediaTypeWhitelist = array_unique(array_merge($mediaTypeWhitelist, $xmlMediaTypes));
        $settings->set('media_type_whitelist', $mediaTypeWhitelist);
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $this->allowFileFormats();

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $form->init();

        $config = require __DIR__ . '/config/module.config.php';
        $config = $config['extractocr']['config'];
        $data = [];
        foreach ($config as $name => $value) {
            $data[$name] = $settings->get($name, $value);
        }
        $form->setData($data);

        $html = '<p>'
            . $renderer->translate('Options are used during edition of items and for bulk processing.') // @translate
            . $renderer->translate('The insertion of the text in the item properties is currently not supported.') // @translate
            . ' ' . $renderer->translate('XML files will be rebuilt for all PDF files of your Omeka install.') // @translate
            . '</p>';
        $html .= $renderer->formCollection($form);
        return $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $form = $services->get('FormElementManager')->get(ConfigForm::class);

        /** @var \Laminas\Stdlib\Parameters $params */
        $params = $controller->getRequest()->getPost();

        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $data = $form->getData();

        $settings = $services->get('Omeka\Settings');
        $settings->set('extractocr_types_files', $data['extractocr_types_files'] ?? []);
        $settings->set('extractocr_types_media', $data['extractocr_types_media'] ?? []);
        $settings->set('extractocr_content_store', $data['extractocr_content_store']);
        $settings->set('extractocr_content_property', $data['extractocr_content_property']);
        $settings->set('extractocr_content_language', $data['extractocr_content_language']);
        $settings->set('extractocr_create_empty_file', !empty($data['extractocr_create_empty_file']));

        // Keep only values used in job.
        $params = array_intersect_key($params->getArrayCopy(), [
            'mode' => 'all',
            'item_ids' => '',
            'process' => null,
        ]);
        if (empty($params['process']) || $params['process'] !== $controller->translate('Process')) {
            $message = 'No job launched.'; // @translate
            $controller->messenger()->addWarning($message);
            return true;
        }

        $args = [];
        $args['mode'] = $params['mode'] ?? 'all';
        $args['base_uri'] = $this->getBaseUri();
        $args['item_ids'] = $params['item_ids'] ?? '';

        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        $job = $dispatcher->dispatch(\ExtractOcr\Job\ExtractOcr::class, $args);

        $message = new Message(
            'Creating Extract OCR files in background (job %1$s#%2$s%3$s, %4$slogs%3$s).', // @translate
            sprintf(
                '<a href="%s">',
                htmlspecialchars($controller->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
            ),
            $job->getId(),
            '</a>',
            sprintf(
                '<a href="%s">',
                class_exists('Log\Module', false)
                    ? htmlspecialchars($controller->url()->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]]))
                    : htmlspecialchars($controller->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId(), 'action' => 'log']))
            )
        );
        $message->setEscapeHtml(false);
        $controller->messenger()->addSuccess($message);
        return true;
    }

    /**
     * Launch extract ocr's job for an item.
     *
     * @param Event $event
     */
    public function extractOcr(Event $event): void
    {
        $services = $this->getServiceLocator();
        $response = $event->getParams()['response'];
        /** @var \Omeka\Entity\Item $item */
        $item = $response->getContent();

        $extensions = [
            \ExtractOcr\Job\ExtractOcr::FORMAT_ALTO => 'alto.xml',
            \ExtractOcr\Job\ExtractOcr::FORMAT_PDF2XML => 'xml',
            \ExtractOcr\Job\ExtractOcr::FORMAT_TSV => 'tsv',
        ];
        $settings = $services->get('Omeka\Settings');
        $targetTypesFiles = $settings->get('extractocr_types_files') ?: [];
        $targetTypesFiles = array_intersect($targetTypesFiles, array_flip($extensions));
        $targetTypesMedia = $settings->get('extractocr_types_media') ?: [];
        $targetTypesMedia = array_intersect($targetTypesMedia, array_flip($extensions));
        if (!$targetTypesFiles && !$targetTypesMedia ) {
            return;
        }

        // Get the pdf.
        $hasPdf = false;
        /** @var \Omeka\Entity\Media $media */
        foreach ($item->getMedia() as $media) {
            $mediaType = $media->getMediaType();
            $extension = strtolower((string) $media->getExtension());
            if ($mediaType === 'application/pdf' && $extension === 'pdf') {
                $hasPdf = true;
                break;
            }
        }

        if (!$hasPdf) {
            return;
        }

        $source = (string) $media->getSource();
        $filename = (string) parse_url($source, PHP_URL_PATH);
        $targetFilenameNoExtension = strlen($filename)
            ? basename($filename, '.pdf')
            : $media->id() . '-' . $media->getStorageId();
        if (!$targetFilenameNoExtension) {
            return;
        }

        $suffixFilenames = [
            'alto.xml' => '.alto',
            'xml' => '',
            'tsv' => '',
        ];
        $shortExtensions = [
            'alto.xml' => 'xml',
            'xml' => 'xml',
            'tsv' => 'tsv',
        ];
        $dirPaths = [
            'alto.xml' => 'alto',
            'pdf2xml' => 'pdf2xml',
            'tsv' => 'iiif-search',
        ];

        // Don't override an already processed pdf when updating an item.
        $existingFiles = array_fill_keys($targetTypesFiles, false);
        if ($targetTypesFiles) {
            $basePath = $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
            foreach ($targetTypesFiles as $targetTypeFile) {
                $targetExtension = $extensions[$targetTypeFile];
                $targetDirPath = $dirPaths[$targetExtension];
                $localSearchFilepath = $basePath . '/' . $targetDirPath . '/' . $item->getId() . '.' . $targetExtension;
                if (file_exists($localSearchFilepath)) {
                    $existingFiles[$targetTypeFile] = true;
                }
            }
        }

        $existingMedias = array_fill_keys($targetTypesMedia, false);
        if ($targetTypesMedia) {
            foreach ($targetTypesMedia as $targetMediaType) {
                $targetExtension = $extensions[$targetMediaType];
                $targetFilename = $targetFilenameNoExtension . '.' . $targetExtension;
                if ($this->getMediaFromFilename($item->getId(), $targetFilename . $suffixFilenames[$targetExtension], $shortExtensions[$targetExtension], $targetMediaType)) {
                    $existingMedias[$targetMediaType] = true;
                }
            }
        }

        if (count(array_filter($existingFiles)) === count($existingFiles)
            && count(array_filter($existingMedias)) === count($existingMedias)
        ) {
            return;
        }

        $params = [
            'mode' => 'all',
            'base_uri' => $this->getBaseUri(),
            'item_id' => $item->getId(),
            // FIXME Currently impossible to save text with event api.update.post.
            'manual' => true,
        ];
        $services->get('Omeka\Job\Dispatcher')->dispatch(\ExtractOcr\Job\ExtractOcr::class, $params);

        $messenger = $services->get('ControllerPluginManager')->get('messenger');
        $message = new Message('Extracting OCR in background.'); // @translate
        $messenger->addNotice($message);
    }

    /**
     * Get the first media from item id, source name, extension and media type.
     *
     * @todo Improve search of ocr pdf2xml files.
     *
     * Copy:
     * @see \ExtractOcr\Module::getMediaFromFilename()
     * @see \ExtractOcr\Job\ExtractOcr::getMediaFromFilename()
     *
     * @param int $itemId
     * @param string $filename
     * @param string $extension
     * @param string $mediaType
     * @return \Omeka\Api\Representation\MediaRepresentation|null
     */
    protected function getMediaFromFilename($itemId, $filename, $extension, $mediaType)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        // The api search() doesn't allow to search a source, so we use read().
        try {
            return $api->read('media', [
                'item' => $itemId,
                'source' => $filename,
                'extension' => $extension,
                'mediaType' => $mediaType,
            ])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
        }
        return null;
    }

    /**
     * @todo Add parameter for xml storage path.
     * @todo To get the base uri is useless now, since base uri is passed as job argument.
     */
    protected function getBaseUri()
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $baseUri = $config['file_store']['local']['base_uri'];
        if (!$baseUri) {
            $helpers = $services->get('ViewHelperManager');
            $serverUrlHelper = $helpers->get('serverUrl');
            $basePathHelper = $helpers->get('basePath');
            $baseUri = $serverUrlHelper($basePathHelper('files'));
            if ($baseUri === 'http:///files' || $baseUri === 'https:///files') {
                $t = $services->get('MvcTranslator');
                throw new \Omeka\Mvc\Exception\RuntimeException(
                    sprintf(
                        $t->translate('The base uri is not set (key [file_store][local][base_uri]) in the config file of Omeka "config/local.config.php". It must be set for now (key [file_store][local][base_uri]) in order to process background jobs.'), //@translate
                        $baseUri
                    )
                );
            }
        }
        return $baseUri;
    }

    /**
     * Check or create the destination folder.
     *
     * @param string $dirPath Absolute path.
     * @return string|null
     */
    protected function checkDestinationDir(string $dirPath): ?string
    {
        if (file_exists($dirPath)) {
            if (!is_dir($dirPath) || !is_readable($dirPath) || !is_writeable($dirPath)) {
                $this->getServiceLocator()->get('Omeka\Logger')->err(new Message(
                    'The directory "%s" is not writeable.', // @translate
                    $dirPath
                ));
                return null;
            }
            return $dirPath;
        }

        $result = @mkdir($dirPath, 0775, true);
        if (!$result) {
            $this->getServiceLocator()->get('Omeka\Logger')->err(new Message(
                'The directory "%1$s" is not writeable: %2$s.', // @translate
                $dirPath, error_get_last()['message']
            ));
            return null;
        }
        return $dirPath;
    }

    public function handleEasyAdminJobsForm(Event $event): void
    {
        /**
         * @var \EasyAdmin\Form\CheckAndFixForm $form
         * @var \Laminas\Form\Element\Radio $process
         * @var \ExtractOcr\Form\ConfigForm $configForm
         */
        $form = $event->getTarget();
        $fieldset = $form->get('module_tasks');
        $process = $fieldset->get('process');
        $valueOptions = $process->getValueOptions();
        $valueOptions['extractocr_extractor'] = 'Extract OCR: Extract ocr from files'; // @translate
        $process->setValueOptions($valueOptions);

        // $configForm = $this->getServiceLocator()->get('FormElementManager')
        //     ->get(\ExtractOcr\Form\ConfigForm::class);
        $fieldset
            ->add([
                'type' => \Laminas\Form\Fieldset::class,
                'name' => 'extractocr_extractor',
                'options' => [
                    'label' => 'Options to extract OCR', // @translate
                ],
                'attributes' => [
                    'class' => 'extractocr_extractor',
                ],
            ])
            ->get('extractocr_extractor')
            ->add([
                'name' => 'mode',
                'type' => \Common\Form\Element\OptionalRadio::class,
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
                'type' => \Laminas\Form\Element\Text::class,
                'options' => [
                    'label' => 'Item ids', // @translate
                ],
                'attributes' => [
                    'id' => 'item_ids',
                    'placeholder' => '2-6 8 38-52 80-', // @ translate
                ],
            ])
        ;
    }

    public function handleEasyAdminJobs(Event $event): void
    {
        $process = $event->getParam('process');
        if ($process === 'extractocr_extractor') {
            $params = $event->getParam('params');
            $event->setParam('job', \ExtractOcr\Job\ExtractOcr::class);
            $args = $params['module_tasks']['extractocr_extractor'] ?? [];
            $args['base_uri'] = $this->getBaseUri();
            $event->setParam('args', $args);
        }
    }
}
