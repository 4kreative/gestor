<?php
class PagesController {
    public function privacy(): void {
        require_once __DIR__.'/../views/pages/privacy.php';
    }
    public function terms(): void {
        require_once __DIR__.'/../views/pages/terms.php';
    }
    public function dataDeletion(): void {
        require_once __DIR__.'/../views/pages/data-deletion.php';
    }
}
