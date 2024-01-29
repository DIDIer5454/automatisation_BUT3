<?php

declare(strict_types=1);

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// change this, if your php files and data is outside of your webservers document root
define('PATH', '');

define('PUBLIC_PATH', __DIR__);

require PATH.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
new PrivateBin\Controller();
