<?php

/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

/*
 * @since   1.5.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

include_once __DIR__ . '/Ks_HomeSlide.php';

class Ks_ImageSliderResponsive extends Module implements WidgetInterface
{
    protected $_html = '';
    protected $default_speed = 5000;
    protected $default_pause_on_hover = 1;
    protected $default_wrap = 1;
    protected $templateFile;

    private $image_types = ['desktop', 'mobile'];
    
    const MODULE_TRANSLATION_KEY = 'Modules.Imageslider.Admin';

    /**
     * @var string
     */
    public $secure_key;

    public function __construct()
    {
        $this->name = 'ks_imagesliderresponsive';
        $this->tab = 'front_office_features';
        $this->version = '3.1.2';
        $this->author = 'Karol Sawicki';
        $this->need_instance = 0;
        $this->secure_key = Tools::hash($this->name);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Responsive image slider', [], self::MODULE_TRANSLATION_KEY);
        $this->description = $this->trans('Add sliding responsive images to your homepage to welcome your visitors in a visual and friendly way.', [], self::MODULE_TRANSLATION_KEY);
        $this->ps_versions_compliancy = ['min' => '1.7.4.0', 'max' => _PS_VERSION_];

        $this->templateFile = 'module:ks_imagesliderresponsive/views/templates/hook/slider.tpl';
    }

    /**
     * @see Module::install()
     */
    public function install()
    {
        /* Adds Module */
        if (
            parent::install() &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('displayHome') &&
            $this->registerHook('actionShopDataDuplication')
        ) {
            $shops = Shop::getContextListShopID();
            $shop_groups_list = [];
            $res = true;

            /* Setup each shop */
            foreach ($shops as $shop_id) {
                $shop_group_id = (int) Shop::getGroupFromShop($shop_id, true);

                if (!in_array($shop_group_id, $shop_groups_list)) {
                    $shop_groups_list[] = $shop_group_id;
                }

                /* Sets up configuration */
                $res &= Configuration::updateValue('HOMESLIDER_SPEED', $this->default_speed, false, $shop_group_id, $shop_id);
                $res &= Configuration::updateValue('HOMESLIDER_PAUSE_ON_HOVER', $this->default_pause_on_hover, false, $shop_group_id, $shop_id);
                $res &= Configuration::updateValue('HOMESLIDER_WRAP', $this->default_wrap, false, $shop_group_id, $shop_id);
            }

            /* Sets up Shop Group configuration */
            if (count($shop_groups_list)) {
                foreach ($shop_groups_list as $shop_group_id) {
                    $res &= Configuration::updateValue('HOMESLIDER_SPEED', $this->default_speed, false, $shop_group_id);
                    $res &= Configuration::updateValue('HOMESLIDER_PAUSE_ON_HOVER', $this->default_pause_on_hover, false, $shop_group_id);
                    $res &= Configuration::updateValue('HOMESLIDER_WRAP', $this->default_wrap, false, $shop_group_id);
                }
            }

            /* Sets up Global configuration */
            $res &= Configuration::updateValue('HOMESLIDER_SPEED', $this->default_speed);
            $res &= Configuration::updateValue('HOMESLIDER_PAUSE_ON_HOVER', $this->default_pause_on_hover);
            $res &= Configuration::updateValue('HOMESLIDER_WRAP', $this->default_wrap);

            /* Creates tables */
            $res &= $this->createTables();

            /* Adds samples */
            if ($res) {
                $this->installSamples();
            }

            return (bool) $res;
        }

        return false;
    }

    /**
     * Adds samples
     */
    protected function installSamples()
    {
        $languages = Language::getLanguages(false);
        for ($i = 1; $i <= 3; ++$i) {
            $slide = new Ks_HomeSlide();
            $slide->position = $i;
            $slide->active = 1;
            foreach ($languages as $language) {
                $slide->title[$language['id_lang']] = 'Sample ' . $i;
                $slide->description[$language['id_lang']] = '<h3>EXCEPTEUR OCCAECAT</h3>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Proin tristique in tortor et dignissim. Quisque non tempor leo. Maecenas egestas sem elit</p>';
                $slide->legend[$language['id_lang']] = 'sample-' . $i;
                $slide->url[$language['id_lang']] = 'https://www.prestashop-project.org?utm_source=back-office&utm_medium=v17_homeslider'
                    . '&utm_campaign=back-office-' . Tools::strtoupper($this->context->language->iso_code)
                    . '&utm_content=' . (defined('_PS_HOST_MODE_') ? 'ondemand' : 'download');
                $rtlSuffix = $language['is_rtl'] ? '_rtl' : '';
                foreach ($this->image_types as $image_type) {
                    $slide->{'image_' . $image_type}[$language['id_lang']] = sprintf('sample-%d%s.jpg', $i, $rtlSuffix);
                }
            }
            $slide->add();
        }
    }

    /**
     * @see Module::uninstall()
     */
    public function uninstall()
    {
        /* Deletes Module */
        if (parent::uninstall()) {
            /* Deletes tables */
            $res = $this->deleteTables();

            /* Unsets configuration */
            $res &= Configuration::deleteByName('HOMESLIDER_SPEED');
            $res &= Configuration::deleteByName('HOMESLIDER_PAUSE_ON_HOVER');
            $res &= Configuration::deleteByName('HOMESLIDER_WRAP');

            return (bool) $res;
        }

        return false;
    }

    /**
     * Creates tables
     */
    protected function createTables()
    {
        /* Slides */
        $res = (bool) Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'homeslider` (
                `id_homeslider_slides` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `id_shop` int(10) unsigned NOT NULL,
                PRIMARY KEY (`id_homeslider_slides`, `id_shop`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=UTF8;
        ');

        /* Slides configuration */
        $res &= Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'homeslider_slides` (
              `id_homeslider_slides` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `position` int(10) unsigned NOT NULL DEFAULT \'0\',
              `active` tinyint(1) unsigned NOT NULL DEFAULT \'0\',
              PRIMARY KEY (`id_homeslider_slides`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=UTF8;
        ');

        /* Slides lang configuration */
        $res &= Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'homeslider_slides_lang` (
              `id_homeslider_slides` int(10) unsigned NOT NULL,
              `id_lang` int(10) unsigned NOT NULL,
              `title` varchar(255) NOT NULL,
              `description` text NOT NULL,
              `legend` varchar(255) NOT NULL,
              `url` varchar(255) NOT NULL,
              `image_desktop` varchar(255) NOT NULL,
              `image_mobile` varchar(255) NOT NULL,
              PRIMARY KEY (`id_homeslider_slides`,`id_lang`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=UTF8;
        ');

        return $res;
    }

    /**
     * deletes tables
     */
    protected function deleteTables()
    {
        $slides = $this->getSlides();
        foreach ($slides as $slide) {
            $to_del = new Ks_HomeSlide($slide['id_slide']);
            $to_del->delete();
        }

        return Db::getInstance()->execute('
            DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'homeslider`, `' . _DB_PREFIX_ . 'homeslider_slides`, `' . _DB_PREFIX_ . 'homeslider_slides_lang`;
        ');
    }

    public function getContent()
    {
        $this->_html .= $this->headerHTML();

        /* Validate & process */
        if (
            Tools::isSubmit('submitSlide') ||
            Tools::isSubmit('delete_id_slide') ||
            Tools::isSubmit('submitSlider') ||
            Tools::isSubmit('changeStatus')
        ) {
            if ($this->_postValidation()) {
                $this->_postProcess();
                $this->_html .= $this->renderForm();
                $this->_html .= $this->renderList();
            } else {
                $this->_html .= $this->renderAddForm();
            }

            $this->clearCache();
        } elseif (Tools::isSubmit('addSlide') || (Tools::isSubmit('id_slide') && $this->slideExists((int) Tools::getValue('id_slide')))) {
            if (Tools::isSubmit('addSlide')) {
                $mode = 'add';
            } else {
                $mode = 'edit';
            }

            if ($mode == 'add') {
                if (Shop::getContext() != Shop::CONTEXT_GROUP && Shop::getContext() != Shop::CONTEXT_ALL) {
                    $this->_html .= $this->renderAddForm();
                } else {
                    $this->_html .= $this->getShopContextError(null, $mode);
                }
            } else {
                $associated_shop_ids = Ks_HomeSlide::getAssociatedIdsShop((int) Tools::getValue('id_slide'));
                $context_shop_id = (int) Shop::getContextShopID();

                if ($associated_shop_ids === false) {
                    $this->_html .= $this->getShopAssociationError((int) Tools::getValue('id_slide'));
                } elseif (Shop::getContext() != Shop::CONTEXT_GROUP && Shop::getContext() != Shop::CONTEXT_ALL && in_array($context_shop_id, $associated_shop_ids)) {
                    if (count($associated_shop_ids) > 1) {
                        $this->_html = $this->getSharedSlideWarning();
                    }
                    $this->_html .= $this->renderAddForm();
                } else {
                    $shops_name_list = [];
                    foreach ($associated_shop_ids as $shop_id) {
                        $associated_shop = new Shop((int) $shop_id);
                        $shops_name_list[] = $associated_shop->name;
                    }
                    $this->_html .= $this->getShopContextError($shops_name_list, $mode);
                }
            }
        } else {
            $this->_html .= $this->getWarningMultishopHtml() . $this->getCurrentShopInfoMsg() . $this->renderForm();

            if (Shop::getContext() != Shop::CONTEXT_GROUP && Shop::getContext() != Shop::CONTEXT_ALL) {
                $this->_html .= $this->renderList();
            }
        }

        return $this->_html;
    }

    protected function _postValidation()
    {
        $errors = [];

        /* Validation for Slider configuration */
        if (Tools::isSubmit('submitSlider')) {
            if (!Validate::isInt(Tools::getValue('HOMESLIDER_SPEED'))) {
                $errors[] = $this->trans('Invalid values', [], self::MODULE_TRANSLATION_KEY);
            }
        } elseif (Tools::isSubmit('changeStatus')) {
            if (!Validate::isInt(Tools::getValue('id_slide'))) {
                $errors[] = $this->trans('Invalid slide', [], self::MODULE_TRANSLATION_KEY);
            }
        } elseif (Tools::isSubmit('submitSlide')) {
            /* Checks state (active) */
            if (!Validate::isInt(Tools::getValue('active_slide')) || (Tools::getValue('active_slide') != 0 && Tools::getValue('active_slide') != 1)) {
                $errors[] = $this->trans('Invalid slide state.', [], self::MODULE_TRANSLATION_KEY);
            }
            /* If edit : checks id_slide */
            if (Tools::isSubmit('id_slide')) {
                if (!Validate::isInt(Tools::getValue('id_slide')) && !$this->slideExists(Tools::getValue('id_slide'))) {
                    $errors[] = $this->trans('Invalid slide ID', [], self::MODULE_TRANSLATION_KEY);
                }
            }
            /* Checks title/url/legend/description/image */
            $languages = Language::getLanguages(false);
            foreach ($languages as $language) {
                if (Tools::strlen(Tools::getValue('title_' . $language['id_lang'])) > 255) {
                    $errors[] = $this->trans('The title is too long.', [], self::MODULE_TRANSLATION_KEY);
                }
                if (Tools::strlen(Tools::getValue('legend_' . $language['id_lang'])) > 255) {
                    $errors[] = $this->trans('The caption is too long.', [], self::MODULE_TRANSLATION_KEY);
                }
                if (Tools::strlen(Tools::getValue('url_' . $language['id_lang'])) > 255) {
                    $errors[] = $this->trans('The URL is too long.', [], self::MODULE_TRANSLATION_KEY);
                }
                if (Tools::strlen(Tools::getValue('description_' . $language['id_lang'])) > 4000) {
                    $errors[] = $this->trans('The description is too long.', [], self::MODULE_TRANSLATION_KEY);
                }
                if (Tools::strlen(Tools::getValue('url_' . $language['id_lang'])) > 0 && !Validate::isUrl(Tools::getValue('url_' . $language['id_lang']))) {
                    $errors[] = $this->trans('The URL format is not correct.', [], self::MODULE_TRANSLATION_KEY);
                }
                foreach ($this->image_types as $image_type) {
                    $image_name = 'image_' . $image_type . '_' . $language['id_lang'];
                    if (Tools::getValue($image_name) != null && !Validate::isFileName(Tools::getValue($image_name))) {
                        $errors[] = $this->trans('Invalid ' . $image_type . ' filename.', [], self::MODULE_TRANSLATION_KEY);
                    }
                }
                if (Tools::getValue('image_old_' . $language['id_lang']) != null && !Validate::isFileName(Tools::getValue('image_old_' . $language['id_lang']))) {
                    $errors[] = $this->trans('Invalid filename.', [], self::MODULE_TRANSLATION_KEY);
                }
            }

            /* Checks title/legend/description for default lang */
            $id_lang_default = (int) Configuration::get('PS_LANG_DEFAULT');
            if (!Tools::isSubmit('id_slide')) {
                foreach ($this->image_types as $image_type) {
                    $image = $_FILES['image_' . $image_type . '_' . $id_lang_default];
                    if (!Tools::isSubmit('has_picture') && (!isset($image) || empty($image['tmp_name']))) {
                        $errors[] = $this->trans('The ' . $image_type . ' image is not set.', [], self::MODULE_TRANSLATION_KEY);
                    }
                }

            }
            if (Tools::getValue('image_old_' . $id_lang_default) && !Validate::isFileName(Tools::getValue('image_old_' . $id_lang_default))) {
                $errors[] = $this->trans('The image is not set.', [], self::MODULE_TRANSLATION_KEY);
            }
        } elseif (Tools::isSubmit('delete_id_slide') && (!Validate::isInt(Tools::getValue('delete_id_slide')) || !$this->slideExists((int) Tools::getValue('delete_id_slide')))) {
            $errors[] = $this->trans('Invalid slide ID', [], self::MODULE_TRANSLATION_KEY);
        }

        /* Display errors if needed */
        if (count($errors)) {
            $this->_html .= $this->displayError(implode('<br />', $errors));

            return false;
        }

        /* Returns if validation is ok */

        return true;
    }

    private function getImage($image_type, $language)
    {
        return $_FILES['image_' . $image_type . '_' . $language['id_lang']];
    }

    protected function _postProcess()
    {
        $errors = [];
        $shop_context = Shop::getContext();

        /* Processes Slider */
        if (Tools::isSubmit('submitSlider')) {
            $shop_groups_list = [];
            $shops = Shop::getContextListShopID();
            $res = true;

            foreach ($shops as $shop_id) {
                $shop_group_id = (int) Shop::getGroupFromShop($shop_id, true);

                if (!in_array($shop_group_id, $shop_groups_list)) {
                    $shop_groups_list[] = $shop_group_id;
                }

                $res &= Configuration::updateValue('HOMESLIDER_SPEED', (int) Tools::getValue('HOMESLIDER_SPEED'), false, $shop_group_id, $shop_id);
                $res &= Configuration::updateValue('HOMESLIDER_PAUSE_ON_HOVER', (int) Tools::getValue('HOMESLIDER_PAUSE_ON_HOVER'), false, $shop_group_id, $shop_id);
                $res &= Configuration::updateValue('HOMESLIDER_WRAP', (int) Tools::getValue('HOMESLIDER_WRAP'), false, $shop_group_id, $shop_id);
            }

            /* Update global shop context if needed*/
            switch ($shop_context) {
                case Shop::CONTEXT_ALL:
                    $res &= Configuration::updateValue('HOMESLIDER_SPEED', (int) Tools::getValue('HOMESLIDER_SPEED'));
                    $res &= Configuration::updateValue('HOMESLIDER_PAUSE_ON_HOVER', (int) Tools::getValue('HOMESLIDER_PAUSE_ON_HOVER'));
                    $res &= Configuration::updateValue('HOMESLIDER_WRAP', (int) Tools::getValue('HOMESLIDER_WRAP'));
                    if (count($shop_groups_list)) {
                        foreach ($shop_groups_list as $shop_group_id) {
                            $res &= Configuration::updateValue('HOMESLIDER_SPEED', (int) Tools::getValue('HOMESLIDER_SPEED'), false, $shop_group_id);
                            $res &= Configuration::updateValue('HOMESLIDER_PAUSE_ON_HOVER', (int) Tools::getValue('HOMESLIDER_PAUSE_ON_HOVER'), false, $shop_group_id);
                            $res &= Configuration::updateValue('HOMESLIDER_WRAP', (int) Tools::getValue('HOMESLIDER_WRAP'), false, $shop_group_id);
                        }
                    }
                    break;
                case Shop::CONTEXT_GROUP:
                    if (count($shop_groups_list)) {
                        foreach ($shop_groups_list as $shop_group_id) {
                            $res &= Configuration::updateValue('HOMESLIDER_SPEED', (int) Tools::getValue('HOMESLIDER_SPEED'), false, $shop_group_id);
                            $res &= Configuration::updateValue('HOMESLIDER_PAUSE_ON_HOVER', (int) Tools::getValue('HOMESLIDER_PAUSE_ON_HOVER'), false, $shop_group_id);
                            $res &= Configuration::updateValue('HOMESLIDER_WRAP', (int) Tools::getValue('HOMESLIDER_WRAP'), false, $shop_group_id);
                        }
                    }
                    break;
            }

            $this->clearCache();

            if (!$res) {
                $errors[] = $this->displayError($this->trans('The configuration could not be updated.', [], self::MODULE_TRANSLATION_KEY));
            } else {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true) . '&conf=6&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name);
            }
        } elseif (Tools::isSubmit('changeStatus') && Tools::isSubmit('id_slide')) {
            $slide = new Ks_HomeSlide((int) Tools::getValue('id_slide'));
            if ($slide->active == 0) {
                $slide->active = 1;
            } else {
                $slide->active = 0;
            }
            $res = $slide->update();
            $this->clearCache();
            $this->_html .= ($res ? $this->displayConfirmation($this->trans('Configuration updated', [], 'Admin.Notifications.Success')) : $this->displayError($this->getTranslator()->trans('The configuration could not be updated.', [], self::MODULE_TRANSLATION_KEY)));
        } elseif (Tools::isSubmit('submitSlide')) {
            /* Sets ID if needed */
            if (Tools::getValue('id_slide')) {
                $slide = new Ks_HomeSlide((int) Tools::getValue('id_slide'));
                if (!Validate::isLoadedObject($slide)) {
                    $this->_html .= $this->displayError($this->trans('Invalid slide ID', [], self::MODULE_TRANSLATION_KEY));

                    return false;
                }
            } else {
                $slide = new Ks_HomeSlide();
                /* Sets position */
                $slide->position = (int) $this->getNextPosition();
            }
            /* Sets active */
            $slide->active = (int) Tools::getValue('active_slide');

            /* Sets each langue fields */
            $languages = Language::getLanguages(false);

            foreach ($languages as $language) {
                $slide->title[$language['id_lang']] = Tools::getValue('title_' . $language['id_lang']);
                $slide->url[$language['id_lang']] = Tools::getValue('url_' . $language['id_lang']);
                $slide->legend[$language['id_lang']] = Tools::getValue('legend_' . $language['id_lang']);
                $slide->description[$language['id_lang']] = Tools::getValue('description_' . $language['id_lang']);

                /* Uploads image and sets slide */
                foreach($this->image_types as $image_type) {
                    $type = '';
                    $imagesize = 0;
                    $uploaded_image = $this->getImage($image_type, $language);
                    var_dump("[_FILES]" . $image_type);
                    print("<pre>" . print_r($_FILES, true) . "</pre>");

                    if (
                        isset($uploaded_image) &&
                        !empty($uploaded_image['tmp_name'])
                    ) {
                        $type = Tools::strtolower(Tools::substr(strrchr($uploaded_image['name'], '.'), 1));
                        $imagesize = @getimagesize($uploaded_image['tmp_name']);
                    }

                    var_dump("type empty " . $image_type);
                    print("<pre>" . print_r($type, true) . "</pre>");
                    
                    var_dump("imagesize empty " . $image_type);
                    print("<pre>" . print_r($imagesize, true) . "</pre>");
    
                    if (
                        !empty($type) &&
                        !empty($imagesize) &&
                        in_array(
                            Tools::strtolower(Tools::substr(strrchr($imagesize['mime'], '/'), 1)),
                            [
                                'jpg',
                                'gif',
                                'jpeg',
                                'png',
                            ]
                        ) &&
                        in_array($type, ['jpg', 'gif', 'jpeg', 'png'])
                    ) {
                        $temp_name = tempnam(_PS_TMP_IMG_DIR_, 'PS');
                        $salt = sha1(microtime());
                        var_dump("UPLOADED_IMAGe" . $image_type);
                        print("<pre>" . print_r($uploaded_image, true) . "</pre>");
                        if ($error = ImageManager::validateUpload($uploaded_image)) {
                            $errors[] = $error;
                        } elseif (!$temp_name || !move_uploaded_file($uploaded_image['tmp_name'], $temp_name)) {
                            return false;
                        } elseif (!ImageManager::resize($temp_name, __DIR__ . '/images/' . $salt . '_' . $uploaded_image['name'], null, null, $type)) {
                            $errors[] = $this->displayError($this->trans('An error occurred during the ' . $image_type . ' image upload process.', [], 'Admin.Notifications.Error'));
                        }
                        if (file_exists($temp_name)) {
                            @unlink($temp_name);
                        }
                        $slide->{'image_' . $image_type}[$language['id_lang']] = $salt . '_' . $uploaded_image['name'];
                    } elseif (Tools::getValue('image_old_' . $language['id_lang']) != '') {
                        $slide->{'image_' . $image_type}[$language['id_lang']] = Tools::getValue('image_old_' . $language['id_lang']);
                    }
                }
            }

            /* Processes if no errors  */
            if (!$errors) {
                /* Adds */
                if (!Tools::getValue('id_slide')) {
                    if (!$slide->add()) {
                        $errors[] = $this->displayError($this->trans('The slide could not be added.', [], self::MODULE_TRANSLATION_KEY));
                    }
                } elseif (!$slide->update()) {
                    $errors[] = $this->displayError($this->trans('The slide could not be updated.', [], self::MODULE_TRANSLATION_KEY));
                }
                $this->clearCache();
            }
        } elseif (Tools::isSubmit('delete_id_slide')) {
            $slide = new Ks_HomeSlide((int) Tools::getValue('delete_id_slide'));
            $res = $slide->delete();
            $this->clearCache();
            if (!$res) {
                $this->_html .= $this->displayError('Could not delete.');
            } else {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true) . '&conf=1&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name);
            }
        }

        /* Display errors if needed */
        if (count($errors)) {
            $this->_html .= $this->displayError(implode('<br />', $errors));
        } elseif (Tools::isSubmit('submitSlide') && Tools::getValue('id_slide')) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true) . '&conf=4&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name);
        } elseif (Tools::isSubmit('submitSlide')) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true) . '&conf=3&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name);
        }
    }

    public function hookdisplayHeader($params)
    {
        $this->context->controller->registerStylesheet('modules-homeslider', 'modules/' . $this->name . '/css/homeslider.css', ['media' => 'all', 'priority' => 150]);
        $this->context->controller->registerJavascript('modules-responsiveslides', 'modules/' . $this->name . '/js/responsiveslides.min.js', ['position' => 'bottom', 'priority' => 150]);
        $this->context->controller->registerJavascript('modules-homeslider', 'modules/' . $this->name . '/js/homeslider.js', ['position' => 'bottom', 'priority' => 150]);
    }

    public function renderWidget($hookName = null, array $configuration = [])
    {
        if (!$this->isCached($this->templateFile, $this->getCacheId())) {
            $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));
        }

        return $this->fetch($this->templateFile, $this->getCacheId());
    }

    public function getWidgetVariables($hookName = null, array $configuration = [])
    {
        $slides = $this->getSlides(true);
        if (is_array($slides)) {
            foreach ($slides as &$slide) {
                foreach ($this->image_types as $image_type) {
                    $key = 'image_' . $image_type;
                    $slide['sizes'][$key] = @getimagesize((__DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $slide[$key]));
                    if (isset($slide['sizes'][$key][3]) && $slide['sizes'][$key][3]) {
                        $slide['size'][$key] = $slide['sizes'][$key][3];
                    }
                }
            }
        }

        $config = $this->getConfigFieldsValues();

        return [
            'homeslider' => [
                'speed' => $config['HOMESLIDER_SPEED'],
                'pause' => $config['HOMESLIDER_PAUSE_ON_HOVER'] ? 'hover' : '',
                'wrap' => $config['HOMESLIDER_WRAP'] ? 'true' : 'false',
                'slides' => $slides,
            ],
        ];
    }

    protected function validateUrl($link)
    {
        // Empty or anchor link.
        if (empty($link) || 0 === strpos($link, '#')) {
            return $link;
        }

        $host = parse_url($link, PHP_URL_HOST);
        // links starting with http://, https:// or // have $host determined, the rest needs more validation
        if (empty($host)) {
            if (preg_match('/^(?!\-|index\.php)(?:(?:[a-z\d][a-z\d\-]{0,61})?[a-z\d]\.){1,126}(?!\d+)[a-z\d]{1,63}/i', $link)) {
                // handle strings considered to be domain names without protocol eg. 'prestashop.com', excluding 'index.php'
                // ref. https://stackoverflow.com/a/16491074/6389945
                $link = '//' . $link;
            } else {
                // consider other links shop internal and add shop domain in front
                $link = $this->context->link->getBaseLink() . ltrim($link, '/');
            }
        }

        return $link;
    }

    public function clearCache()
    {
        $this->_clearCache($this->templateFile);
    }

    public function hookActionShopDataDuplication($params)
    {
        Db::getInstance()->execute(
            'INSERT IGNORE INTO ' . _DB_PREFIX_ . 'homeslider (id_homeslider_slides, id_shop)
            SELECT id_homeslider_slides, ' . (int) $params['new_id_shop'] . '
            FROM ' . _DB_PREFIX_ . 'homeslider
            WHERE id_shop = ' . (int) $params['old_id_shop']
        );
        $this->clearCache();
    }

    public function headerHTML()
    {
        if ('AdminModules' !== Tools::getValue('controller') ||
            Tools::getValue('configure') !== $this->name ||
            Tools::getIsset('id_slide')) {
            return;
        }

        $this->context->controller->addJS($this->_path . 'js/Sortable.min.js');
        /* Style & js for fieldset 'slides configuration' */
        $html = '<script type="text/javascript">
              $(function() {
                var $mySlides = $("#slides");
                new Sortable($mySlides[0], {
                  animation: 150,
                  onUpdate: function(event) {
                    var order = this.toArray().join("&") + "&action=updateSlidesPosition";
                    $.post("' . $this->context->shop->physical_uri . $this->context->shop->virtual_uri . 'modules/' . $this->name . '/ajax_' . $this->name . '.php?secure_key=' . $this->secure_key . '", order);
                  }
                });
                $mySlides.hover(function() {
                    $(this).css("cursor","move");
                    },
                    function() {
                    $(this).css("cursor","auto");
                });
            });
        </script>';

        return $html;
    }

    public function getNextPosition()
    {
        $row = Db::getInstance((bool) _PS_USE_SQL_SLAVE_)->getRow(
            'SELECT MAX(hss.`position`) AS `next_position`
            FROM `' . _DB_PREFIX_ . 'homeslider_slides` hss, `' . _DB_PREFIX_ . 'homeslider` hs
            WHERE hss.`id_homeslider_slides` = hs.`id_homeslider_slides` AND hs.`id_shop` = ' . (int) $this->context->shop->id
        );

        return ++$row['next_position'];
    }

    public function getSlides($active = null)
    {
        $this->context = Context::getContext();
        $id_shop = $this->context->shop->id;
        $id_lang = $this->context->language->id;

        $slides = Db::getInstance((bool) _PS_USE_SQL_SLAVE_)->executeS(
            'SELECT hs.`id_homeslider_slides` as id_slide, hss.`position`, hss.`active`, hssl.`title`,
            hssl.`url`, hssl.`legend`, hssl.`description`, hssl.`image_desktop`, hssl.`image_mobile`
            FROM ' . _DB_PREFIX_ . 'homeslider hs
            LEFT JOIN ' . _DB_PREFIX_ . 'homeslider_slides hss ON (hs.id_homeslider_slides = hss.id_homeslider_slides)
            LEFT JOIN ' . _DB_PREFIX_ . 'homeslider_slides_lang hssl ON (hss.id_homeslider_slides = hssl.id_homeslider_slides)
            WHERE id_shop = ' . (int) $id_shop . '
            AND hssl.id_lang = ' . (int) $id_lang . '
            AND hssl.`image_desktop` <> ""' . '
            AND hssl.`image_mobile` <> ""' .
            ($active ? ' AND hss.`active` = 1' : ' ') . '
            ORDER BY hss.position'
        );

        foreach ($slides as &$slide) {
            foreach($this->image_types as $image_type) {
                $key = 'image_' . $image_type;
                $slide[$key . '_url'] = $this->context->link->getMediaLink(_MODULE_DIR_ . 'ks_imagesliderresponsive/images/' . $slide[$key]);
            }
            $slide['url'] = $this->validateUrl($slide['url']);
        }

        return $slides;
    }

    public function getAllImagesBySlidesId($id_slides, $active = null, $id_shop = null)
    {
        $this->context = Context::getContext();
        $images = [];

        if (!isset($id_shop)) {
            $id_shop = $this->context->shop->id;
        }

        $results = Db::getInstance((bool) _PS_USE_SQL_SLAVE_)->executeS(
            'SELECT hssl.`image_desktop`, hssl.`image_mobile`, hssl.`id_lang`
            FROM ' . _DB_PREFIX_ . 'homeslider hs
            LEFT JOIN ' . _DB_PREFIX_ . 'homeslider_slides hss ON (hs.id_homeslider_slides = hss.id_homeslider_slides)
            LEFT JOIN ' . _DB_PREFIX_ . 'homeslider_slides_lang hssl ON (hss.id_homeslider_slides = hssl.id_homeslider_slides)
            WHERE hs.`id_homeslider_slides` = ' . (int) $id_slides . ' AND hs.`id_shop` = ' . (int) $id_shop .
            ($active ? ' AND hss.`active` = 1' : ' ')
        );

        foreach ($results as $result) {
            foreach($this->image_types as $image_type) {
                $key = 'image_' . $image_type;
                $images[$key][$result['id_lang']] = $result[$key];
            }
        }

        return $images;
    }

    public function displayStatus($id_slide, $active)
    {
        $title = ((int) $active == 0 ? $this->trans('Disabled', [], 'Admin.Global') : $this->trans('Enabled', [], 'Admin.Global'));
        $icon = ((int) $active == 0 ? 'icon-remove' : 'icon-check');
        $class = ((int) $active == 0 ? 'btn-danger' : 'btn-success');
        $html = '<a class="btn ' . $class . '" href="' . AdminController::$currentIndex .
            '&configure=' . $this->name .
            '&token=' . Tools::getAdminTokenLite('AdminModules') .
            '&changeStatus&id_slide=' . (int) $id_slide . '" title="' . $title . '"><i class="' . $icon . '"></i> ' . $title . '</a>';

        return $html;
    }

    public function slideExists($id_slide)
    {
        $req = 'SELECT hs.`id_homeslider_slides` as id_slide
                FROM `' . _DB_PREFIX_ . 'homeslider` hs
                WHERE hs.`id_homeslider_slides` = ' . (int) $id_slide;
        $row = Db::getInstance((bool) _PS_USE_SQL_SLAVE_)->getRow($req);

        return $row;
    }

    public function renderList()
    {
        $slides = $this->getSlides();
        foreach ($slides as $key => $slide) {
            $slides[$key]['status'] = $this->displayStatus($slide['id_slide'], $slide['active']);
            $associated_shop_ids = Ks_HomeSlide::getAssociatedIdsShop((int) $slide['id_slide']);
            if ($associated_shop_ids && count($associated_shop_ids) > 1) {
                $slides[$key]['is_shared'] = true;
            } else {
                $slides[$key]['is_shared'] = false;
            }
        }

        $this->context->smarty->assign(
            [
                'link' => $this->context->link,
                'slides' => $slides,
                'image_baseurl' => $this->_path . 'images/',
            ]
        );

        return $this->display(__FILE__, 'list.tpl');
    }

    public function renderAddForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Slide information', [], self::MODULE_TRANSLATION_KEY),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'file_lang',
                        'label' => $this->trans('Image desktop', [], 'Admin.Global'),
                        'name' => 'image_desktop',
                        'required' => true,
                        'lang' => true,
                        'desc' => $this->trans('Maximum image size: %s.', [ini_get('upload_max_filesize')], 'Admin.Global'),
                    ],
                    [
                        'type' => 'file_lang',
                        'label' => $this->trans('Image mobile', [], 'Admin.Global'),
                        'name' => 'image_mobile',
                        'required' => true,
                        'lang' => true,
                        'desc' => $this->trans('Maximum image size: %s.', [ini_get('upload_max_filesize')], 'Admin.Global'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Title', [], 'Admin.Global'),
                        'name' => 'title',
                        'lang' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Target URL', [], self::MODULE_TRANSLATION_KEY),
                        'name' => 'url',
                        'lang' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Caption', [], self::MODULE_TRANSLATION_KEY),
                        'name' => 'legend',
                        'lang' => true,
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->trans('Description', [], 'Admin.Global'),
                        'name' => 'description',
                        'autoload_rte' => true,
                        'lang' => true,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Enabled', [], 'Admin.Global'),
                        'name' => 'active_slide',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Admin.Global'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Admin.Global'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ],
            ],
        ];

        if (Tools::isSubmit('id_slide') && $this->slideExists((int) Tools::getValue('id_slide'))) {
            $slide = new Ks_HomeSlide((int) Tools::getValue('id_slide'));
            $fields_form['form']['input'][] = ['type' => 'hidden', 'name' => 'id_slide'];

            foreach($this->image_types as $image_type) {
                $image_name = 'image_' . $image_type;
                $fields_form['form']['images'][$image_name] = $slide->{$image_name};
            }

            $has_picture = true;

            foreach (Language::getLanguages(false) as $lang) {
                foreach($this->image_types as $image_type) {
                    if (!isset($slide->{'image_' . $image_type}[$lang['id_lang']])) {
                        $has_picture &= false;
                    }
                }
            }

            if ($has_picture) {
                $fields_form['form']['input'][] = ['type' => 'hidden', 'name' => 'has_picture'];
            }
        }

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->module = $this;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitSlide';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $language = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->tpl_vars = [
            'base_url' => $this->context->shop->getBaseURL(),
            'language' => [
                'id_lang' => $language->id,
                'iso_code' => $language->iso_code,
            ],
            'fields_value' => $this->getAddFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
            'image_baseurl' => $this->_path . 'images/',
        ];

        $helper->override_folder = '/';

        $languages = Language::getLanguages(false);

        if (count($languages) > 1) {
            return $this->getMultiLanguageInfoMsg() . $helper->generateForm([$fields_form]);
        } else {
            return $helper->generateForm([$fields_form]);
        }
    }

    public function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings', [], 'Admin.Global'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Speed', [], self::MODULE_TRANSLATION_KEY),
                        'name' => 'HOMESLIDER_SPEED',
                        'suffix' => 'milliseconds',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->trans('The duration of the transition between two slides.', [], self::MODULE_TRANSLATION_KEY),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Pause on hover', [], self::MODULE_TRANSLATION_KEY),
                        'name' => 'HOMESLIDER_PAUSE_ON_HOVER',
                        'desc' => $this->trans('Stop sliding when the mouse cursor is over the slideshow.', [], self::MODULE_TRANSLATION_KEY),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Admin.Global'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Admin.Global'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Loop forever', [], self::MODULE_TRANSLATION_KEY),
                        'name' => 'HOMESLIDER_WRAP',
                        'desc' => $this->trans('Loop or stop after the last slide.', [], self::MODULE_TRANSLATION_KEY),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Admin.Global'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Admin.Global'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitSlider';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fields_form]);
    }

    public function getConfigFieldsValues()
    {
        $id_shop_group = Shop::getContextShopGroupID();
        $id_shop = Shop::getContextShopID();

        return [
            'HOMESLIDER_SPEED' => Tools::getValue('HOMESLIDER_SPEED', Configuration::get('HOMESLIDER_SPEED', null, $id_shop_group, $id_shop)),
            'HOMESLIDER_PAUSE_ON_HOVER' => Tools::getValue('HOMESLIDER_PAUSE_ON_HOVER', Configuration::get('HOMESLIDER_PAUSE_ON_HOVER', null, $id_shop_group, $id_shop)),
            'HOMESLIDER_WRAP' => Tools::getValue('HOMESLIDER_WRAP', Configuration::get('HOMESLIDER_WRAP', null, $id_shop_group, $id_shop)),
        ];
    }

    public function getAddFieldsValues()
    {
        $fields = [];

        if (Tools::isSubmit('id_slide') && $this->slideExists((int) Tools::getValue('id_slide'))) {
            $slide = new Ks_HomeSlide((int) Tools::getValue('id_slide'));
            $fields['id_slide'] = (int) Tools::getValue('id_slide', $slide->id);
        } else {
            $slide = new Ks_HomeSlide();
        }

        $fields['active_slide'] = Tools::getValue('active_slide', $slide->active);
        $fields['has_picture'] = true;

        $languages = Language::getLanguages(false);

        foreach ($languages as $lang) {
            $fields['title'][$lang['id_lang']] = Tools::getValue(
                'title_' . (int) $lang['id_lang'],
                isset($slide->title[$lang['id_lang']]) ? $slide->title[$lang['id_lang']] : ''
            );
            $fields['url'][$lang['id_lang']] = Tools::getValue(
                'url_' . (int) $lang['id_lang'],
                isset($slide->url[$lang['id_lang']]) ? $slide->url[$lang['id_lang']] : ''
            );
            $fields['legend'][$lang['id_lang']] = Tools::getValue(
                'legend_' . (int) $lang['id_lang'],
                isset($slide->legend[$lang['id_lang']]) ? $slide->legend[$lang['id_lang']] : ''
            );
            $fields['description'][$lang['id_lang']] = Tools::getValue(
                'description_' . (int) $lang['id_lang'],
                isset($slide->description[$lang['id_lang']]) ? $slide->description[$lang['id_lang']] : ''
            );
        }

        return $fields;
    }

    protected function getMultiLanguageInfoMsg()
    {
        return '<p class="alert alert-warning">' .
            $this->trans('Since multiple languages are activated on your shop, please mind to upload your image for each one of them', [], self::MODULE_TRANSLATION_KEY) .
            '</p>';
    }

    protected function getWarningMultishopHtml()
    {
        if (Shop::getContext() == Shop::CONTEXT_GROUP || Shop::getContext() == Shop::CONTEXT_ALL) {
            return '<p class="alert alert-warning">' .
                $this->trans('You cannot manage slides items from a "All Shops" or a "Group Shop" context, select directly the shop you want to edit', [], self::MODULE_TRANSLATION_KEY) .
                '</p>';
        } else {
            return '';
        }
    }

    protected function getShopContextError($shop_contextualized_name, $mode)
    {
        if (is_array($shop_contextualized_name)) {
            $shop_contextualized_name = implode('<br/>', $shop_contextualized_name);
        }

        if ($mode == 'edit') {
            return '<p class="alert alert-danger">' .
                $this->trans('You can only edit this slide from the shop(s) context: %s', [$shop_contextualized_name], self::MODULE_TRANSLATION_KEY) .
                '</p>';
        } else {
            return '<p class="alert alert-danger">' .
                $this->trans('You cannot add slides from a "All Shops" or a "Group Shop" context', [], self::MODULE_TRANSLATION_KEY) .
                '</p>';
        }
    }

    protected function getShopAssociationError($id_slide)
    {
        return '<p class="alert alert-danger">' .
            $this->trans('Unable to get slide shop association information (id_slide: %d)', [(int) $id_slide], self::MODULE_TRANSLATION_KEY) .
            '</p>';
    }

    protected function getCurrentShopInfoMsg()
    {
        $shop_info = null;

        if (Shop::isFeatureActive()) {
            if (Shop::getContext() == Shop::CONTEXT_SHOP) {
                $shop_info = $this->trans('The modifications will be applied to shop: %s', [$this->context->shop->name], self::MODULE_TRANSLATION_KEY);
            } elseif (Shop::getContext() == Shop::CONTEXT_GROUP) {
                $shop_info = $this->trans('The modifications will be applied to this group: %s', [Shop::getContextShopGroup()->name], self::MODULE_TRANSLATION_KEY);
            } else {
                $shop_info = $this->trans('The modifications will be applied to all shops and shop groups', [], self::MODULE_TRANSLATION_KEY);
            }

            return '<div class="alert alert-info">' . $shop_info . '</div>';
        } else {
            return '';
        }
    }

    protected function getSharedSlideWarning()
    {
        return '<p class="alert alert-warning">' .
            $this->trans('This slide is shared with other shops! All shops associated to this slide will apply modifications made here', [], self::MODULE_TRANSLATION_KEY) .
            '</p>';
    }
}
