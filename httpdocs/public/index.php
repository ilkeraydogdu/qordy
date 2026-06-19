<?php
// Qordy: Sadece ana domain, subdomain ve admin panel engelleme
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/restrictions/DomainRestriction.php';
\DomainRestriction::apply();

require_once __DIR__ . '/../app/core/Logger.php';
require_once __DIR__ . '/../app/core/ErrorHandler.php';
require_once __DIR__ . '/../app/core/App.php';

\App\Core\ErrorHandler::init();
\App\Core\Logger::init();

$app = new App\Core\App();

