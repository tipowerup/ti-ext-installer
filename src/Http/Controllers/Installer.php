<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Http\Controllers;

use Igniter\Admin\Classes\AdminController;
use Igniter\Admin\Facades\AdminMenu;
use Igniter\Admin\Facades\Template;

class Installer extends AdminController
{
    /**
     * The required permissions for this controller.
     */
    protected null|string|array $requiredPermissions = 'Tipowerup.Installer.*';

    /**
     * Custom URL slug to avoid double "installer" in the path.
     */
    public static function getSlug(): string
    {
        return 'tipowerup/installer';
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        AdminMenu::setContext('installer', 'tools');
    }

    /**
     * Display the installer index page.
     */
    public function index(): void
    {
        $pageTitle = lang('tipowerup.installer::default.text_title');
        Template::setTitle($pageTitle);
        Template::setHeading($pageTitle);

        $this->addCss('tipowerup.installer::css/installer.css', 'tipowerup-installer-css');
    }
}
