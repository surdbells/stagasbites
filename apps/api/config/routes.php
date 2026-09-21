<?php

declare(strict_types=1);

use Slim\App;

return function (App $app): void {
    (require __DIR__ . '/../src/Module/Health/routes.php')($app);
    (require __DIR__ . '/../src/Module/Auth/routes.php')($app);
    (require __DIR__ . '/../src/Module/Catalog/routes.php')($app);
    (require __DIR__ . '/../src/Module/Checkout/routes.php')($app);
    (require __DIR__ . '/../src/Module/Account/routes.php')($app);
    (require __DIR__ . '/../src/Module/Admin/routes.php')($app);
    (require __DIR__ . '/../src/Module/Newsletter/routes.php')($app);

    // Keep last: contains the catch-all that serves the storefront shell.
    (require __DIR__ . '/../src/Module/Seo/routes.php')($app);
};
