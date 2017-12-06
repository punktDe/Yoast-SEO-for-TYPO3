<?php
namespace YoastSeoForTypo3\YoastSeo\Controller;

//use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\DataHandling\DataHandler;
//use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ModuleController extends ActionController
{

    /**
     * @var PageRenderer
     */
    protected $pageRenderer;

    /**
     * @var string
     */
    const FOCUS_KEYWORD_COLUMN_NAME = 'tx_yoastseo_focuskeyword';

    /**
     * @var int
     */
    const FE_PREVIEW_TYPE = 1480321830;

    /**
     * @var string
     */
    const APP_TRANSLATION_FILE_PATTERN = 'EXT:yoast_seo/Resources/Private/Language/wordpress-seo-%s.json';

    /**
     * @var array
     */
    protected $configuration = array(
        'translations' => array(
            'availableLocales' => array(),
            'languageKeyToLocaleMapping' => array()
        ),
        'menuActions' => array(),
        'previewDomain' => null,
        'previewUrlTemplate' => '',
        'viewSettings' => array()
    );

    /**
     * @var Locales
     */
    protected $localeService;

    /**
     * @param ViewInterface $view
     *
     * @return void
     */
    protected function initializeView(ViewInterface $view)
    {
        parent::initializeView($view);

        // Early return for actions without valid view like tcaCreateAction or tcaDeleteAction
//        if (!($this->view instanceof BackendTemplateView)) {
//            return;
//        }

        $this->makeLanguageMenu();
    }

    protected function initializeAction()
    {
        if (array_key_exists('yoast_seo', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'])
            && is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['yoast_seo'])
        ) {
            ArrayUtility::mergeRecursiveWithOverrule($this->configuration, $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['yoast_seo']);
        }

        parent::initializeAction();

        if (!($this->localeService instanceof Locales)) {
            $this->localeService = GeneralUtility::makeInstance(Locales::class);
        }
        if (!($this->pageRenderer instanceof PageRenderer)) {
            $this->pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        }
    }

    /**
     * @return void
     */
    public function dashboardAction()
    {
    }

    /**
     * @return void
     */
    public function settingsAction()
    {
        $languageId = 0;
        if ($this->request->hasArgument('language')) {
            $languageId = $this->request->getArgument('language');
        }
        $this->view->assign('languageId', $languageId);
    }
    /**
     * @return void
     */
    public function editAction()
    {
        $pageId = 0;
        $languageId = 0;

        if ($this->request->hasArgument('id')) {
            $pageId = $this->request->getArgument('id');
        } elseif ((int)GeneralUtility::_GET('id')) {
            $pageId = (int)GeneralUtility::_GET('id');
        }

        if ($pageId === 0) {
            $this->redirect('dashboard');
        }

        if ($this->request->hasArgument('language')) {
            $languageId = $this->request->getArgument('language');
        }
        $targetElementId = uniqid('_YoastSEO_panel_');

        $currentPage = BackendUtility::getRecord(
            'pages',
            (int)$pageId
        );

        if ($languageId > 0) {
            $overlayRecords = BackendUtility::getRecordLocalization(
                'pages',
                (int)$pageId,
                (int)$languageId
            );

            if (is_array($overlayRecords) && array_key_exists(0, $overlayRecords) && is_array($overlayRecords[0])) {
                $currentPage = $overlayRecords[0];
            }
        }
        $focusKeyword = $currentPage[static::FOCUS_KEYWORD_COLUMN_NAME];

        $domain = BackendUtility::getViewDomain($currentPage['uid']);

        // Allow Overwrite of the domain via ExtConf
        if (array_key_exists('previewDomain', $this->configuration) && $this->configuration['previewDomain']) {
            try {
                $protocol = GeneralUtility::getIndpEnv('TYPO3_SSL') ? 'https' : 'http';
            } catch (\UnexpectedValueException $e) {
                $protocol = 'http';
            }

            if (strpos($this->configuration['previewDomain'], '://') !== false) {
                list($protocol, $domainName) = explode('://', $this->configuration['previewDomain']);
            } else {
                $domainName = $this->configuration['previewDomain'];
            }
            $domain = $protocol . '://' . $domainName;
        }

        $previewDataUrl = vsprintf(
            $domain . '/index.php?id=%d&type=%d&L=%d',
            array(
                (int)$pageId,
                static::FE_PREVIEW_TYPE,
                $languageId
            )
        );

        $interfaceLocale = $this->getInterfaceLocale();

        if ($interfaceLocale !== null
            && ($translationFilePath = sprintf(
                static::APP_TRANSLATION_FILE_PATTERN,
                $interfaceLocale
            )) !== false
            && ($translationFilePath = GeneralUtility::getFileAbsFileName(
                $translationFilePath
            )) !== false
            && file_exists($translationFilePath)
        ) {
            $this->pageRenderer->addJsInlineCode(
                md5($translationFilePath),
                'var tx_yoast_seo = tx_yoast_seo || {};'
                . ' tx_yoast_seo.translations = '
                . file_get_contents($translationFilePath)
                . ';'
            );
        }
        $publicResourcesPath = '../../../' . ExtensionManagementUtility::extRelPath('yoast_seo') . 'Resources/Public/';

        $this->pageRenderer->addJsInlineCode(
            'YoastSEO settings',
            'var tx_yoast_seo = tx_yoast_seo || {};'
            . ' tx_yoast_seo.settings = '
            . json_encode(
                array(
                    'focusKeyword' => $focusKeyword,
                    'focusKeywordLabel' => LocalizationUtility::translate('LLL:EXT:yoast_seo/Resources/Private/Language/BackendModule.xlf:focusKeyword', 'yoast_seo'),
                    'preview' => $previewDataUrl,
                    'recordId' => '',
                    'recordTable' => '',
                    'targetElementId' => $targetElementId,
                    'editable' => 1,
                    'disableSlug' => ExtensionManagementUtility::isLoaded('realurl') ? 0 : 1
                )
            )
            . ';'
        );

        $this->pageRenderer->addRequireJsConfiguration(
            array(
                'paths' => array(
                    'YoastSEO' => $publicResourcesPath . 'JavaScript/'
                )
            )
        );

        $this->pageRenderer->loadRequireJsModule('YoastSEO/app');

        $this->pageRenderer->addCssFile(
            $publicResourcesPath . 'CSS/bootstrap.min.css'
        );

        $this->pageRenderer->addCssFile(
            $publicResourcesPath . 'CSS/bootstrap-theme.min.css'
        );

        $this->pageRenderer->addCssFile(
            $publicResourcesPath . 'CSS/yoast-seo.min.css'
        );

        $this->pageRenderer->addJsLibrary('bootstrap', $publicResourcesPath . 'JavaScript/bootstrap.min.js');

        $returnUrl = '';
        if ($this->request->hasArgument('returnUrl')) {
            $returnUrl = $this->request->getArgument('returnUrl');
        }

        $this->view->assign('page', $currentPage);
        $this->view->assign('pageId', $pageId);
        $this->view->assign('languageId', $languageId);
        $this->view->assign('targetElementId', $targetElementId);
        $this->view->assign('returnUrl', $returnUrl);
        $this->view->assign('viewSettings', $this->configuration['viewSettings']);
    }

    public function saveAction()
    {
        $pageId = (int)$this->request->getArgument('id');
        $languageId = (int)$this->request->getArgument('language');

        $fields = array();
        $this->addFieldToArray($fields, $this->settings['titleFieldName'], 'snippet-editor-title');
        $this->addFieldToArray($fields, $this->settings['descriptionFieldName'], 'snippet-editor-meta-description');
        $this->addFieldToArray($fields, 'tx_yoastseo_facebook_title', 'facebookTitle');
        $this->addFieldToArray($fields, 'tx_yoastseo_facebook_description', 'facebookDescription');
        $this->addFieldToArray($fields, 'tx_yoastseo_twitter_title', 'twitterTitle');
        $this->addFieldToArray($fields, 'tx_yoastseo_twitter_description', 'twitterDescription');
        $this->addFieldToArray($fields, $this->settings['canonicalTagFieldName'], 'canonical');
        $this->addFieldToArray($fields, 'tx_yoastseo_robot_instructions', 'robotInstructions');
        $this->addFieldToArray($fields, 'tx_yoastseo_focuskeyword', 'focusKeyword');

        if (ExtensionManagementUtility::isLoaded('realurl')) {
            $this->addFieldToArray($fields, 'tx_realurl_pathsegment', 'snippet-editor-slug', ['/^\/|\/$/', '']);
        }

        $table = 'pages';
        $extraTableRecords = [];
        $recordId = $pageId;

        if ($this->request->hasArgument('twitterImage')) {
            $twitterImage = $this->request->getArgument('twitterImage');

            if ($this->request->hasArgument('twitterDeleteImage') ||
                (array_key_exists('tmp_name', $twitterImage) && !empty($twitterImage['tmp_name']))) {
                $GLOBALS['TYPO3_DB']->exec_UPDATEquery('sys_file_reference', 'fieldname="tx_yoastseo_twitter_image" AND uid_foreign=' . $pageId, ['deleted' => 1]);
            }
            if (array_key_exists('tmp_name', $twitterImage) && !empty($twitterImage['tmp_name'])) {
                $resourceFactory = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance();
                $storage = $resourceFactory->getDefaultStorage();

                if ($storage instanceof ResourceStorage) {
                    $newFile = $storage->addFile(
                        $twitterImage['tmp_name'],
                        $storage->getDefaultFolder(),
                        $twitterImage['name']
                    );

                    $newId = 'NEW1234';
                    $extraTableRecords['sys_file_reference'][$newId] = [
                        'table_local' => 'sys_file',
                        'uid_local' => $newFile->getUid(),
                        'tablenames' => 'pages',
                        'uid_foreign' => $pageId,
                        'fieldname' => 'tx_yoastseo_twitter_image',
                        'pid' => $pageId
                    ];

                    $fields['tx_yoastseo_twitter_image'] = $newId;
                }
            }
        }

        if ($this->request->hasArgument('facebookImage')) {
            $facebookImage = $this->request->getArgument('facebookImage');

            if ($this->request->hasArgument('facebookDeleteImage') ||
                (array_key_exists('tmp_name', $facebookImage) && !empty($facebookImage['tmp_name']))) {
                $GLOBALS['TYPO3_DB']->exec_UPDATEquery('sys_file_reference', 'fieldname="tx_yoastseo_facebook_image" AND uid_foreign=' . $pageId, ['deleted' => 1]);
            }
            if (array_key_exists('tmp_name', $facebookImage) && !empty($facebookImage['tmp_name'])) {
                $resourceFactory = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance();
                $storage = $resourceFactory->getDefaultStorage();

                if ($storage instanceof ResourceStorage) {
                    $newFile = $storage->addFile(
                        $facebookImage['tmp_name'],
                        $storage->getDefaultFolder(),
                        $facebookImage['name']
                    );

                    $newId = 'NEW1234';
                    $extraTableRecords['sys_file_reference'][$newId] = [
                        'table_local' => 'sys_file',
                        'uid_local' => $newFile->getUid(),
                        'tablenames' => 'pages',
                        'uid_foreign' => $pageId,
                        'fieldname' => 'tx_yoastseo_facebook_image',
                        'pid' => $pageId
                    ];

                    $fields['tx_yoastseo_facebook_image'] = $newId;
                }
            }
        }

        if ($languageId > 0) {
            $table = 'pages_language_overlay';

            $overlayRecords = BackendUtility::getRecordLocalization(
                'pages',
                (int)$pageId,
                (int)$languageId
            );

            if (is_array($overlayRecords) && array_key_exists(0, $overlayRecords) && is_array($overlayRecords[0])) {
                $recordId = $overlayRecords[0]['uid'];
            }

            if (array_key_exists('tx_realurl_pathsegment', $fields) && !empty($fields['tx_realurl_pathsegment'])) {
                $extraTableRecords['pages'] =
                    [
                        $pageId => [
                            'tx_realurl_pathoverride' => 1
                        ]
                    ];
            }
        } else {
            if (array_key_exists('tx_realurl_pathsegment', $fields) && !empty($fields['tx_realurl_pathsegment'])) {
                $fields['tx_realurl_pathoverride'] = 1;
            }
        }

        $data = array(
            $table => array(
                $recordId => $fields
            ),
        );

        if (!empty($extraTableRecords)) {
            $data = array_merge($data, $extraTableRecords);
        }

        $tce = GeneralUtility::makeInstance(DataHandler::class);
        $tce->reverseOrder = 1;
        $tce->start($data, array());
        $tce->process_datamap();
        BackendUtility::setUpdateSignal('updatePageTree');
        $tce->clear_cacheCmd('pages');

        $lang = $this->getLanguageService();

        $message = GeneralUtility::makeInstance(
            FlashMessage::class,
            $lang->sL('LLL:EXT:yoast_seo/Resources/Private/Language/BackendModule.xlf:saved.description'),
            $lang->sL('LLL:EXT:yoast_seo/Resources/Private/Language/BackendModule.xlf:saved.title'),
            FlashMessage::OK,
            true
        );

        $flashMessageService = $this->objectManager->get(\TYPO3\CMS\Core\Messaging\FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->addMessage($message);

        $returnUrl = '';
        if ($this->request->hasArgument('returnUrl')) {
            $returnUrl = $this->request->getArgument('returnUrl');
        }

        $this->redirect('edit', null, null, array('id' => $pageId, 'language' => $languageId, 'returnUrl' => $returnUrl));
    }

    public function saveSettingsAction()
    {
        $pageId = (int)$this->request->getArgument('id');
        $languageId = (int)$this->request->getArgument('language');
        $lang = $this->getLanguageService();

        if ($this->request->hasArgument('twitterImage')) {
            $twitterImage = $this->request->getArgument('twitterImage');

            if ($this->request->hasArgument('twitterDeleteImage') ||
                (array_key_exists('tmp_name', $twitterImage) && !empty($twitterImage['tmp_name']))) {
                $GLOBALS['TYPO3_DB']->exec_UPDATEquery('sys_file_reference', 'fieldname="tx_yoastseo_settings_twitter_image" AND uid_foreign=' . $pageId, ['deleted' => 1]);
            }
            if (array_key_exists('tmp_name', $twitterImage) && !empty($twitterImage['tmp_name'])) {
                $resourceFactory = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance();
                $storage = $resourceFactory->getDefaultStorage();

                if ($storage instanceof ResourceStorage) {
                    $newFile = $storage->addFile(
                        $twitterImage['tmp_name'],
                        $storage->getDefaultFolder(),
                        $twitterImage['name']
                    );

                    $newId = 'NEW1234';
                    $extraTableRecords['sys_file_reference'][$newId] = [
                        'table_local' => 'sys_file',
                        'uid_local' => $newFile->getUid(),
                        'tablenames' => 'tx_yoastseo_settings',
                        'uid_foreign' => $pageId,
                        'fieldname' => 'tx_yoastseo_settings_twitter_image',
                        'pid' => $pageId
                    ];
                }
            }
        }

        if ($this->request->hasArgument('facebookImage')) {
            $facebookImage = $this->request->getArgument('facebookImage');

            if ($this->request->hasArgument('facebookDeleteImage') ||
                (array_key_exists('tmp_name', $facebookImage) && !empty($facebookImage['tmp_name']))) {
                $GLOBALS['TYPO3_DB']->exec_UPDATEquery('sys_file_reference', 'fieldname="tx_yoastseo_settings_facebook_image" AND uid_foreign=' . $pageId, ['deleted' => 1]);
            }
            if (array_key_exists('tmp_name', $facebookImage) && !empty($facebookImage['tmp_name'])) {
                $resourceFactory = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance();
                $storage = $resourceFactory->getDefaultStorage();

                if ($storage instanceof ResourceStorage) {
                    $newFile = $storage->addFile(
                        $facebookImage['tmp_name'],
                        $storage->getDefaultFolder(),
                        $facebookImage['name']
                    );

                    $newId = 'NEW1234';
                    $extraTableRecords['sys_file_reference'][$newId] = [
                        'table_local' => 'sys_file',
                        'uid_local' => $newFile->getUid(),
                        'tablenames' => 'tx_yoastseo_settings',
                        'uid_foreign' => $pageId,
                        'fieldname' => 'tx_yoastseo_settings_facebook_image',
                        'pid' => $pageId
                    ];
                }
            }
        }

        $tce = GeneralUtility::makeInstance(DataHandler::class);
        $tce->reverseOrder = 1;
        $tce->start($extraTableRecords, array());
        $tce->process_datamap();
        $tce->clear_cacheCmd('pages');

        $message = GeneralUtility::makeInstance(
            FlashMessage::class,
            $lang->sL('LLL:EXT:yoast_seo/Resources/Private/Language/BackendModule.xlf:saved.description'),
            $lang->sL('LLL:EXT:yoast_seo/Resources/Private/Language/BackendModule.xlf:saved.title'),
            FlashMessage::OK,
            true
        );

        $flashMessageService = $this->objectManager->get(\TYPO3\CMS\Core\Messaging\FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->addMessage($message);

        $returnUrl = '';
        if ($this->request->hasArgument('returnUrl')) {
            $returnUrl = $this->request->getArgument('returnUrl');
        }

        $this->redirect('settings', null, null, array('id' => $pageId, 'language' => $languageId));
    }

    /**
     * @param array $fields
     * @param string $key
     * @param string $fieldName
     * @param array $replace
     *
     * @return void
     */
    protected function addFieldToArray(&$fields, $key, $fieldName = null, $replace = [])
    {
        if ($fieldName === null) {
            $fieldName = $key;
        }

        if ($this->request->hasArgument($fieldName)) {
            $value = $this->request->getArgument($fieldName);

            if (!empty($replace)) {
                $value = preg_replace($replace[0], $replace[1], $value);
            }
            $fields[$key] = $value;
        }
    }

    /**
     * Make the LanguageMenu
     *
     * @return void
     */
    protected function makeLanguageMenu()
    {
        if (count($this->MOD_MENU['language']) > 1) {
            $lang = $this->getLanguageService();
            $languageMenu = $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
            $languageMenu->setIdentifier('languageMenu');
            $languageMenu->setLabel($lang->sL('LLL:EXT:lang/locallang_general.xlf:LGL.language', true));
            foreach ($this->MOD_MENU['language'] as $key => $language) {
                $menuItem = $languageMenu
                    ->makeMenuItem()
                    ->setTitle($language)
                    ->setHref(BackendUtility::getModuleUrl($this->moduleName) . '&id=' . $this->id . '&SET[language]=' . $key);
                if ((int)$this->current_sys_language === $key) {
                    $menuItem->setActive(true);
                }
                $languageMenu->addMenuItem($menuItem);
            }
            $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($languageMenu);
        }
    }

    /**
     * @return DatabaseConnection
     */
    protected function getDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
    }

    /**
     * Returns LanguageService
     *
     * @return \TYPO3\CMS\Lang\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Try to resolve a supported locale based on the user settings
     * take the configured locale dependencies into account
     * so if the TYPO3 interface is tailored for a specific dialect
     * the local of a parent language might be used
     *
     * @return string|null
     */
    protected function getInterfaceLocale()
    {
        $locale = null;
        $languageChain = null;

        if ($GLOBALS['BE_USER'] instanceof BackendUserAuthentication
            && is_array($GLOBALS['BE_USER']->uc)
            && array_key_exists('lang', $GLOBALS['BE_USER']->uc)
            && !empty($GLOBALS['BE_USER']->uc['lang'])
        ) {
            $languageChain = $this->localeService->getLocaleDependencies(
                $GLOBALS['BE_USER']->uc['lang']
            );

            array_unshift($languageChain, $GLOBALS['BE_USER']->uc['lang']);
        }

        // try to find a matching locale available for this plugins UI
        // take configured locale dependencies into account
        if ($languageChain !== null
            && ($suitableLocales = array_intersect(
                $languageChain,
                $this->configuration['translations']['availableLocales']
            )) !== false
            && count($suitableLocales) > 0
        ) {
            $locale = array_shift($suitableLocales);
        }

        // if a locale couldn't be resolved try if an entry of the
        // language dependency chain matches legacy mapping
        if ($locale === null && $languageChain !== null
            && ($suitableLanguageKeys = array_intersect(
                $languageChain,
                array_flip(
                    $this->configuration['translations']['languageKeyToLocaleMapping']
                )
            )) !== false
            && count($suitableLanguageKeys) > 0
        ) {
            $locale = $this->configuration['translations']['languageKeyToLocaleMapping'][array_shift($suitableLanguageKeys)];
        }

        return $locale;
    }
}
