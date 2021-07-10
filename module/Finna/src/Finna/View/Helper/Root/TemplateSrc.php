<?php
/**
 * Resolve path to a resource in theme 'templates' directory.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Resolve path to a template within theme 'templates' directory.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class TemplateSrc extends ThemeSrc
{
    /**
     * Check if template is found in theme 'templates' directory.
     *
     * @param string $path Path (starting from 'templates' directory).
     *
     * @return string
     */
    public function __invoke($path)
    {
        $lang = $this->view->layout()->userLang;
        $langSpecificPath = $path . '_' . $lang;

        if ($url = $this->fileFromCurrentTheme(
            'templates/'
            . $langSpecificPath
            . '.phtml'
        )
        ) {
            return $url;
        }
        if ($url = $this->fileFromCurrentTheme('templates/' . $path . '.phtml')) {
            return $url;
        }

        return '';
    }
}
