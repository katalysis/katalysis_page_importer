<?php 
namespace Concrete\Package\KatalysisPageImporter;

use Config;
use Page;
use Package;
use SinglePage;
use View;
use KatalysisPageImporter\PageImporter;


class Controller extends Package
{
    protected $pkgHandle = 'katalysis_page_importer';
    protected $appVersionRequired = '9.1';
    protected $pkgVersion = '0.1';
    protected $pkgAutoloaderRegistries = ['src' => 'KatalysisPageImporter'];



    protected $single_pages = array(
        '/dashboard/system/katalysis_page_importer/page_importer' => array(
            'cName' => 'Page Importer'
        )
    );

    public function getPackageName()
    {
        return t("Katalysis Page Importer");
    }

    public function getPackageDescription()
    {
        return t("Import new pages into your Concrete CMS site.");
    }

    public function on_start()
    {
        $this->setupAutoloader();

    }

    private function setupAutoloader()
    {
        if (file_exists($this->getPackagePath() . '/vendor')) {
            require_once $this->getPackagePath() . '/vendor/autoload.php';
        }
    }

    public function install()
    {

        $pkg = parent::install();

        $this->installPages($pkg);
        
    }


    /**
     * @param Package $pkg
     * @return void
     */
    protected function installPages($pkg)
    {
        foreach ($this->single_pages as $path => $value) {
            if (!is_array($value)) {
                $path = $value;
                $value = array();
            }
            $page = Page::getByPath($path);
            if (!$page || $page->isError()) {
                $single_page = SinglePage::add($path, $pkg);

                if ($value) {
                    $single_page->update($value);
                }
            }
        }
    }
}
