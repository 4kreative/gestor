<?php
class HelpController {
    public function index(): void {
        requireAuth();
        require_once __DIR__.'/../views/help/index.php';
    }
}
