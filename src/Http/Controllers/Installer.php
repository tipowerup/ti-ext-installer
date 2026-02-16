<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Http\Controllers;

use Igniter\Admin\Classes\AdminController;
use Igniter\Admin\Facades\AdminMenu;

class Installer extends AdminController
{
    /**
     * The required permissions for this controller.
     */
    protected null|string|array $requiredPermissions = 'Tipowerup.Installer.*';

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
        $this->pageTitle = lang('tipowerup.installer::default.text_title');
    }
}
