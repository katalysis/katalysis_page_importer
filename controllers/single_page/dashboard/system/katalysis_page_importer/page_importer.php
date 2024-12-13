<?php
namespace Concrete\Package\KatalysisPageImporter\Controller\SinglePage\Dashboard\System\KatalysisPageImporter;

use Core;
use PageType;
use Concrete\Core\Page\Type;
use Concrete\Core\Page\Collection\Collection;
use PageTemplate;
use Config;
use Concrete\Core\Page\Page;
use Loader;
use FileImporter;
use File;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Support\Facade\Log;
use Concrete\Core\Support\Facade\Url;


use Concrete\Core\Http\ResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;

class PageImporter extends DashboardPageController
{


    protected $importArray = array();

    protected $pageTypes = null;

    public function on_start()
    {
        $this->error = Loader::helper('validation/error');
    }

    public function on_before_render()
    {
        $this->set('error', $this->error);
    }

    public function view()
    {
        $siteType = $this->app->make('site/type')->getDefault();
        $pageTypes = PageType::getList(false, $siteType);
        $this->set('pageTypes', $pageTypes);
        $this->set('pageTitle', t('Set up Page Import'));

    }

    public function get_page_templates()
    {

        $pageTypeID = $this->request->request->get('pageTypeID');
        $pageType = PageType::getByID($pageTypeID);
        $allowedTemplates = $pageType->getPageTypePageTemplateObjects();

        return new \Symfony\Component\HttpFoundation\JsonResponse($allowedTemplates);
    }


    public function process_import()
    {
        $this->view();



        $this->set('error', $this->error);


        $this->error = Loader::helper('validation/error');
        $pageTypeID = $this->request->request->get('pageType');
        $pageTemplateID = $this->request->request->get('pageTemplate');
        $parentPageID = $this->request->request->get('parentPage');
        $fID = $this->request->request->get('csv');

        if (null == $pageTypeID || empty($pageTypeID)) {
            $this->error->add(t("Please select a Page Type."));
        }
        if (null == $pageTemplateID || empty($pageTemplateID)) {
            $this->error->add(t("Please select a Page Template."));
        }
        if (null == $parentPageID || empty($parentPageID)) {
            $this->error->add(t("Please select a Parent Page."));
        }
        if (null == $fID || empty($fID)) {
            $this->error->add(t("Please select a CSV file."));
        }


        if (!$this->error->has()) {
            $factory = $this->app->make(abstract: ResponseFactory::class);
            return $factory->redirect(Url::to('/dashboard/system/katalysis_page_importer/page_importer/review_import', $pageTypeID, $pageTemplateID, $parentPageID, $fID));

        } else {
            $this->flash('error', $this->error);
        }

    }

    public function review_import($pageTypeID, $pageTemplateID, $parentPageID, $fID)
    {

        $this->set('pageTitle', t('Review Import'));

        $this->view();

        $rowCount = 0;
        $pageTypeName = PageType::getByID($pageTypeID)->getPageTypeDisplayName();
        $pageTemplateName = PageTemplate::getByID($pageTemplateID)->getPageTemplateName();
        $parentPageName = \Page::getByID($parentPageID)->getCollectionName();

        $this->set('pageTypeID', $pageTypeID);
        $this->set('pageTemplateID', $pageTemplateID);
        $this->set('parentPageID', $parentPageID);
        $this->set('csvID', $fID);

        $f = File::getByID($fID);
        if ($f->isError()) {
            $this->error->add($fi->getErrorMessage(FileImporter::E_FILE_INVALID));
        } else {
            $fsr = $f->getFileResource();
            if (!$fsr->isFile()) {
                $this->error->add($fi->getErrorMessage(FileImporter::E_FILE_INVALID));
            }
        }

        if (!$this->error->has() && is_object($fsr)) {
            // Parse the csv file
            $csv = new \ParseCsv\Csv();
            $csv->file_data = $fsr->read();
            $csv->auto();
            $this->set('csv', $csv);

            $rowCount = count($csv->data);
            $this->flash('success', value: 'You are about to create ' . $rowCount . ' pages of type ' . $pageTypeName . ' using the ' . $pageTemplateName . ' template under the ' . $parentPageName . ' page.');

        }

    }


    public function import($pageTypeID, $pageTemplateID, $parentPageID, $fID)
    {

        $this->view();
        $pageType = \PageType::getByID($pageTypeID);
        $template = \PageTemplate::getByID($pageTemplateID);
        $parentPage = \Page::getByID($parentPageID);

        $newPageID = null;

        $f = File::getByID($fID);
        if ($f->isError()) {
            $this->error->add($fi->getErrorMessage(FileImporter::E_FILE_INVALID));
        } else {
            $fsr = $f->getFileResource();
            if (!$fsr->isFile()) {
                $this->error->add($fi->getErrorMessage(FileImporter::E_FILE_INVALID));
            }
        }

        if (!$this->error->has() && is_object($fsr)) {
            // Parse the csv file
            $csv = new \ParseCsv\Csv();
            $csv->file_data = $fsr->read();
            $csv->auto();

            foreach ($csv->data as $key => $row):
                // Create a new page for each row
                $cName = '';
                $cDescription = '';
                $cUrl = '';
                foreach ($row as $rowKey => $value):
                    if ($rowKey == 'Page Name') {
                        $cName = $value;
                    } else if ($rowKey == 'Description') {
                        $cDescription = $value;
                    } else if ($rowKey == 'URL SLug') {
                        $cUrl = $value;
                    }
                    $data = array('cName' => $cName, 'cDescription' => $cDescription, 'cHandle' => $cUrl);
                endforeach;

                $newPage = $parentPage->add($pageType, $data, $template);
                $newPageID = $newPage->getCollectionID();
                $newPageVersion = \Page::getByID($newPageID, $version = 'ACTIVE');

                foreach ($row as $rowKey => $value):
                    if ($rowKey == 'Meta Title') {
                        $newPageVersion->setAttribute('meta_title', $value);
                    } else if ($rowKey == 'Meta Description') {
                        $newPageVersion->setAttribute('meta_description', $value);
                    }
                endforeach;

                $oldblocks = $newPageVersion->getBlocks();

                foreach ($oldblocks as $oldblock) {
                    if ($oldblock->getBlockTypeHandle() == 'content') {
                        //  Duplicate blocks and delete original to break link to defaults
                        $newblock = $oldblock->duplicate($newPageVersion);
                        $count = 0;
                        $occurenceCount = 0;
                        $content = $newblock->getInstance()->getContent();

                        \Log::addInfo('Block: ' . $newblock->getBlockID());

                        foreach ($row as $rowKey => $value):
                            $newValue = $value;
                            // Preprocess list content
                            if (str_starts_with($rowKey, '-[') && str_ends_with($rowKey, ']')) {
                                if (!empty($value)) {
                                    $words = explode(',', $value);
                                    if (!empty($words)) {
                                        $list = '<ul>';
                                        foreach ($words as $word) {
                                            $list .= '<li>' . htmlspecialchars($word) . '</li>';
                                        }
                                        $list .= '</ul>';
                                    }
                                    $newValue = $list;
                                } else {
                                    $newValue = '';
                                }
                            }
                            // Remove leading hyphen from key for list placeholders
                            $placeholder = strval('[' . str_replace('-[', '[', $rowKey) . ']');
                            // Count occurences of placeholder in content and change if present
                            $occurenceCount = substr_count($content, needle: $placeholder);
                            $count = $count + $occurenceCount;
                            if ($occurenceCount > 0) {
                                $content = str_replace($placeholder, $newValue, $content);
                                //Additional step to replace unnecessary p tags that may have wrapped empty list placeholders
                                $content = str_replace('<p></p>', '', $content);
                                $newblock->getInstance()->save(array('content' => $content));
                            }
                        endforeach;
                        if ($count > 0) {
                            $oldblock->delete();
                        } else {
                            $newblock->delete();
                        }
                    }
                }
                // End of row/page
            endforeach;
        }

        $this->set('error', $this->error);

        if (!$this->error->has()) {
            $this->flash('success', value: t('Import completed successfully.'));
            $factory = $this->app->make(abstract: ResponseFactory::class);
            return $factory->redirect(Url::to('/dashboard/system/katalysis_page_importer/page_importer/'));
        } else {
            $this->flash('error', $this->error);
        }
    }

}

