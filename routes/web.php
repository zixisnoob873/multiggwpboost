<?php

foreach ([
    __DIR__.'/web-routes/auth.php',
    __DIR__.'/web-routes/chat.php',
    __DIR__.'/web-routes/public.php',
    __DIR__.'/web-routes/admin.php',
    __DIR__.'/web-routes/booster.php',
    __DIR__.'/web-routes/customer.php',
] as $routeFile) {
    require $routeFile;
}
