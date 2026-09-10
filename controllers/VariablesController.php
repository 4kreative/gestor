<?php
class VariablesController {
    public function index(): void {
        requireAuth();
        require_once __DIR__.'/../views/variables/index.php';
    }
}
