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

namespace PrivateBin;

use Exception;

/**
 * View.
 *
 * Displays the templates
 */
class View
{
    /**
     * variables available in the template.
     *
     * @var array
     */
    private $_variables = [];

    /**
     * assign variables to be used inside of the template.
     *
     * @param string $name
     * @param mixed  $value
     */
    public function assign($name, $value): void
    {
        $this->_variables[$name] = $value;
    }

    /**
     * render a template.
     *
     * @param string $template
     *
     * @throws Exception
     */
    public function draw($template): void
    {
        $file = 'bootstrap' === substr($template, 0, 9) ? 'bootstrap' : $template;
        $path = PATH.'tpl'.\DIRECTORY_SEPARATOR.$file.'.php';
        if (!file_exists($path)) {
            throw new Exception('Template '.$template.' not found!', 80);
        }
        extract($this->_variables);

        include $path;
    }
}
